<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'https://lancore.test',
        'lancore.token' => 'test-integration-token',
        'lancore.retries' => 0,
    ]);
});

it('authenticates a user via LanCore callback', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => Http::response([
            'user' => [
                'id' => 42,
                'username' => 'mkohn',
                'display_name' => 'Matt Kohn',
                'email' => 'matt@example.com',
                'avatar_url' => 'https://lancore.test/avatars/42.jpg',
                'locale' => 'en',
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'token' => 'valid-user-token',
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

it('updates existing shadow user on repeat login', function () {
    User::factory()->lancore(42)->create([
        'name' => 'old',
        'email' => 'old@example.com',
    ]);

    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => Http::response([
            'user' => [
                'id' => 42,
                'username' => 'mkohn',
                'display_name' => 'Matt Kohn',
                'email' => 'matt@example.com',
                'avatar_url' => null,
                'locale' => null,
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'token' => 'valid-user-token',
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
        'token' => 'some-token',
    ]);

    $response->assertStatus(503)
        ->assertJson([
            'message' => 'LanCore integration is currently disabled.',
        ]);

    $this->assertGuest();
});

it('returns 502 when LanCore is unreachable', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'token' => 'some-token',
    ]);

    $response->assertStatus(502)
        ->assertJson([
            'message' => 'Unable to reach the identity provider. Please try again later.',
        ]);

    $this->assertGuest();
});

it('returns 401 when token is invalid', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'token' => 'invalid-token',
    ]);

    $response->assertStatus(502);
    $this->assertGuest();
});

it('returns 422 when LanCore returns incomplete user data', function () {
    Http::fake([
        'lancore.test/api/v1/auth/verify-token' => Http::response([
            'user' => [
                'id' => 0,
                'username' => '',
                'email' => '',
            ],
        ], 200),
    ]);

    $response = $this->postJson(route('lancore.callback'), [
        'token' => 'some-token',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Received incomplete user information from the identity provider.',
        ]);

    $this->assertGuest();
});

it('validates that token is required', function () {
    $response = $this->postJson(route('lancore.callback'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('token');
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
