<?php

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('sends a message via --message option as system', function () {
    Event::fake([MessageSent::class]);

    $this->artisan('chat:send', ['--message' => 'Hello from CLI'])
        ->assertSuccessful();

    expect(Message::whereNull('user_id')->where('body', 'Hello from CLI')->exists())->toBeTrue();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event->message->body === 'Hello from CLI' && $event->message->user_id === null;
    });
});

it('sanitizes message body before storing', function () {
    Event::fake([MessageSent::class]);

    $this->artisan('chat:send', ['--message' => '<script>alert("xss")</script>Hello'])
        ->assertSuccessful();

    $message = Message::whereNull('user_id')->first();
    expect($message->body)->toBe('alert("xss")Hello');
});

it('sends without any users existing', function () {
    Event::fake([MessageSent::class]);

    $this->artisan('chat:send', ['--message' => 'No users needed'])
        ->assertSuccessful();

    expect(Message::whereNull('user_id')->count())->toBe(1);
});

it('displays recent messages in history', function () {
    Event::fake([MessageSent::class]);

    Message::factory()->count(3)->create(['user_id' => null]);

    $this->artisan('chat:send', ['--message' => 'After history'])
        ->expectsOutputToContain('Recent Messages')
        ->assertSuccessful();
});
