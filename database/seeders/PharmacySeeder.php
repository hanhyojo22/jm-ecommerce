<?php

namespace Database\Seeders;

use App\Actions\BranchInventory;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sample data for a pharmacy test server: four open branches (three in Metro Manila, one in Cebu City) with map pins, and
 * medicines with a different stock mix at each branch. Fills the company `pharmacy:setup` created,
 * or creates it. Safe to run again: it never resets stock that already exists.
 *
 * Every medicine has a sample FDA number and storage note. Amlodipine comes in two pack sizes and the
 * paracetamol syrup in two flavors, each received as a batch with a lot number and expiry date; an
 * insulin pen, a dangerous drug kept as a draft, and a thermometer (not a medicine) show the other
 * product types. The FDA numbers are made up for the test server.
 */
class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(['slug' => 'jmmart-pharmacy'], ['name' => 'Everyday Pharmacy', 'status' => 'active']);

        $branches = collect($this->branches())->map(fn (array $branch): Store => Store::query()->firstOrCreate(
            ['slug' => Str::slug($branch['name'])],
            [
                ...$branch,
                'tenant_id' => $tenant->id,
                'phone' => '+639171234567',
                'status' => 'active',
                'delivery_radius_km' => 8,
                'fulfillment_methods' => 'pickup,delivery',
            ],
        ))->values();

        $createdSlugs = [];
        foreach ($this->products() as $index => [$name, $generic, $form, $strength, $category, $price, $rx, $stockByBranch]) {
            $product = Product::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => Str::slug($name)],
                [
                    // Products belong to the company; store_id still names the first branch.
                    'store_id' => $branches[0]->id,
                    'category_id' => Category::query()->firstOrCreate(['slug' => Str::slug($category)], ['name' => $category])->id,
                    'name' => $name,
                    'generic_name' => $generic,
                    'dosage_form' => $form,
                    'strength' => $strength,
                    'manufacturer' => 'Sample Laboratories',
                    'drug_class' => $rx ? 'rx' : 'otc',
                    'fda_registration_number' => 'DR-XY'.(41001 + $index * 37),
                    'storage_instructions' => in_array($form, ['Syrup', 'Oral drops'], true) ? 'Store below 30°C. Use within 30 days of opening.' : 'Store below 30°C, away from moisture and sunlight.',
                    'sku' => 'RX-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'description' => "{$name}. Sample medicine for the Everyday Pharmacy test server.",
                    'price' => number_format($price, 2, '.', ''),
                    'brand' => 'Everyday Pharmacy',
                    'condition' => 'new',
                    'free_shipping' => false,
                    'stock_quantity' => 0,
                    'status' => 'active',
                ],
            );

            if ($product->wasRecentlyCreated) {
                $createdSlugs[] = $product->slug;
            }

            foreach ($stockByBranch as $branchIndex => $quantity) {
                BranchStock::query()->firstOrCreate(
                    ['store_id' => $branches[$branchIndex]->id, 'product_id' => $product->id, 'variant_key' => 'default'],
                    ['product_variant_id' => null, 'quantity' => $quantity],
                );
            }

            // Product stock is the company total across branches.
            $product->update(['stock_quantity' => BranchStock::query()->where('product_id', $product->id)->sum('quantity')]);
        }

        $this->seedPackSizesAndFlavors($branches, $createdSlugs);
        $this->seedOtherProductTypes($tenant, $branches);

        $this->call(PharmacyImageSeeder::class);
    }

    /**
     * Pack sizes with their own units per pack, and syrup flavors, on two medicines created just now.
     * An existing database keeps its products as they are, so its stock never moves.
     *
     * @param  Collection<int, Store>  $branches
     * @param  list<string>  $createdSlugs
     */
    private function seedPackSizesAndFlavors(Collection $branches, array $createdSlugs): void
    {
        $samples = [
            'amlodipine-5-mg-tablet' => [
                ['size' => '10s', 'sku' => 'RX-018-10S', 'pack_units' => 10, 'price' => '95.00', 'stock' => [0 => 40, 1 => 25]],
                ['size' => '30s', 'sku' => 'RX-018-30S', 'pack_units' => 30, 'price' => '270.00', 'stock' => [0 => 12, 2 => 10]],
            ],
            'paracetamol-120-mg5-ml-syrup' => [
                ['color' => 'Orange', 'sku' => 'RX-004-ORANGE', 'pack_units' => 60, 'price' => null, 'stock' => [0 => 18, 2 => 12]],
                ['color' => 'Grape', 'sku' => 'RX-004-GRAPE', 'pack_units' => 60, 'price' => null, 'stock' => [0 => 10]],
            ],
        ];

        foreach ($samples as $slug => $variants) {
            $product = Product::query()->where('slug', $slug)->first();
            if ($product === null || ! in_array($slug, $createdSlugs, true)) {
                continue;
            }
            foreach ($variants as $variant) {
                $saved = $product->variants()->create([...collect($variant)->except('stock')->all(), 'stock_quantity' => 0]);
                foreach ($variant['stock'] as $branchIndex => $quantity) {
                    $this->receive($branches[$branchIndex], $product, $saved, $quantity);
                }
            }
        }
    }

    /**
     * A prescription medicine kept cold, a dangerous drug that stays a draft, and something that is not a medicine.
     *
     * @param  Collection<int, Store>  $branches
     */
    private function seedOtherProductTypes(Tenant $tenant, Collection $branches): void
    {
        $samples = [
            ['name' => 'Insulin Glargine 100 IU/mL Pen', 'generic_name' => 'Insulin Glargine', 'dosage_form' => 'Prefilled pen', 'strength' => '100 IU/mL, 3 mL', 'category' => 'Prescription Medicines', 'drug_class' => 'rx', 'fda_registration_number' => 'BR-1187', 'storage_instructions' => 'Refrigerate at 2–8°C. Do not freeze. Once in use, keep below 30°C for up to 28 days.', 'price' => '1450.00', 'status' => 'active', 'stock' => [1 => 6, 3 => 4]],
            ['name' => 'Diazepam 5 mg Tablet', 'generic_name' => 'Diazepam', 'dosage_form' => 'Tablet', 'strength' => '5 mg', 'category' => 'Prescription Medicines', 'drug_class' => 'dangerous', 'fda_registration_number' => 'DR-XY20977', 'storage_instructions' => 'Store below 30°C in a locked cabinet.', 'price' => '12.00', 'status' => 'draft', 'stock' => []],
            ['name' => 'Digital Thermometer', 'generic_name' => null, 'dosage_form' => null, 'strength' => null, 'category' => 'First Aid', 'drug_class' => null, 'fda_registration_number' => null, 'storage_instructions' => null, 'price' => '180.00', 'status' => 'active', 'stock' => [0 => 20, 2 => 15]],
        ];

        foreach ($samples as $index => $sample) {
            $product = Product::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => Str::slug($sample['name'])],
                [
                    ...collect($sample)->except(['category', 'stock'])->all(),
                    'store_id' => $branches[0]->id,
                    'category_id' => Category::query()->firstOrCreate(['slug' => Str::slug($sample['category'])], ['name' => $sample['category']])->id,
                    'manufacturer' => 'Sample Laboratories',
                    'sku' => 'SAMPLE-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'description' => $sample['drug_class'] === 'dangerous'
                        ? "{$sample['name']}. Sample dangerous drug (S2): saved for records, never sold online."
                        : "{$sample['name']}. Sample product for the Everyday Pharmacy test server.",
                    'brand' => 'Everyday Pharmacy',
                    'free_shipping' => false,
                    'stock_quantity' => 0,
                ],
            );
            if (! $product->wasRecentlyCreated) {
                continue;
            }
            foreach ($sample['stock'] as $branchIndex => $quantity) {
                $this->receive($branches[$branchIndex], $product, null, $quantity);
            }
        }
    }

    /**
     * Stock arrives the way a branch receives it: a batch with a lot number and an expiry date.
     */
    private function receive(Store $branch, Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $batch = InventoryBatch::query()->create([
            'store_id' => $branch->id, 'product_id' => $product->id, 'product_variant_id' => $variant?->id,
            'variant_key' => $variant?->id ?? 'default', 'lot_number' => 'SAMPLE-'.Str::upper(Str::random(6)),
            'expires_at' => now()->addMonths(18)->toDateString(), 'received_quantity' => $quantity,
            'remaining_quantity' => $quantity, 'status' => 'active', 'supplier' => 'Sample supplier',
        ]);
        InventoryMovement::query()->create([
            'inventory_batch_id' => $batch->id, 'store_id' => $branch->id, 'product_id' => $product->id,
            'type' => 'received', 'reason' => 'supplier_delivery', 'quantity_change' => $quantity,
            'quantity_before' => 0, 'quantity_after' => $quantity,
        ]);
        app(BranchInventory::class)->syncBranchStock($branch->id, $product, $variant);
        app(BranchInventory::class)->syncTotals($product);
    }

    /** @return list<array{name: string, address_line: string, city: string, barangay: string, latitude: float, longitude: float}> */
    private function branches(): array
    {
        return [
            ['name' => 'Everyday Pharmacy Ermita', 'address_line' => '1200 Taft Avenue', 'city' => 'Manila', 'barangay' => 'Ermita', 'latitude' => 14.5826, 'longitude' => 120.9845],
            ['name' => 'Everyday Pharmacy Makati', 'address_line' => '6750 Ayala Avenue', 'city' => 'Makati', 'barangay' => 'San Lorenzo', 'latitude' => 14.5547, 'longitude' => 121.0244],
            ['name' => 'Everyday Pharmacy Quezon City', 'address_line' => '88 Timog Avenue', 'city' => 'Quezon City', 'barangay' => 'Sacred Heart', 'latitude' => 14.6345, 'longitude' => 121.0353],
            // Beside the Cebu Provincial Capitol, at the north end of Osmena Boulevard.
            ['name' => 'Everyday Pharmacy Cebu Capitol', 'address_line' => 'Osmena Boulevard', 'city' => 'Cebu City', 'barangay' => 'Capitol Site', 'latitude' => 10.3160, 'longitude' => 123.8912],
        ];
    }

    /**
     * Name, generic name, dosage form, strength, category, price, needs a prescription, stock per branch
     * (index 0 Ermita, 1 Makati, 2 Quezon City, 3 Cebu Capitol; a missing index means that branch does not carry it).
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: float, 6: bool, 7: array<int, int>}>
     */
    private function products(): array
    {
        return [
            ['Paracetamol 500 mg Tablet', 'Paracetamol', 'Tablet', '500 mg', 'Pain & Fever', 4.50, false, [0 => 200, 1 => 150, 2 => 180, 3 => 160]],
            ['Ibuprofen 200 mg Tablet', 'Ibuprofen', 'Tablet', '200 mg', 'Pain & Fever', 6.00, false, [0 => 120, 1 => 90]],
            ['Mefenamic Acid 500 mg Capsule', 'Mefenamic Acid', 'Capsule', '500 mg', 'Pain & Fever', 8.50, false, [1 => 80, 2 => 60, 3 => 70]],
            ['Paracetamol 120 mg/5 mL Syrup', 'Paracetamol', 'Syrup', '120 mg/5 mL, 60 mL', 'Pain & Fever', 85.00, false, []],
            ['Cetirizine 10 mg Tablet', 'Cetirizine', 'Tablet', '10 mg', 'Cough & Colds', 12.00, false, [0 => 100, 1 => 100, 2 => 100, 3 => 80]],
            ['Loratadine 10 mg Tablet', 'Loratadine', 'Tablet', '10 mg', 'Cough & Colds', 14.00, false, [2 => 70]],
            ['Carbocisteine 500 mg Capsule', 'Carbocisteine', 'Capsule', '500 mg', 'Cough & Colds', 9.50, false, [0 => 90, 1 => 40]],
            ['Phenylephrine + Paracetamol Tablet', 'Phenylephrine HCl + Paracetamol', 'Tablet', '10 mg/500 mg', 'Cough & Colds', 7.50, false, [1 => 150, 2 => 120]],
            ['Ascorbic Acid 500 mg Tablet', 'Ascorbic Acid (Vitamin C)', 'Tablet', '500 mg', 'Vitamins & Supplements', 5.00, false, [0 => 300, 1 => 250, 2 => 200, 3 => 220]],
            ['Multivitamins + Iron Capsule', 'Multivitamins + Iron', 'Capsule', '1 capsule daily', 'Vitamins & Supplements', 11.00, false, [0 => 120]],
            ['Zinc Sulfate 20 mg/mL Drops', 'Zinc Sulfate', 'Oral drops', '20 mg/mL, 15 mL', 'Vitamins & Supplements', 145.00, false, [1 => 20, 2 => 15]],
            ['Oral Rehydration Salts Sachet', 'Oral Rehydration Salts', 'Powder for solution', '20.5 g sachet', 'First Aid', 18.00, false, [0 => 80, 1 => 60, 2 => 70, 3 => 50]],
            ['Isopropyl Alcohol 70% 500 mL', 'Isopropyl Alcohol', 'Solution', '70%, 500 mL', 'First Aid', 95.00, false, [0 => 40, 1 => 35, 2 => 50, 3 => 30]],
            ['Povidone-Iodine 10% Solution', 'Povidone-Iodine', 'Solution', '10%, 60 mL', 'First Aid', 120.00, false, [2 => 25]],
            ['Amoxicillin 500 mg Capsule', 'Amoxicillin', 'Capsule', '500 mg', 'Prescription Medicines', 15.00, true, [0 => 150, 1 => 100, 3 => 90]],
            ['Losartan 50 mg Tablet', 'Losartan Potassium', 'Tablet', '50 mg', 'Prescription Medicines', 18.00, true, [0 => 200, 2 => 140, 3 => 110]],
            ['Metformin 500 mg Tablet', 'Metformin HCl', 'Tablet', '500 mg', 'Prescription Medicines', 6.50, true, [1 => 180, 2 => 160, 3 => 100]],
            ['Amlodipine 5 mg Tablet', 'Amlodipine Besilate', 'Tablet', '5 mg', 'Prescription Medicines', 10.00, true, []],
            ['Salbutamol 2 mg/5 mL Syrup', 'Salbutamol', 'Syrup', '2 mg/5 mL, 60 mL', 'Prescription Medicines', 110.00, true, [1 => 20]],
            ['Cefalexin 500 mg Capsule', 'Cefalexin', 'Capsule', '500 mg', 'Prescription Medicines', 22.00, true, [2 => 60]],
        ];
    }
}
