<?php

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches MessageSent event when a message is stored', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/messages', ['body' => 'Hello, world!'])
        ->assertSuccessful();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event->message->body === 'Hello, world!';
    });
});

it('broadcasts MessageSent on the chat channel', function () {
    $user = User::factory()->create();
    $message = Message::factory()->create(['user_id' => $user->id, 'body' => 'Test broadcast']);
    $message->load('user:id,name,chat_color');

    $event = new MessageSent($message);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('chat');
});

it('includes correct data in broadcast payload', function () {
    $user = User::factory()->create(['name' => 'Alice', 'chat_color' => '#ff0000']);
    $message = Message::factory()->create(['user_id' => $user->id, 'body' => 'Payload test']);
    $message->load('user:id,name,chat_color');

    $event = new MessageSent($message);
    $data = $event->broadcastWith();

    expect($data)->toHaveKeys(['id', 'body', 'created_at', 'user']);
    expect($data['body'])->toBe('Payload test');
    expect($data['user']['name'])->toBe('Alice');
    expect($data['user']['chat_color'])->toBe('#ff0000');
});

it('does not dispatch event when message validation fails', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/messages', ['body' => ''])
        ->assertUnprocessable();

    Event::assertNotDispatched(MessageSent::class);
});

it('includes null user in broadcast payload for system messages', function () {
    $message = Message::factory()->create(['user_id' => null, 'body' => 'System test']);
    $message->load('user:id,name,chat_color');

    $event = new MessageSent($message);
    $data = $event->broadcastWith();

    expect($data['body'])->toBe('System test');
    expect($data['user'])->toBeNull();
});
