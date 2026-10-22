<?php

namespace Database\Factories;

use App\Models\PlatformFeeStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformFeeStatement>
 */
class PlatformFeeStatementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'gross_total' => '1000.00',
            'commission_total' => '10.00',
            'amount_due' => '10.00',
            'status' => 'unpaid',
            'due_at' => now()->startOfMonth()->addDays(15),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => 'submitted',
            'payment_reference' => 'GCASH-123456',
            'payment_proof_path' => 'platform-fees/receipt.jpg',
            'payment_proof_name' => 'receipt.jpg',
            'payment_submitted_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => ['due_at' => now()->subDay()]);
    }
}
