<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'lancore.enabled' => true,
        'lancore.base_url' => 'https://lancore.test',
        'lancore.internal_url' => null,
        'lancore.token' => 'lci_test-integration-token',
        'lancore.retries' => 0,
    ]);

    Http::fake();
});

// --- Profile update ---

it('allows a LanCore user to update chat_color and locale', function () {
    $user = User::factory()->lancore()->create();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'chat_color' => '#ff0000',
        'locale' => 'de',
    ]);

    $response->assertRedirect(route('profile.edit'));
    expect($user->fresh()->chat_color)->toBe('#ff0000')
        ->and($user->fresh()->locale)->toBe('de');
});

it('does not update name or email for a LanCore user even if submitted', function () {
    $user = User::factory()->lancore()->create([
        'name' => 'original_name',
        'email' => 'original@example.com',
    ]);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'hacker',
        'email' => 'hacker@example.com',
        'chat_color' => '#00ff00',
        'locale' => 'en',
    ]);

    $user->refresh();
    expect($user->name)->toBe('original_name')
        ->and($user->email)->toBe('original@example.com')
        ->and($user->chat_color)->toBe('#00ff00');
});

it('allows a non-LanCore user to update their name and email', function () {
    $user = User::factory()->create(['name' => 'old name', 'email' => 'old@example.com']);

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'new name',
        'email' => 'new@example.com',
    ]);

    $response->assertRedirect(route('profile.edit'));
    $user->refresh();
    expect($user->name)->toBe('new name')
        ->and($user->email)->toBe('new@example.com');
});

// --- Account deletion ---

it('returns 403 when a LanCore user attempts to delete their account', function () {
    $user = User::factory()->lancore()->create();

    $response = $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('allows a non-LanCore user to delete their account', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ]);

    $response->assertRedirect('/');
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

// --- Password update ---

it('returns 403 when a LanCore user attempts to update their password', function () {
    $user = User::factory()->lancore()->create();

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertForbidden();
});

it('allows a non-LanCore user to update their password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-Password-123!',
        'password_confirmation' => 'new-Password-123!',
    ]);

    $response->assertRedirect();
});

// --- Password reset (Fortify forgot-password flow) ---

it('silently pretends to send a reset link when the email belongs to a LanCore user', function () {
    $user = User::factory()->lancore()->create(['email' => 'sso@example.com']);

    $response = $this->post(route('password.email'), ['email' => 'sso@example.com']);

    $response->assertRedirect();
    $response->assertSessionHas('status', __('passwords.sent'));

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'sso@example.com']);
});

it('sends a real reset link for non-LanCore users', function () {
    $user = User::factory()->create(['email' => 'local@example.com']);

    $response = $this->post(route('password.email'), ['email' => 'local@example.com']);

    $response->assertRedirect();
    $this->assertDatabaseHas('password_reset_tokens', ['email' => 'local@example.com']);
});

// --- Email verification resend ---

it('returns 403 when a LanCore user attempts to resend the verification email', function () {
    $user = User::factory()->lancore()->create();

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertForbidden();
});

it('allows a non-LanCore user to resend the verification email', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertRedirect();
});

// --- Fortify reset password action ---

it('throws a validation error when a LanCore user lands on the reset password form', function () {
    $user = User::factory()->lancore()->create(['email' => 'sso@example.com']);

    $token = app('auth.password')->createToken($user);

    $response = $this->post(route('password.update'), [
        'email' => 'sso@example.com',
        'token' => $token,
        'password' => 'new-Password-123!',
        'password_confirmation' => 'new-Password-123!',
    ]);

    $response->assertSessionHasErrors('email');
});
