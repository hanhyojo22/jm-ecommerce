<?php

namespace Database\Factories;

use App\Models\SellerEarning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerEarning>
 */
class SellerEarningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gross_amount' => '100.00',
            'commission_amount' => '0.00',
            'net_amount' => '100.00',
            'status' => 'available',
            'available_at' => now(),
        ];
    }
}
