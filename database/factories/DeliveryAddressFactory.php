<?php

namespace Database\Factories;

use App\Models\DeliveryAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAddress>
 */
class DeliveryAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipient_name' => fake()->name(),
            'phone' => '+639171234567',
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'province' => fake()->word(),
            'postal_code' => '1000',
        ];
    }
}
