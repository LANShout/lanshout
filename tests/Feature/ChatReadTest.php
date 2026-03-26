<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// Compute the Inertia asset version the same way the Inertia middleware does, so
// our test requests don't trigger a 409 version-mismatch redirect.
function inertiaVersion(): string
{
    return md5_file(public_path('build/manifest.json')) ?: '';
}

it('marks chat as read and updates last_chat_read_at', function () {
    $user = User::factory()->create(['last_chat_read_at' => null]);

    $this->actingAs($user)
        ->postJson('/chat/mark-read')
        ->assertNoContent();

    expect($user->fresh()->last_chat_read_at)->not->toBeNull();
});

it('updates last_chat_read_at on subsequent mark-read calls', function () {
    $user = User::factory()->create([
        'last_chat_read_at' => Carbon::now()->subHour(),
    ]);

    $before = $user->last_chat_read_at;

    $this->actingAs($user)
        ->postJson('/chat/mark-read')
        ->assertNoContent();

    expect($user->fresh()->last_chat_read_at->isAfter($before))->toBeTrue();
});

it('requires authentication for mark-read', function () {
    $this->postJson('/chat/mark-read')->assertUnauthorized();
});

it('chat page passes null lastReadAt and total count for new users', function () {
    $user = User::factory()->create(['last_chat_read_at' => null]);
    Message::factory()->count(3)->create();

    $response = $this->actingAs($user)
        ->get('/chat', ['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()]);

    $response->assertInertia(fn ($page) => $page
        ->component('Chat')
        ->where('lastReadAt', null)
        ->where('unreadCount', 3)
    );
});

it('chat page passes correct lastReadAt and unread count', function () {
    $user = User::factory()->create([
        'last_chat_read_at' => Carbon::now()->subHour(),
    ]);

    // 2 messages before lastReadAt (read)
    Message::factory()->count(2)->create([
        'created_at' => Carbon::now()->subHours(2),
    ]);

    // 3 messages after lastReadAt (unread)
    Message::factory()->count(3)->create([
        'created_at' => Carbon::now()->subMinutes(10),
    ]);

    $response = $this->actingAs($user)
        ->get('/chat', ['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()]);

    $response->assertInertia(fn ($page) => $page
        ->component('Chat')
        ->where('lastReadAt', $user->last_chat_read_at->toISOString())
        ->where('unreadCount', 3)
    );
});

it('chat page passes zero unread count when all messages are read', function () {
    $user = User::factory()->create([
        'last_chat_read_at' => Carbon::now(),
    ]);

    Message::factory()->count(5)->create([
        'created_at' => Carbon::now()->subHour(),
    ]);

    $response = $this->actingAs($user)
        ->get('/chat', ['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaVersion()]);

    $response->assertInertia(fn ($page) => $page
        ->component('Chat')
        ->where('unreadCount', 0)
    );
});

it('debug inertia response', function () {
    $user = User::factory()->create(['last_chat_read_at' => null]);
    $version = md5_file(public_path('build/manifest.json')) ?: '';
    $response = $this->actingAs($user)
        ->get('/chat', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version]);
    dump($response->getStatusCode(), substr($response->getContent(), 0, 500));
    $response->assertOk();
});
