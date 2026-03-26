<?php

use App\Models\User;
use Illuminate\Support\Carbon;

it('correctly identifies a LanCore user', function () {
    $user = User::factory()->lancore(42)->create();

    expect($user->isLanCoreUser())->toBeTrue();
});

it('correctly identifies a non-LanCore user', function () {
    $user = User::factory()->create();

    expect($user->isLanCoreUser())->toBeFalse();
});

it('includes lancore fields in fillable', function () {
    $user = new User;

    expect($user->getFillable())->toContain(
        'lancore_user_id',
        'display_name',
        'avatar_url',
        'lancore_synced_at',
    );
});

it('casts lancore_synced_at as datetime', function () {
    $user = User::factory()->lancore()->create();

    expect($user->lancore_synced_at)->toBeInstanceOf(Carbon::class);
});

it('allows null password for LanCore users', function () {
    $user = User::factory()->lancore()->create();

    expect($user->password)->toBeNull();
});
