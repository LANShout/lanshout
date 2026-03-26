<?php

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('sends a message via --message option', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();

    $this->artisan('chat:send', ['--user' => $user->id, '--message' => 'Hello from CLI'])
        ->assertSuccessful();

    expect(Message::where('body', 'Hello from CLI')->where('user_id', $user->id)->exists())->toBeTrue();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event->message->body === 'Hello from CLI';
    });
});

it('fails when no users exist and --user is invalid', function () {
    $this->artisan('chat:send', ['--user' => 999, '--message' => 'Hello'])
        ->assertFailed();
});

it('fails when no users exist at all', function () {
    $this->artisan('chat:send', ['--message' => 'Hello'])
        ->assertFailed();
});

it('sanitizes message body before storing', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();

    $this->artisan('chat:send', ['--user' => $user->id, '--message' => '<script>alert("xss")</script>Hello'])
        ->assertSuccessful();

    $message = Message::where('user_id', $user->id)->first();
    expect($message->body)->toBe('alert("xss")Hello');
});

it('selects only user automatically when one exists', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();

    $this->artisan('chat:send', ['--message' => 'Auto-selected user message'])
        ->assertSuccessful();

    expect(Message::where('user_id', $user->id)->where('body', 'Auto-selected user message')->exists())->toBeTrue();
});

it('displays recent messages in history', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    Message::factory()->count(3)->create(['user_id' => $user->id]);

    $this->artisan('chat:send', ['--user' => $user->id, '--message' => 'After history'])
        ->expectsOutputToContain('Recent Messages')
        ->assertSuccessful();
});

it('sends a system message with --system flag', function () {
    Event::fake([MessageSent::class]);

    $this->artisan('chat:send', ['--system' => true, '--message' => 'System announcement'])
        ->assertSuccessful();

    $message = Message::whereNull('user_id')->where('body', 'System announcement')->first();
    expect($message)->not->toBeNull();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event->message->body === 'System announcement' && $event->message->user_id === null;
    });
});

it('does not require users to exist when using --system', function () {
    Event::fake([MessageSent::class]);

    $this->artisan('chat:send', ['--system' => true, '--message' => 'No users needed'])
        ->assertSuccessful();

    expect(Message::whereNull('user_id')->count())->toBe(1);
});
