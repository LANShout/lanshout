<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => $this->faker->realText(80),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
