<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_marketplace_category_taxonomy(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(18, Category::query()->count());
        $this->assertDatabaseHas('categories', ['slug' => 'electronics', 'is_active' => true]);
        $this->assertDatabaseHas('categories', ['slug' => 'babies-kids', 'is_active' => true]);
        $this->assertDatabaseHas('categories', ['slug' => 'hobbies-crafts', 'is_active' => true]);
        $this->assertDatabaseHas('categories', ['slug' => 'travel-luggage', 'is_active' => true]);
    }
}
