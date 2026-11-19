<?php

namespace Database\Seeders;

use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample data for a pharmacy test server: four open branches (three in Metro Manila, one in Cebu City) with map pins, and
 * medicines with a different stock mix at each branch. Fills the company `pharmacy:setup` created,
 * or creates it. Safe to run again: it never resets stock that already exists.
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
                    'requires_prescription' => $rx,
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

            foreach ($stockByBranch as $branchIndex => $quantity) {
                BranchStock::query()->firstOrCreate(
                    ['store_id' => $branches[$branchIndex]->id, 'product_id' => $product->id, 'variant_key' => 'default'],
                    ['product_variant_id' => null, 'quantity' => $quantity],
                );
            }

            // Product stock is the company total across branches.
            $product->update(['stock_quantity' => BranchStock::query()->where('product_id', $product->id)->sum('quantity')]);
        }

        $this->call(PharmacyImageSeeder::class);
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
            ['Paracetamol 120 mg/5 mL Syrup', 'Paracetamol', 'Syrup', '120 mg/5 mL, 60 mL', 'Pain & Fever', 85.00, false, [0 => 30, 2 => 25]],
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
            ['Amlodipine 5 mg Tablet', 'Amlodipine Besilate', 'Tablet', '5 mg', 'Prescription Medicines', 10.00, true, [0 => 120, 1 => 120, 2 => 90]],
            ['Salbutamol 2 mg/5 mL Syrup', 'Salbutamol', 'Syrup', '2 mg/5 mL, 60 mL', 'Prescription Medicines', 110.00, true, [1 => 20]],
            ['Cefalexin 500 mg Capsule', 'Cefalexin', 'Capsule', '500 mg', 'Prescription Medicines', 22.00, true, [2 => 60]],
        ];
    }
}
