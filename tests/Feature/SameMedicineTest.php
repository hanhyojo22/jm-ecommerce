<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SameMedicineTest extends TestCase
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

    public function test_every_same_generic_strength_and_form_in_stock_at_the_branch_is_listed_by_price(): void
    {
        $biogesic = $this->medicine('Biogesic 500mg', '8.00');
        $this->medicine('RiteMed Paracetamol 500mg', '3.50', generic: 'PARACETAMOL', strength: '500MG');
        $this->medicine('Generika Paracetamol 500mg', '5.00');
        $tempra = $this->medicine('Tempra Paracetamol 500mg', '9.00');
        $tempra->update(['requires_prescription' => true]);
        $this->medicine('Paracetamol 250mg', '2.00', strength: '250 mg');
        $this->medicine('Paracetamol syrup', '2.00', form: 'Syrup');
        $this->medicine('Out of stock paracetamol', '1.00', stock: 0);

        $this->getJson(route('api.v1.products.show', [$this->branch->slug, $biogesic->slug, 'branch' => $this->branch->id]))
            ->assertOk()
            ->assertJsonCount(3, 'data.same_medicine')
            ->assertJsonPath('data.same_medicine.0.name', 'RiteMed Paracetamol 500mg')
            ->assertJsonPath('data.same_medicine.1.name', 'Generika Paracetamol 500mg')
            ->assertJsonPath('data.same_medicine.2.name', 'Tempra Paracetamol 500mg')
            ->assertJsonPath('data.same_medicine.2.requires_prescription', true);

        $this->get(route('catalog.show', [$this->branch->slug, $biogesic->slug]))
            ->assertOk()
            ->assertSeeInOrder(['Other brands of this medicine', 'RiteMed Paracetamol 500mg', '₱3.50', 'Generika Paracetamol 500mg', 'Tempra Paracetamol 500mg', 'Rx', '₱9.00']);
    }

    public function test_one_switch_with_care_brand_warns_on_every_brand_of_that_medicine(): void
    {
        $eltroxin = $this->medicine('Eltroxin 50mcg', '12.00', generic: 'Levothyroxine', strength: '50 mcg');
        $generic = $this->medicine('Levothyroxine 50mcg', '6.00', generic: 'Levothyroxine', strength: '50 mcg');
        $paracetamol = $this->medicine('Biogesic 500mg', '8.00');
        $this->medicine('Generika Paracetamol 500mg', '5.00');
        $eltroxin->update(['switch_with_care' => true]);
        $warning = 'Switching brands of this medicine needs care.';

        // Flagged on the brand alone, the generic's page still warns: the group is one medicine.
        foreach ([$eltroxin, $generic] as $product) {
            $this->getJson(route('api.v1.products.show', [$this->branch->slug, $product->slug, 'branch' => $this->branch->id]))
                ->assertOk()
                ->assertJsonPath('data.switch_with_care', true)
                ->assertJsonCount(1, 'data.same_medicine');
            $this->get(route('catalog.show', [$this->branch->slug, $product->slug]))->assertOk()->assertSee($warning);
        }

        $this->getJson(route('api.v1.products.show', [$this->branch->slug, $paracetamol->slug, 'branch' => $this->branch->id]))
            ->assertJsonPath('data.switch_with_care', false);
        $this->get(route('catalog.show', [$this->branch->slug, $paracetamol->slug]))->assertDontSee($warning);
    }

    public function test_a_product_missing_its_generic_name_strength_or_form_gets_no_matches(): void
    {
        $vitamins = $this->medicine('Multivitamins', '8.00', generic: null);
        $this->medicine('Cheaper multivitamins', '3.00', generic: null);
        $noStrength = $this->medicine('Paracetamol caplet', '6.00', strength: '');
        $this->medicine('Paracetamol 500mg', '4.00');

        foreach ([$vitamins, $noStrength] as $product) {
            $this->getJson(route('api.v1.products.show', [$this->branch->slug, $product->slug]))
                ->assertOk()
                ->assertJsonCount(0, 'data.same_medicine');
            $this->get(route('catalog.show', [$this->branch->slug, $product->slug]))->assertDontSee('Other brands of this medicine');
        }
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
