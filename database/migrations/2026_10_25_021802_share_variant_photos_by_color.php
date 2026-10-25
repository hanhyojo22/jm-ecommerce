<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variant photos belong to a color, so every size of that color shares them.
     *
     * A photo on a size-only variant has no color to follow and becomes a shared product photo.
     */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->string('color', 60)->nullable();
            $table->index(['product_id', 'color']);
        });

        DB::table('product_images')->whereNotNull('product_variant_id')->orderBy('id')->each(function (object $image): void {
            $color = DB::table('product_variants')->where('id', $image->product_variant_id)->value('color');
            DB::table('product_images')->where('id', $image->id)->update(['color' => filled($color) ? $color : null]);
        });

        Schema::table('product_images', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_variant_id'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
        });

        DB::table('product_images')->whereNotNull('color')->orderBy('id')->each(function (object $image): void {
            $variantId = DB::table('product_variants')->where('product_id', $image->product_id)->where('color', $image->color)->orderBy('id')->value('id');
            DB::table('product_images')->where('id', $image->id)->update(['product_variant_id' => $variantId]);
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropIndex(['product_id', 'color']);
            $table->dropColumn('color');
        });
    }
};
