<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSuspensionAppealSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::query()
            ->where('status', 'suspended')
            ->whereHas('tenant.members', fn ($query) => $query->where('role', 'owner'))
            ->whereDoesntHave('appeals', fn ($query) => $query->where('status', 'pending'))
            ->with('tenant.members.user')
            ->first();

        if ($store === null) {
            return;
        }

        $owner = $store->tenant->members->firstWhere('role', 'owner')?->user;

        if ($owner === null) {
            return;
        }

        $store->appeals()->create([
            'seller_id' => $owner->id,
            'statement' => 'The seller has reviewed the suspension and asks for another review.',
            'status' => 'pending',
        ]);
    }
}
