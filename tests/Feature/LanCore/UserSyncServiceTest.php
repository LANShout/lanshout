<?php

use App\Models\User;
use App\Services\LanCore\Exceptions\InvalidLanCoreUserException;
use App\Services\LanCore\LanCoreUser;
use App\Services\LanCore\UserSyncService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'https://lancore.test',
        'lancore.token' => 'test-token',
        'lancore.retries' => 0,
    ]);
});

it('creates a new local user from LanCore data', function () {
    $lanCoreUser = new LanCoreUser(
        id: 42,
        username: 'mkohn',
        displayName: 'Matt Kohn',
        email: 'matt@example.com',
        avatarUrl: 'https://lancore.test/avatars/42.jpg',
        locale: 'en',
    );

    $service = app(UserSyncService::class);
    $user = $service->resolveFromUpstream($lanCoreUser);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->exists)->toBeTrue()
        ->and($user->lancore_user_id)->toBe(42)
        ->and($user->name)->toBe('mkohn')
        ->and($user->display_name)->toBe('Matt Kohn')
        ->and($user->email)->toBe('matt@example.com')
        ->and($user->avatar_url)->toBe('https://lancore.test/avatars/42.jpg')
        ->and($user->locale)->toBe('en')
        ->and($user->lancore_synced_at)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();
});

it('updates an existing shadow user with fresh LanCore data', function () {
    $existing = User::factory()->lancore(42)->create([
        'name' => 'old-name',
        'display_name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $lanCoreUser = new LanCoreUser(
        id: 42,
        username: 'new-name',
        displayName: 'New Name',
        email: 'new@example.com',
        avatarUrl: 'https://lancore.test/new.jpg',
        locale: 'de',
    );

    $service = app(UserSyncService::class);
    $updated = $service->resolveFromUpstream($lanCoreUser);

    expect($updated->id)->toBe($existing->id)
        ->and($updated->name)->toBe('new-name')
        ->and($updated->display_name)->toBe('New Name')
        ->and($updated->email)->toBe('new@example.com')
        ->and($updated->avatar_url)->toBe('https://lancore.test/new.jpg')
        ->and($updated->locale)->toBe('de');

    expect(User::count())->toBe(1);
});

it('does not create a duplicate when lancore_user_id already exists', function () {
    User::factory()->lancore(99)->create();

    $lanCoreUser = new LanCoreUser(
        id: 99,
        username: 'updated',
        displayName: 'Updated User',
        email: 'updated@example.com',
    );

    $service = app(UserSyncService::class);
    $service->resolveFromUpstream($lanCoreUser);

    expect(User::where('lancore_user_id', 99)->count())->toBe(1);
});

it('throws InvalidLanCoreUserException for invalid user data', function () {
    $invalidUser = new LanCoreUser(
        id: 0,
        username: '',
        displayName: '',
        email: '',
    );

    $service = app(UserSyncService::class);
    $service->resolveFromUpstream($invalidUser);
})->throws(InvalidLanCoreUserException::class);

it('refreshes a LanCore user from upstream', function () {
    Http::fake([
        'lancore.test/api/v1/users/42' => Http::response([
            'user' => [
                'id' => 42,
                'username' => 'refreshed',
                'display_name' => 'Refreshed User',
                'email' => 'refreshed@example.com',
                'avatar_url' => null,
                'locale' => 'de',
            ],
        ], 200),
    ]);

    $user = User::factory()->lancore(42)->create([
        'name' => 'stale',
    ]);

    $service = app(UserSyncService::class);
    $refreshed = $service->refreshFromLanCore($user);

    expect($refreshed->name)->toBe('refreshed')
        ->and($refreshed->display_name)->toBe('Refreshed User');
});

it('returns unmodified user when refreshing a non-LanCore user', function () {
    $user = User::factory()->create(['lancore_user_id' => null]);

    $service = app(UserSyncService::class);
    $result = $service->refreshFromLanCore($user);

    expect($result->id)->toBe($user->id)
        ->and($result->name)->toBe($user->name);
});
