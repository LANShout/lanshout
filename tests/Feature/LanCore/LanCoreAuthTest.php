<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'https://lancore.test',
        'lancore.token' => 'lci_test-integration-token',
        'lancore.retries' => 0,
    ]);
});

it('authenticates a user by lancore_user_id', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'mkohn',
                'locale' => 'en',
                'avatar' => 'https://lancore.test/avatars/42.jpg',
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'matt@example.com',
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'lancore_user_id' => 42,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Authenticated via LanCore.',
        ])
        ->assertJsonStructure(['redirect']);

    $this->assertAuthenticated();

    $user = User::where('lancore_user_id', 42)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('mkohn')
        ->and($user->email)->toBe('matt@example.com');
});

it('authenticates a user by email', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 55,
                'username' => 'jane',
                'locale' => 'de',
                'avatar' => null,
                'created_at' => '2025-06-01T00:00:00Z',
                'email' => 'jane@example.com',
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'email' => 'jane@example.com',
    ]);

    $response->assertOk();
    $this->assertAuthenticated();

    $user = User::where('lancore_user_id', 55)->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('jane');
});

it('updates existing shadow user on repeat login', function () {
    User::factory()->lancore(42)->create([
        'name' => 'old',
        'email' => 'old@example.com',
    ]);

    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'mkohn',
                'locale' => null,
                'avatar' => null,
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'matt@example.com',
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'lancore_user_id' => 42,
    ]);

    $response->assertOk();
    $this->assertAuthenticated();

    $user = User::where('lancore_user_id', 42)->first();
    expect($user->name)->toBe('mkohn')
        ->and($user->email)->toBe('matt@example.com');

    expect(User::where('lancore_user_id', 42)->count())->toBe(1);
});

it('returns 503 when LanCore integration is disabled', function () {
    config(['lancore.enabled' => false]);

    $response = $this->postJson(route('lancore.callback'), [
        'lancore_user_id' => 42,
    ]);

    $response->assertStatus(503)
        ->assertJson([
            'message' => 'LanCore integration is currently disabled.',
        ]);

    $this->assertGuest();
});

it('returns 502 when LanCore is unreachable', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'lancore_user_id' => 42,
    ]);

    $response->assertStatus(502)
        ->assertJson([
            'message' => 'Unable to reach the identity provider. Please try again later.',
        ]);

    $this->assertGuest();
});

it('returns 502 when LanCore rejects integration token', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'lancore_user_id' => 42,
    ]);

    $response->assertStatus(502);
    $this->assertGuest();
});

it('returns 422 when LanCore returns incomplete user data', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 0,
                'username' => '',
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'lancore_user_id' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Received incomplete user information from the identity provider.',
        ]);

    $this->assertGuest();
});

it('validates that lancore_user_id or email is required', function () {
    $response = $this->postJson(route('lancore.callback'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['lancore_user_id', 'email']);
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
