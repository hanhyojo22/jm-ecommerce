<?php

namespace Database\Factories;

use App\Models\SellerPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerPayout>
 */
class SellerPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'PO-'.str()->upper(str()->ulid()),
            'amount' => '100.00',
            'status' => 'recorded',
            'recorded_at' => now(),
        ];
    }
}
