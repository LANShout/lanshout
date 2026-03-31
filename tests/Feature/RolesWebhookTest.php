<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(fn () => Config::set('lancore.webhook_secret', null));

function rolesWebhookPayload(array $overrides = []): array
{
    return array_merge([
        'event' => 'user.roles_updated',
        'user' => [
            'id' => 42,
            'username' => 'alice',
            'roles' => ['user', 'moderator'],
        ],
        'changes' => [
            'added' => ['moderator'],
            'removed' => ['admin'],
        ],
    ], $overrides);
}

function signedPost(mixed $test, string $url, array $payload, string $secret): mixed
{
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

    return $test->postJson($url, $payload, ['X-Webhook-Signature' => $signature]);
}

// --- Happy path ---

it('syncs roles from the webhook payload', function () {
    $user = User::factory()->lancore(42)->create();
    Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);
    Role::firstOrCreate(['name' => 'moderator'], ['display_name' => 'Moderator']);

    $this->postJson('/api/webhooks/roles', rolesWebhookPayload())
        ->assertOk()
        ->assertJson(['message' => 'Roles synced.']);

    $user->refresh();
    expect($user->roles->pluck('name')->sort()->values()->all())
        ->toBe(['moderator', 'user']);
});

it('maps superadmin to super_admin', function () {
    $user = User::factory()->lancore(42)->create();
    Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin']);

    $payload = rolesWebhookPayload(['user' => ['id' => 42, 'username' => 'alice', 'roles' => ['superadmin']]]);

    $this->postJson('/api/webhooks/roles', $payload)->assertOk();

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['super_admin']);
});

it('preserves local-only roles not managed by LanCore', function () {
    $user = User::factory()->lancore(42)->create();
    $localRole = Role::firstOrCreate(['name' => 'event_staff'], ['display_name' => 'Event Staff']);
    $userRole = Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);
    $user->roles()->attach($localRole->id);

    $payload = rolesWebhookPayload(['user' => ['id' => 42, 'username' => 'alice', 'roles' => ['user']]]);

    $this->postJson('/api/webhooks/roles', $payload)->assertOk();

    $user->refresh();
    $roleNames = $user->roles->pluck('name')->sort()->values()->all();
    expect($roleNames)->toBe(['event_staff', 'user']);
});

it('removes managed roles no longer present in the payload', function () {
    $user = User::factory()->lancore(42)->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    $userRole = Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);
    $user->roles()->attach([$adminRole->id, $userRole->id]);

    $payload = rolesWebhookPayload(['user' => ['id' => 42, 'username' => 'alice', 'roles' => ['user']]]);

    $this->postJson('/api/webhooks/roles', $payload)->assertOk();

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['user']);
});

// --- Idempotency ---

it('produces the same result when delivered twice', function () {
    $user = User::factory()->lancore(42)->create();
    Role::firstOrCreate(['name' => 'moderator'], ['display_name' => 'Moderator']);

    $payload = rolesWebhookPayload(['user' => ['id' => 42, 'username' => 'alice', 'roles' => ['moderator']]]);

    $this->postJson('/api/webhooks/roles', $payload)->assertOk();
    $this->postJson('/api/webhooks/roles', $payload)->assertOk();

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['moderator']);
});

// --- Unknown user ---

it('returns 200 when user is not found locally', function () {
    $payload = rolesWebhookPayload(['user' => ['id' => 999, 'username' => 'nobody', 'roles' => ['admin']]]);

    $this->postJson('/api/webhooks/roles', $payload)
        ->assertOk()
        ->assertJson(['message' => 'User not found locally, ignored.']);
});

// --- Validation ---

it('rejects an invalid event type', function () {
    $payload = rolesWebhookPayload(['event' => 'user.deleted']);

    $this->postJson('/api/webhooks/roles', $payload)
        ->assertUnprocessable();
});

it('rejects a missing user object', function () {
    $this->postJson('/api/webhooks/roles', ['event' => 'user.roles_updated'])
        ->assertUnprocessable();
});

it('rejects a missing roles array', function () {
    $payload = rolesWebhookPayload(['user' => ['id' => 42, 'username' => 'alice']]);

    $this->postJson('/api/webhooks/roles', $payload)
        ->assertUnprocessable();
});

// --- Signature verification ---

it('accepts a request with a valid sha256= prefixed signature', function () {
    $user = User::factory()->lancore(42)->create();
    Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);

    $secret = 'webhook-test-secret';
    Config::set('lancore.webhook_secret', $secret);

    $payload = rolesWebhookPayload(['user' => ['id' => 42, 'username' => 'alice', 'roles' => ['user']]]);

    signedPost($this, '/api/webhooks/roles', $payload, $secret)
        ->assertOk();

    $user->refresh();
    expect($user->roles->pluck('name')->all())->toBe(['user']);
});

it('accepts a request with a raw hex signature (no prefix)', function () {
    $secret = 'webhook-test-secret';
    Config::set('lancore.webhook_secret', $secret);

    $user = User::factory()->lancore(42)->create();
    Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);

    $payload = rolesWebhookPayload(['user' => ['id' => 42, 'username' => 'alice', 'roles' => ['user']]]);
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret);

    $this->postJson('/api/webhooks/roles', $payload, ['X-Webhook-Signature' => $signature])
        ->assertOk();
});

it('rejects a request with an invalid signature', function () {
    Config::set('lancore.webhook_secret', 'correct-secret');

    $this->postJson('/api/webhooks/roles', rolesWebhookPayload(), ['X-Webhook-Signature' => 'sha256=invalid'])
        ->assertUnauthorized();
});

it('rejects a request with a missing signature when secret is configured', function () {
    Config::set('lancore.webhook_secret', 'test-secret');

    $this->postJson('/api/webhooks/roles', rolesWebhookPayload())
        ->assertUnauthorized();
});

it('passes through when no webhook secret is configured', function () {
    Config::set('lancore.webhook_secret', null);

    $user = User::factory()->lancore(42)->create();
    Role::firstOrCreate(['name' => 'user'], ['display_name' => 'User']);

    $payload = rolesWebhookPayload(['user' => ['id' => 42, 'username' => 'alice', 'roles' => ['user']]]);

    $this->postJson('/api/webhooks/roles', $payload)->assertOk();
});
