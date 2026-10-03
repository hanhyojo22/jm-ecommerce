<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'Electronics',
            'Fashion',
            'Health & Beauty',
            'Home & Living',
            'Food & Grocery',
            'Babies & Kids',
            'Sports & Outdoors',
            'Pet Supplies',
            'Automotive',
            'Toys & Collectibles',
            'Office & School',
            'Appliances',
            'Bags & Accessories',
            'Hobbies & Crafts',
            'Books & Media',
            'Jewelry & Watches',
            'Garden & DIY',
            'Travel & Luggage',
        ])->each(fn (string $name) => Category::firstOrCreate(['slug' => str($name)->slug()], ['name' => $name]));
    }
}
