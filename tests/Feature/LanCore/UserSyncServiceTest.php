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
        'lancore.token' => 'lci_test-token',
        'lancore.retries' => 0,
    ]);
});

it('creates a new local user from LanCore data', function () {
    $lanCoreUser = new LanCoreUser(
        id: 42,
        username: 'mkohn',
        locale: 'en',
        avatar: 'https://lancore.test/avatars/42.jpg',
        email: 'matt@example.com',
    );

    $service = app(UserSyncService::class);
    $user = $service->resolveFromUpstream($lanCoreUser);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->exists)->toBeTrue()
        ->and($user->lancore_user_id)->toBe(42)
        ->and($user->name)->toBe('mkohn')
        ->and($user->display_name)->toBe('mkohn')
        ->and($user->email)->toBe('matt@example.com')
        ->and($user->avatar_url)->toBe('https://lancore.test/avatars/42.jpg')
        ->and($user->locale)->toBe('en')
        ->and($user->lancore_synced_at)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();
});

it('creates a shadow user without email when scope not granted', function () {
    $lanCoreUser = new LanCoreUser(
        id: 55,
        username: 'no-email',
        locale: 'de',
    );

    $service = app(UserSyncService::class);
    $user = $service->resolveFromUpstream($lanCoreUser);

    expect($user->lancore_user_id)->toBe(55)
        ->and($user->name)->toBe('no-email')
        ->and($user->email)->toBeNull();
});

it('updates an existing shadow user with fresh LanCore data', function () {
    $existing = User::factory()->lancore(42)->create([
        'name' => 'old-name',
        'display_name' => 'Custom Display',
        'email' => 'old@example.com',
    ]);

    $lanCoreUser = new LanCoreUser(
        id: 42,
        username: 'new-name',
        locale: 'de',
        avatar: 'https://lancore.test/new.jpg',
        email: 'new@example.com',
    );

    $service = app(UserSyncService::class);
    $updated = $service->resolveFromUpstream($lanCoreUser);

    expect($updated->id)->toBe($existing->id)
        ->and($updated->name)->toBe('new-name')
        ->and($updated->display_name)->toBe('Custom Display')
        ->and($updated->email)->toBe('new@example.com')
        ->and($updated->avatar_url)->toBe('https://lancore.test/new.jpg')
        ->and($updated->locale)->toBe('de');

    expect(User::count())->toBe(1);
});

it('does not overwrite email when scope not granted on update', function () {
    User::factory()->lancore(42)->create([
        'email' => 'existing@example.com',
    ]);

    $lanCoreUser = new LanCoreUser(
        id: 42,
        username: 'updated',
        email: null,
    );

    $service = app(UserSyncService::class);
    $updated = $service->resolveFromUpstream($lanCoreUser);

    expect($updated->email)->toBe('existing@example.com');
});

it('does not create a duplicate when lancore_user_id already exists', function () {
    User::factory()->lancore(99)->create();

    $lanCoreUser = new LanCoreUser(
        id: 99,
        username: 'updated',
    );

    $service = app(UserSyncService::class);
    $service->resolveFromUpstream($lanCoreUser);

    expect(User::where('lancore_user_id', 99)->count())->toBe(1);
});

it('throws InvalidLanCoreUserException for invalid user data', function () {
    $invalidUser = new LanCoreUser(
        id: 0,
        username: '',
    );

    $service = app(UserSyncService::class);
    $service->resolveFromUpstream($invalidUser);
})->throws(InvalidLanCoreUserException::class);

it('refreshes a LanCore user from upstream', function () {
    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'refreshed',
                'locale' => 'de',
                'avatar' => null,
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'refreshed@example.com',
            ],
        ], 200),
    ]);

    $user = User::factory()->lancore(42)->create([
        'name' => 'stale',
    ]);

    $service = app(UserSyncService::class);
    $refreshed = $service->refreshFromLanCore($user);

    expect($refreshed->name)->toBe('refreshed')
        ->and($refreshed->email)->toBe('refreshed@example.com');
});

it('returns unmodified user when refreshing a non-LanCore user', function () {
    $user = User::factory()->create(['lancore_user_id' => null]);

    $service = app(UserSyncService::class);
    $result = $service->refreshFromLanCore($user);

    expect($result->id)->toBe($user->id)
        ->and($result->name)->toBe($user->name);
});
