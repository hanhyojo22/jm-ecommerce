<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PharmacyImageSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()
            ->whereHas('tenant', fn (Builder $query): Builder => $query->where('slug', 'jmmart-pharmacy'))
            ->where('manufacturer', 'Sample Laboratories')
            ->with('images')->get();

        foreach ($products as $product) {
            $slug = Str::slug($product->name);
            $source = __DIR__.'/images/pharmacy/'.$slug.'.webp';
            $path = 'products/catalog/pharmacy/'.$slug.'.webp';

            if (! is_file($source) || ($product->images->isNotEmpty() && ! $product->images->contains('path', $path))) {
                continue;
            }

            $disk = Storage::disk('product_images');
            if (! $disk->exists($path)) {
                if ($disk instanceof AwsS3V3Adapter) {
                    $disk->getClient()->putObject([
                        'Bucket' => $disk->getConfig()['bucket'],
                        'Key' => $path,
                        'SourceFile' => $source,
                        'ContentType' => 'image/webp',
                    ]);
                } else {
                    $disk->put($path, file_get_contents($source));
                }
            }

            $product->images()->firstOrCreate(['path' => $path], ['position' => 0]);
        }
    }
}
