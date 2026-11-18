<?php

namespace Database\Factories;

use App\Models\DiscountCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCard>
 */
class DiscountCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->numerify('SC-####-####');

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['senior', 'pwd']),
            'id_number' => $number,
            'name' => fake()->name(),
            'last4' => mb_substr($number, -4),
        ];
    }
}
