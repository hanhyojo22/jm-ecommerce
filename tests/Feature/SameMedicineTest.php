<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheaperAlternativeTest extends TestCase
{
    use RefreshDatabase;

    private Store $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active']);
        $this->branch = Store::create([
            'tenant_id' => $tenant->id, 'name' => 'Botika Plus Cebu', 'slug' => 'botika-plus-cebu',
            'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => 'Cebu', 'status' => 'active',
        ]);
    }

    public function test_only_cheaper_same_generic_strength_and_form_in_stock_at_the_branch_are_suggested(): void
    {
        $biogesic = $this->medicine('Biogesic 500mg', '8.00');
        $this->medicine('RiteMed Paracetamol 500mg', '3.50', generic: 'PARACETAMOL');
        $this->medicine('Generika Paracetamol 500mg', '5.00');
        $this->medicine('Tempra Paracetamol 500mg', '9.00');
        $this->medicine('Paracetamol 250mg', '2.00', strength: '250 mg');
        $this->medicine('Paracetamol syrup', '2.00', form: 'Syrup');
        $this->medicine('Out of stock paracetamol', '1.00', stock: 0);

        $this->getJson(route('api.v1.products.show', [$this->branch->slug, $biogesic->slug, 'branch' => $this->branch->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data.cheaper_alternatives')
            ->assertJsonPath('data.cheaper_alternatives.0.name', 'RiteMed Paracetamol 500mg')
            ->assertJsonPath('data.cheaper_alternatives.1.name', 'Generika Paracetamol 500mg');

        $this->get(route('catalog.show', [$this->branch->slug, $biogesic->slug]))
            ->assertOk()
            ->assertSee('Same medicine, lower price')
            ->assertSeeInOrder(['Same medicine, lower price', 'RiteMed Paracetamol 500mg', '₱4.50 less', 'Generika Paracetamol 500mg', '₱3.00 less']);
    }

    public function test_a_product_without_a_generic_name_gets_no_suggestions(): void
    {
        $vitamins = $this->medicine('Multivitamins', '8.00', generic: null);
        $this->medicine('Cheaper multivitamins', '3.00', generic: null);

        $this->getJson(route('api.v1.products.show', [$this->branch->slug, $vitamins->slug]))
            ->assertOk()
            ->assertJsonCount(0, 'data.cheaper_alternatives');
        $this->get(route('catalog.show', [$this->branch->slug, $vitamins->slug]))->assertDontSee('Same medicine, lower price');
    }

    private function medicine(string $name, string $price, ?string $generic = 'Paracetamol', string $strength = '500 mg', string $form = 'Tablet', int $stock = 5): Product
    {
        $product = Product::create([
            'tenant_id' => $this->branch->tenant_id, 'store_id' => $this->branch->id, 'name' => $name, 'slug' => str($name)->slug()->toString(),
            'sku' => str($name)->slug()->upper()->toString(), 'price' => $price, 'stock_quantity' => $stock, 'status' => 'active',
            'generic_name' => $generic, 'strength' => $strength, 'dosage_form' => $form,
        ]);
        BranchStock::create(['store_id' => $this->branch->id, 'product_id' => $product->id, 'variant_key' => 'default', 'quantity' => $stock]);

        return $product;
    }
}
