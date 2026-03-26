<?php

namespace Database\Factories;

use App\Models\FilterChain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterChain>
 */
class FilterChainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['contains', 'regex', 'exact']),
            'pattern' => fake()->word(),
            'action' => fake()->randomElement(['block', 'replace', 'warn']),
            'replacement' => '***',
            'is_active' => true,
            'priority' => fake()->numberBetween(0, 100),
        ];
    }
}
