<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer's wishlist: products saved to buy later. No quantity and no reserved price;
     * the cart holds those once the customer decides to buy.
     */
    public function up(): void
    {
        Schema::create('saved_products', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_products');
    }
};
