<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists all users in a table', function () {
    User::factory()->create(['name' => 'Alice']);
    User::factory()->create(['name' => 'Bob']);

    $this->artisan('users:list')
        ->expectsOutputToContain('Alice')
        ->expectsOutputToContain('Bob')
        ->expectsOutputToContain('Total: 2 user(s)')
        ->assertSuccessful();
});

it('shows no users message when empty', function () {
    $this->artisan('users:list')
        ->expectsOutputToContain('No users found.')
        ->assertSuccessful();
});

it('filters by role', function () {
    $moderator = Role::create(['name' => 'moderator', 'display_name' => 'Moderator']);

    $alice = User::factory()->create(['name' => 'Alice']);
    $alice->roles()->attach($moderator);

    User::factory()->create(['name' => 'Bob']);

    $this->artisan('users:list --role=moderator')
        ->expectsOutputToContain('Alice')
        ->doesntExpectOutputToContain('Bob')
        ->expectsOutputToContain('Total: 1 user(s)')
        ->assertSuccessful();
});

it('filters to LanCore users only', function () {
    User::factory()->lancore()->create(['name' => 'LCUser']);
    User::factory()->create(['name' => 'LocalUser']);

    $this->artisan('users:list --lancore')
        ->expectsOutputToContain('LCUser')
        ->doesntExpectOutputToContain('LocalUser')
        ->assertSuccessful();
});

it('filters to local users only', function () {
    User::factory()->lancore()->create(['name' => 'LCUser']);
    User::factory()->create(['name' => 'LocalUser']);

    $this->artisan('users:list --local')
        ->expectsOutputToContain('LocalUser')
        ->doesntExpectOutputToContain('LCUser')
        ->assertSuccessful();
});

it('displays roles for users', function () {
    $admin = Role::create(['name' => 'admin', 'display_name' => 'Admin']);

    $user = User::factory()->create(['name' => 'AdminUser']);
    $user->roles()->attach($admin);

    $this->artisan('users:list')
        ->expectsOutputToContain('admin')
        ->assertSuccessful();
});
