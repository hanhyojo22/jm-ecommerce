<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreSuspensionAppeal;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<StoreSuspensionAppeal>
 */
class StoreSuspensionAppealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statement' => fake()->paragraph(),
            'status' => 'pending',
        ];
    }

    public function forStoreOwner(Store $store): static
    {
        $owner = $store->tenant->members()->where('role', 'owner')->first()?->user;

        if ($owner === null) {
            throw new LogicException('The store must have an owner before creating an appeal.');
        }

        return $this->for($store)->for($owner, 'seller');
    }
}
