<?php

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

// Clear any webhook secret configured in .env so tests run without signature
// verification unless they explicitly set one via Config::set.
beforeEach(fn () => Config::set('lancore.webhook_secret', null));

$validPayload = [
    'event' => 'announcement.published',
    'announcement' => [
        'id' => 42,
        'title' => 'Tournament starting in 10 minutes!',
        'priority' => 'normal',
        'published_at' => '2026-03-26T18:30:00+00:00',
    ],
];

it('stores an announcement message and dispatches MessageSent', function () use ($validPayload) {
    Event::fake([MessageSent::class]);

    $this->postJson('/api/announcements', $validPayload)
        ->assertNoContent();

    $this->assertDatabaseHas('messages', [
        'body' => 'Tournament starting in 10 minutes!',
        'type' => 'announcement',
        'priority' => 'normal',
        'user_id' => null,
    ]);

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event->message->body === 'Tournament starting in 10 minutes!'
            && $event->message->type === 'announcement'
            && $event->message->priority === 'normal';
    });
});

it('accepts all valid priorities', function (string $priority) use ($validPayload) {
    Event::fake([MessageSent::class]);

    $payload = array_merge($validPayload, [
        'announcement' => array_merge($validPayload['announcement'], ['priority' => $priority]),
    ]);

    $this->postJson('/api/announcements', $payload)
        ->assertNoContent();

    $this->assertDatabaseHas('messages', ['priority' => $priority, 'type' => 'announcement']);
})->with(['silent', 'normal', 'emergency']);

it('rejects an invalid event type', function () use ($validPayload) {
    $payload = array_merge($validPayload, ['event' => 'something.else']);

    $this->postJson('/api/announcements', $payload)
        ->assertUnprocessable();
});

it('rejects an invalid priority', function () use ($validPayload) {
    $payload = array_merge($validPayload, [
        'announcement' => array_merge($validPayload['announcement'], ['priority' => 'critical']),
    ]);

    $this->postJson('/api/announcements', $payload)
        ->assertUnprocessable();
});

it('rejects a missing title', function () use ($validPayload) {
    $announcement = $validPayload['announcement'];
    unset($announcement['title']);

    $this->postJson('/api/announcements', array_merge($validPayload, ['announcement' => $announcement]))
        ->assertUnprocessable();
});

it('rejects a missing announcement object', function () {
    $this->postJson('/api/announcements', ['event' => 'announcement.published'])
        ->assertUnprocessable();
});

it('passes through when no webhook secret is configured', function () use ($validPayload) {
    Event::fake([MessageSent::class]);
    Config::set('lancore.webhook_secret', null);

    $this->postJson('/api/announcements', $validPayload)
        ->assertNoContent();
});

it('accepts a request with a valid signature', function () use ($validPayload) {
    Event::fake([MessageSent::class]);
    $secret = 'test-secret';
    Config::set('lancore.webhook_secret', $secret);

    $body = json_encode($validPayload);
    $signature = hash_hmac('sha256', $body, $secret);

    $this->postJson('/api/announcements', $validPayload, ['X-Webhook-Signature' => $signature])
        ->assertNoContent();
});

it('rejects a request with an invalid signature', function () use ($validPayload) {
    Config::set('lancore.webhook_secret', 'correct-secret');

    $this->postJson('/api/announcements', $validPayload, ['X-Webhook-Signature' => 'wrong-signature'])
        ->assertUnauthorized();
});

it('rejects a request with a missing signature when secret is configured', function () use ($validPayload) {
    Config::set('lancore.webhook_secret', 'test-secret');

    $this->postJson('/api/announcements', $validPayload)
        ->assertUnauthorized();
});

it('includes type and priority in the MessageSent broadcast payload', function () {
    $message = Message::factory()->create([
        'user_id' => null,
        'body' => 'Test announcement',
        'type' => 'announcement',
        'priority' => 'emergency',
    ]);

    $event = new MessageSent($message);
    $data = $event->broadcastWith();

    expect($data['type'])->toBe('announcement');
    expect($data['priority'])->toBe('emergency');
});
