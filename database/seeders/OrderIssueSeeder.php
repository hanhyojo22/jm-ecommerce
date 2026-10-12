<?php

namespace Database\Seeders;

use App\Models\OrderIssue;
use App\Models\SellerOrder;
use Illuminate\Database\Seeder;

class OrderIssueSeeder extends Seeder
{
    public function run(): void
    {
        $sellerOrder = SellerOrder::query()->with('order')->first();

        if ($sellerOrder !== null && ! $sellerOrder->issues()->exists()) {
            OrderIssue::factory()->forSellerOrder($sellerOrder)->create();
        }
    }
}
