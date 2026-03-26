<?php

use App\Models\ChatSetting;
use App\Models\FilterChain;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;

function createModerator(): User
{
    $role = Role::firstOrCreate(['name' => 'moderator'], ['display_name' => 'Moderator']);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

// ─── Chat page: isModerator prop ─────────────────────────────────────────────

it('chat page passes isModerator=false for regular users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/chat');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chat')
            ->where('isModerator', false)
        );
});

it('chat page passes isModerator=true for moderators', function () {
    $moderator = createModerator();

    $response = $this->actingAs($moderator)->get('/chat');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Chat')
            ->where('isModerator', true)
        );
});

// ─── Settings page access ─────────────────────────────────────────────────────

it('settings page is inaccessible for regular users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/chat/settings')
        ->assertForbidden();
});

it('settings page is accessible for moderators', function () {
    $this->actingAs(createModerator())
        ->get('/chat/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('chat/Settings'));
});

it('settings page returns filterChains and slowMode props', function () {
    FilterChain::create([
        'name' => 'Test Filter',
        'type' => 'contains',
        'pattern' => 'badword',
        'action' => 'block',
        'is_active' => true,
        'priority' => 0,
        'created_by' => null,
    ]);

    $this->actingAs(createModerator())
        ->get('/chat/settings')
        ->assertInertia(fn ($page) => $page
            ->component('chat/Settings')
            ->has('filterChains', 1)
            ->has('slowMode')
        );
});

// ─── User moderation: timeout ─────────────────────────────────────────────────

it('moderator can timeout a regular user', function () {
    $moderator = createModerator();
    $target = User::factory()->create();

    $this->actingAs($moderator)
        ->postJson("/chat/moderation/users/{$target->id}/timeout", [
            'duration_minutes' => 30,
            'reason' => 'Spamming',
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => "User {$target->name} has been timed out."]);

    expect($target->fresh()->isTimedOut())->toBeTrue();
});

it('moderator cannot timeout another moderator', function () {
    $moderator = createModerator();
    $otherMod = createModerator();

    $this->actingAs($moderator)
        ->postJson("/chat/moderation/users/{$otherMod->id}/timeout", ['duration_minutes' => 10])
        ->assertForbidden();
});

it('regular user cannot timeout anyone', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->postJson("/chat/moderation/users/{$target->id}/timeout", ['duration_minutes' => 10])
        ->assertForbidden();
});

it('moderator can relieve a timeout', function () {
    $moderator = createModerator();
    $target = User::factory()->create(['timed_out_until' => Carbon::now()->addHour()]);

    $this->actingAs($moderator)
        ->deleteJson("/chat/moderation/users/{$target->id}/timeout")
        ->assertOk();

    expect($target->fresh()->isTimedOut())->toBeFalse();
});

it('timeout validation requires duration_minutes', function () {
    $moderator = createModerator();
    $target = User::factory()->create();

    $this->actingAs($moderator)
        ->postJson("/chat/moderation/users/{$target->id}/timeout", [])
        ->assertUnprocessable();
});

it('timeout duration must be between 1 and 10080 minutes', function (int $minutes) {
    $moderator = createModerator();
    $target = User::factory()->create();

    $this->actingAs($moderator)
        ->postJson("/chat/moderation/users/{$target->id}/timeout", ['duration_minutes' => $minutes])
        ->assertUnprocessable();
})->with([0, 10081, -5]);

// ─── User moderation: block ───────────────────────────────────────────────────

it('moderator can block a regular user', function () {
    $moderator = createModerator();
    $target = User::factory()->create();

    $this->actingAs($moderator)
        ->postJson("/chat/moderation/users/{$target->id}/block", ['reason' => 'Rule violation'])
        ->assertOk()
        ->assertJsonFragment(['message' => "User {$target->name} has been blocked."]);

    expect($target->fresh()->isBlocked())->toBeTrue();
});

it('moderator cannot block another moderator', function () {
    $moderator = createModerator();
    $otherMod = createModerator();

    $this->actingAs($moderator)
        ->postJson("/chat/moderation/users/{$otherMod->id}/block", [])
        ->assertForbidden();
});

it('moderator can unblock a blocked user', function () {
    $moderator = createModerator();
    $target = User::factory()->create(['is_blocked' => true, 'blocked_at' => now()]);

    $this->actingAs($moderator)
        ->deleteJson("/chat/moderation/users/{$target->id}/block")
        ->assertOk();

    expect($target->fresh()->isBlocked())->toBeFalse();
});

it('blocked user cannot send messages', function () {
    $user = User::factory()->create(['is_blocked' => true]);

    $this->actingAs($user)
        ->postJson('/messages', ['body' => 'Hello'])
        ->assertForbidden();
});

it('timed-out user cannot send messages', function () {
    $user = User::factory()->create(['timed_out_until' => Carbon::now()->addHour()]);

    $this->actingAs($user)
        ->postJson('/messages', ['body' => 'Hello'])
        ->assertStatus(429);
});

// ─── Filter chains ────────────────────────────────────────────────────────────

it('moderator can create a filter chain', function () {
    $this->actingAs(createModerator())
        ->postJson('/chat/filters', [
            'name' => 'Swear filter',
            'type' => 'contains',
            'pattern' => 'badword',
            'action' => 'block',
            'is_active' => true,
            'priority' => 0,
        ])
        ->assertStatus(201)
        ->assertJsonFragment(['message' => "Filter 'Swear filter' created."]);

    expect(FilterChain::where('name', 'Swear filter')->exists())->toBeTrue();
});

it('moderator can update a filter chain', function () {
    $filter = FilterChain::create([
        'name' => 'Old Name',
        'type' => 'contains',
        'pattern' => 'test',
        'action' => 'block',
        'is_active' => true,
        'priority' => 0,
        'created_by' => null,
    ]);

    $this->actingAs(createModerator())
        ->putJson("/chat/filters/{$filter->id}", [
            'name' => 'New Name',
            'type' => 'exact',
            'pattern' => 'test',
            'action' => 'warn',
            'is_active' => false,
            'priority' => 5,
        ])
        ->assertOk();

    expect($filter->fresh()->name)->toBe('New Name');
    expect($filter->fresh()->action)->toBe('warn');
});

it('moderator can delete a filter chain', function () {
    $filter = FilterChain::create([
        'name' => 'Delete Me',
        'type' => 'contains',
        'pattern' => 'test',
        'action' => 'block',
        'is_active' => true,
        'priority' => 0,
        'created_by' => null,
    ]);

    $this->actingAs(createModerator())
        ->deleteJson("/chat/filters/{$filter->id}")
        ->assertOk();

    expect(FilterChain::find($filter->id))->toBeNull();
});

it('regular user cannot manage filter chains', function () {
    $user = User::factory()->create();
    $filter = FilterChain::create([
        'name' => 'Filter',
        'type' => 'contains',
        'pattern' => 'test',
        'action' => 'block',
        'is_active' => true,
        'priority' => 0,
        'created_by' => null,
    ]);

    $this->actingAs($user)->postJson('/chat/filters', [
        'name' => 'New', 'type' => 'contains', 'pattern' => 'x', 'action' => 'block', 'is_active' => true, 'priority' => 0,
    ])->assertForbidden();

    $this->actingAs($user)->deleteJson("/chat/filters/{$filter->id}")->assertForbidden();
});

// ─── Slow mode ────────────────────────────────────────────────────────────────

it('moderator can enable slow mode', function () {
    $this->actingAs(createModerator())
        ->putJson('/chat/slow-mode', ['enabled' => true, 'seconds' => 15])
        ->assertOk()
        ->assertJsonFragment(['enabled' => true, 'seconds' => 15]);

    expect(ChatSetting::isSlowModeEnabled())->toBeTrue();
    expect(ChatSetting::slowModeSeconds())->toBe(15);
});

it('moderator can disable slow mode', function () {
    ChatSetting::setValue('slow_mode_enabled', '1');

    $this->actingAs(createModerator())
        ->putJson('/chat/slow-mode', ['enabled' => false, 'seconds' => 10])
        ->assertOk()
        ->assertJsonFragment(['enabled' => false]);

    expect(ChatSetting::isSlowModeEnabled())->toBeFalse();
});

it('regular user cannot change slow mode', function () {
    $this->actingAs(User::factory()->create())
        ->putJson('/chat/slow-mode', ['enabled' => true, 'seconds' => 5])
        ->assertForbidden();
});

it('slow mode prevents rapid message sending', function () {
    ChatSetting::setValue('slow_mode_enabled', '1');
    ChatSetting::setValue('slow_mode_seconds', '60');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/messages', ['body' => 'First message'])
        ->assertSuccessful();

    $this->actingAs($user)
        ->postJson('/messages', ['body' => 'Second message too fast'])
        ->assertStatus(429);
});

// ─── User list endpoint ───────────────────────────────────────────────────────

it('moderator can list users', function () {
    User::factory()->count(3)->create();

    $this->actingAs(createModerator())
        ->getJson('/chat/moderation/users')
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'last_page']);
});

it('moderator can search users by name', function () {
    User::factory()->create(['name' => 'Alice Smith']);
    User::factory()->create(['name' => 'Bob Jones']);

    $this->actingAs(createModerator())
        ->getJson('/chat/moderation/users?search=Alice')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Alice Smith'])
        ->assertJsonMissing(['name' => 'Bob Jones']);
});

it('moderator can filter to blocked users only', function () {
    User::factory()->create(['name' => 'Blocked Bob', 'is_blocked' => true]);
    User::factory()->create(['name' => 'Clean Carol', 'is_blocked' => false]);

    $this->actingAs(createModerator())
        ->getJson('/chat/moderation/users?blocked_only=1')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Blocked Bob'])
        ->assertJsonMissing(['name' => 'Clean Carol']);
});
