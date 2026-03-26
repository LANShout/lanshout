<?php

use App\Services\LanCore\LanCoreUser;

it('creates a LanCoreUser from a valid array', function () {
    $data = [
        'id' => 42,
        'username' => 'mkohn',
        'display_name' => 'Matt Kohn',
        'email' => 'matt@example.com',
        'avatar_url' => 'https://lancore.test/avatars/42.jpg',
        'locale' => 'en',
    ];

    $user = LanCoreUser::fromArray($data);

    expect($user->id)->toBe(42)
        ->and($user->username)->toBe('mkohn')
        ->and($user->displayName)->toBe('Matt Kohn')
        ->and($user->email)->toBe('matt@example.com')
        ->and($user->avatarUrl)->toBe('https://lancore.test/avatars/42.jpg')
        ->and($user->locale)->toBe('en')
        ->and($user->isValid())->toBeTrue();
});

it('falls back to username when display_name is missing', function () {
    $user = LanCoreUser::fromArray([
        'id' => 1,
        'username' => 'jane',
        'email' => 'jane@example.com',
    ]);

    expect($user->displayName)->toBe('jane');
});

it('reports invalid when id is zero', function () {
    $user = LanCoreUser::fromArray([
        'id' => 0,
        'username' => 'ghost',
        'email' => 'ghost@example.com',
    ]);

    expect($user->isValid())->toBeFalse();
});

it('reports invalid when username is empty', function () {
    $user = LanCoreUser::fromArray([
        'id' => 1,
        'username' => '',
        'email' => 'empty@example.com',
    ]);

    expect($user->isValid())->toBeFalse();
});

it('reports invalid when email is empty', function () {
    $user = LanCoreUser::fromArray([
        'id' => 1,
        'username' => 'noemail',
        'email' => '',
    ]);

    expect($user->isValid())->toBeFalse();
});

it('handles nullable optional fields gracefully', function () {
    $user = LanCoreUser::fromArray([
        'id' => 5,
        'username' => 'minimal',
        'email' => 'min@example.com',
    ]);

    expect($user->avatarUrl)->toBeNull()
        ->and($user->locale)->toBeNull()
        ->and($user->isValid())->toBeTrue();
});
