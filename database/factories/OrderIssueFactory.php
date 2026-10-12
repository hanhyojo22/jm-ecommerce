<?php

namespace Database\Factories;

use App\Models\OrderIssue;
use App\Models\SellerOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderIssue>
 */
class OrderIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'delivery',
            'details' => fake()->sentence(12),
            'status' => 'open',
        ];
    }

    public function forSellerOrder(SellerOrder $sellerOrder): static
    {
        return $this->state(fn (): array => [
            'seller_order_id' => $sellerOrder->id,
            'buyer_id' => $sellerOrder->order->user_id,
        ]);
    }
}
