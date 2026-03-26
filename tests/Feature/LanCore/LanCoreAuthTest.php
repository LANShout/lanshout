<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'https://lancore.test',
        'lancore.internal_url' => null,
        'lancore.token' => 'lci_test-integration-token',
        'lancore.app_slug' => 'lanshout',
        'lancore.callback_url' => 'https://shout.test/auth/lancore/callback',
        'lancore.retries' => 0,
    ]);
});

it('redirects to LanCore SSO authorize endpoint', function () {
    $response = $this->get(route('lancore.redirect'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toContain('lancore.test/sso/authorize')
        ->and($location)->toContain('app=lanshout')
        ->and($location)->toContain(urlencode('https://shout.test/auth/lancore/callback'));
});

it('returns 503 when redirecting while disabled', function () {
    config(['lancore.enabled' => false]);

    $response = $this->get(route('lancore.redirect'));

    $response->assertStatus(503);
});

it('authenticates a user via SSO code exchange', function () {
    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'mkohn',
                'locale' => 'en',
                'avatar_url' => 'https://lancore.test/avatars/42.jpg',
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'matt@example.com',
            ],
        ], 200),
    ]);

    $code = str_repeat('a', 64);
    $response = $this->get(route('lancore.callback', ['code' => $code]));

    $response->assertRedirect(config('fortify.home', '/'));
    $this->assertAuthenticated();

    $user = User::where('lancore_user_id', 42)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('mkohn')
        ->and($user->email)->toBe('matt@example.com');
});

it('updates existing shadow user on repeat SSO login', function () {
    User::factory()->lancore(42)->create([
        'name' => 'old',
        'email' => 'old@example.com',
    ]);

    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'mkohn',
                'locale' => null,
                'avatar_url' => null,
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'matt@example.com',
            ],
        ], 200),
    ]);

    $code = str_repeat('b', 64);
    $response = $this->get(route('lancore.callback', ['code' => $code]));

    $response->assertRedirect();
    $this->assertAuthenticated();

    $user = User::where('lancore_user_id', 42)->first();
    expect($user->name)->toBe('mkohn')
        ->and($user->email)->toBe('matt@example.com');

    expect(User::where('lancore_user_id', 42)->count())->toBe(1);
});

it('redirects home with error when code is expired or used', function () {
    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response([
            'error' => 'Invalid or expired authorization code',
        ], 400),
    ]);

    $code = str_repeat('c', 64);
    $response = $this->get(route('lancore.callback', ['code' => $code]));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

it('redirects home when LanCore is unreachable', function () {
    Http::fake([
        'lancore.test/api/integration/sso/exchange' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $code = str_repeat('d', 64);
    $response = $this->get(route('lancore.callback', ['code' => $code]));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

it('redirects home when LanCore rejects integration token', function () {
    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $code = str_repeat('e', 64);
    $response = $this->get(route('lancore.callback', ['code' => $code]));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

it('redirects home when LanCore returns incomplete user data', function () {
    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response([
            'data' => [
                'id' => 0,
                'username' => '',
            ],
        ], 200),
    ]);

    $code = str_repeat('f', 64);
    $response = $this->get(route('lancore.callback', ['code' => $code]));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
    $this->assertGuest();
});

it('validates that code is required and 64 characters', function () {
    $response = $this->get(route('lancore.callback'));

    $response->assertSessionHasErrors('code');
});

it('validates that code must be exactly 64 characters', function () {
    $response = $this->get(route('lancore.callback', ['code' => 'too-short']));

    $response->assertSessionHasErrors('code');
});

it('returns status endpoint correctly when enabled', function () {
    $response = $this->getJson(route('lancore.status'));

    $response->assertOk()
        ->assertJson(['enabled' => true]);
});

it('returns status endpoint correctly when disabled', function () {
    config(['lancore.enabled' => false]);

    $response = $this->getJson(route('lancore.status'));

    $response->assertOk()
        ->assertJson(['enabled' => false]);
});

// --- Auto-redirect tests ---

it('auto-redirects landing page to LanCore SSO when enabled', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('lancore.redirect'));
});

it('shows landing page when LanCore is disabled', function () {
    config(['lancore.enabled' => false]);

    $response = $this->get('/');

    $response->assertOk();
});

it('auto-redirects login page to LanCore SSO when enabled', function () {
    $response = $this->get('/login');

    $response->assertRedirect(route('lancore.redirect'));
});

it('shows login form when local query param is present', function () {
    $response = $this->get('/login?local');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('auth/Login'));
});

it('shows login form when LanCore is disabled', function () {
    config(['lancore.enabled' => false]);

    $response = $this->get('/login');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('auth/Login'));
});

it('redirects authenticated users from landing to chat regardless of LanCore', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get('/');

    $response->assertRedirect(route('chat'));
});
