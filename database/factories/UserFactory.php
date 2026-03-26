<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'remember_token' => Str::random(10),
            'is_blocked' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user originates from LanCore.
     */
    public function lancore(?int $lanCoreUserId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'lancore_user_id' => $lanCoreUserId ?? fake()->unique()->numberBetween(1, 100000),
            'display_name' => fake()->name(),
            'avatar_url' => fake()->imageUrl(100, 100),
            'lancore_synced_at' => now(),
            'password' => null,
        ]);
    }
}
