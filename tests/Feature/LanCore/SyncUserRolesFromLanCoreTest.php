<?php

use App\Actions\LanCore\SyncUserRolesFromLanCore;
use App\Models\Role;
use App\Models\User;
use App\Services\LanCore\LanCoreUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'https://lancore.test',
        'lancore.internal_url' => null,
        'lancore.token' => 'lci_test-integration-token',
        'lancore.retries' => 0,
    ]);
});

// --- Helpers ---

function lanCoreResolveResponse(array $overrides = []): array
{
    return [
        'data' => array_merge([
            'id' => 42,
            'username' => 'alice',
            'locale' => 'en',
            'avatar_url' => null,
            'created_at' => '2025-01-01T00:00:00Z',
            'email' => 'alice@example.com',
            'roles' => ['user'],
        ], $overrides),
    ];
}

// --- Syncing from a provided LanCoreUser DTO ---

it('syncs LanCore roles when DTO is provided directly', function () {
    $user = User::factory()->lancore(42)->create();
    $moderatorRole = Role::firstOrCreate(['name' => 'moderator'], ['display_name' => 'Moderator']);
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

    $lanCoreUser = new LanCoreUser(id: 42, username: 'alice', roles: ['moderator', 'admin']);

    app(SyncUserRolesFromLanCore::class)->execute($user, $lanCoreUser);

    $user->refresh();
    expect($user->roles->pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'moderator']);
});

it('maps superadmin to super_admin when syncing', function () {
    $user = User::factory()->lancore(42)->create();
    Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin']);

    $lanCoreUser = new LanCoreUser(id: 42, username: 'alice', roles: ['superadmin']);

    app(SyncUserRolesFromLanCore::class)->execute($user, $lanCoreUser);

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['super_admin']);
});

it('removes previously held managed roles not present in LanCore response', function () {
    $user = User::factory()->lancore(42)->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $userRole = Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);
    $user->roles()->attach([$adminRole->id, $userRole->id]);

    $lanCoreUser = new LanCoreUser(id: 42, username: 'alice', roles: ['user']);

    app(SyncUserRolesFromLanCore::class)->execute($user, $lanCoreUser);

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['user']);
});

it('preserves local-only roles not managed by LanCore', function () {
    $user = User::factory()->create();

    $localRole = Role::firstOrCreate(['name' => 'event_staff'], ['display_name' => 'Event Staff']);
    $userRole = Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);
    $user->roles()->attach($localRole->id);

    $lanCoreUser = new LanCoreUser(id: 42, username: 'alice', roles: ['user']);

    app(SyncUserRolesFromLanCore::class)->execute($user, $lanCoreUser);

    $user->refresh();
    $roleNames = $user->roles->pluck('name')->sort()->values()->all();
    expect($roleNames)->toBe(['event_staff', 'user']);
});

it('removes all managed roles when LanCore returns an empty roles array', function () {
    $user = User::factory()->lancore(42)->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $user->roles()->attach($adminRole->id);

    $lanCoreUser = new LanCoreUser(id: 42, username: 'alice', roles: []);

    app(SyncUserRolesFromLanCore::class)->execute($user, $lanCoreUser);

    $user->refresh();
    expect($user->roles()->count())->toBe(0);
});

it('does not modify roles when LanCore roles scope is not granted (roles null)', function () {
    $user = User::factory()->lancore(42)->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $user->roles()->attach($adminRole->id);

    $lanCoreUser = new LanCoreUser(id: 42, username: 'alice', roles: null);

    app(SyncUserRolesFromLanCore::class)->execute($user, $lanCoreUser);

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['admin']);
});

// --- Syncing via API call (no DTO provided) ---

it('fetches roles from LanCore API and syncs them', function () {
    $user = User::factory()->lancore(42)->create(['email' => 'alice@example.com']);
    $moderatorRole = Role::firstOrCreate(['name' => 'moderator'], ['display_name' => 'Moderator']);

    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(
            lanCoreResolveResponse(['roles' => ['moderator']]),
            200
        ),
    ]);

    app(SyncUserRolesFromLanCore::class)->execute($user);

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['moderator']);
});

it('uses lancore_user_id when available to resolve', function () {
    $user = User::factory()->lancore(42)->create();
    Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);

    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(
            lanCoreResolveResponse(['id' => 42, 'roles' => ['user']]),
            200
        ),
    ]);

    app(SyncUserRolesFromLanCore::class)->execute($user);

    Http::assertSent(fn ($request) => $request->data() === ['user_id' => 42]);
});

it('falls back to email when lancore_user_id is null', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);
    Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);

    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(
            lanCoreResolveResponse(['roles' => ['user']]),
            200
        ),
    ]);

    app(SyncUserRolesFromLanCore::class)->execute($user);

    Http::assertSent(fn ($request) => $request->data() === ['email' => 'alice@example.com']);
});

// --- 404 behaviour ---

it('leaves local roles unchanged when LanCore returns 404', function () {
    $user = User::factory()->lancore(42)->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $user->roles()->attach($adminRole->id);

    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(['error' => 'Not found'], 404),
    ]);

    app(SyncUserRolesFromLanCore::class)->execute($user);

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe([]);
});

// --- Network / unexpected error behaviour ---

it('leaves local roles unchanged and logs on connection error', function () {
    Log::spy();

    $user = User::factory()->lancore(42)->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $user->roles()->attach($adminRole->id);

    Http::fake([
        'lancore.test/api/integration/user/resolve' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    app(SyncUserRolesFromLanCore::class)->execute($user);

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['admin']);

    Log::shouldHaveReceived('error')->atLeast()->once();
});

it('leaves local roles unchanged and logs on unexpected server error', function () {
    Log::spy();

    $user = User::factory()->lancore(42)->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $user->roles()->attach($adminRole->id);

    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(['error' => 'Internal Server Error'], 500),
    ]);

    app(SyncUserRolesFromLanCore::class)->execute($user);

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['admin']);

    Log::shouldHaveReceived('error')->once();
});

// --- SSO callback integration ---

it('syncs roles during SSO login using exchange response roles directly', function () {
    $moderatorRole = Role::firstOrCreate(['name' => 'moderator'], ['display_name' => 'Moderator']);

    Http::fake([
        'lancore.test/api/integration/sso/exchange' => Http::response([
            'data' => [
                'id' => 42,
                'username' => 'alice',
                'locale' => 'en',
                'avatar_url' => null,
                'created_at' => '2025-01-01T00:00:00Z',
                'email' => 'alice@example.com',
                'roles' => ['moderator'],
            ],
        ], 200),
    ]);

    $code = str_repeat('a', 64);
    $this->get(route('lancore.callback', ['code' => $code]));

    $user = User::where('lancore_user_id', 42)->first();
    expect($user->roles->pluck('name')->all())->toBe(['moderator']);

    // Confirm no resolve endpoint was called (roles came from exchange)
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/integration/user/resolve'));
});

// --- Login event listener ---

it('skips role sync for users with a recent lancore_synced_at (SSO just handled it)', function () {
    Http::fake();

    $user = User::factory()->lancore(42)->create([
        'lancore_synced_at' => now(),
    ]);

    $this->actingAs($user);

    Http::assertNothingSent();
});

it('syncs roles on standard login for users without recent lancore sync', function () {
    Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);

    $user = User::factory()->create([
        'email' => 'alice@example.com',
        'password' => bcrypt('password'),
    ]);

    Http::fake([
        'lancore.test/api/integration/user/resolve' => Http::response(
            lanCoreResolveResponse(['roles' => ['user']]),
            200
        ),
    ]);

    $this->post(route('login'), [
        'email' => 'alice@example.com',
        'password' => 'password',
    ]);

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['user']);
});
