<?php

use App\Services\LanCore\LanCoreUser;

it('creates a LanCoreUser from a valid array', function () {
    $data = [
        'id' => 42,
        'username' => 'mkohn',
        'locale' => 'en',
        'avatar' => 'https://lancore.test/avatars/42.jpg',
        'created_at' => '2025-01-01T00:00:00Z',
        'email' => 'matt@example.com',
        'roles' => ['admin', 'user'],
    ];

    $user = LanCoreUser::fromArray($data);

    expect($user->id)->toBe(42)
        ->and($user->username)->toBe('mkohn')
        ->and($user->locale)->toBe('en')
        ->and($user->avatar)->toBe('https://lancore.test/avatars/42.jpg')
        ->and($user->createdAt)->toBe('2025-01-01T00:00:00Z')
        ->and($user->email)->toBe('matt@example.com')
        ->and($user->roles)->toBe(['admin', 'user'])
        ->and($user->isValid())->toBeTrue();
});

it('handles missing scoped fields gracefully', function () {
    $user = LanCoreUser::fromArray([
        'id' => 1,
        'username' => 'jane',
    ]);

    expect($user->email)->toBeNull()
        ->and($user->roles)->toBeNull()
        ->and($user->avatar)->toBeNull()
        ->and($user->locale)->toBeNull()
        ->and($user->isValid())->toBeTrue();
});

it('reports invalid when id is zero', function () {
    $user = LanCoreUser::fromArray([
        'id' => 0,
        'username' => 'ghost',
    ]);

    expect($user->isValid())->toBeFalse();
});

it('reports invalid when username is empty', function () {
    $user = LanCoreUser::fromArray([
        'id' => 1,
        'username' => '',
    ]);

    expect($user->isValid())->toBeFalse();
});

it('is valid without email when user:email scope is not granted', function () {
    $user = LanCoreUser::fromArray([
        'id' => 1,
        'username' => 'noemail',
        'locale' => 'de',
        'avatar' => null,
    ]);

    expect($user->isValid())->toBeTrue()
        ->and($user->email)->toBeNull();
});

it('preserves roles array from user:roles scope', function () {
    $user = LanCoreUser::fromArray([
        'id' => 5,
        'username' => 'roled',
        'roles' => ['moderator'],
    ]);

    expect($user->roles)->toBe(['moderator']);
});
