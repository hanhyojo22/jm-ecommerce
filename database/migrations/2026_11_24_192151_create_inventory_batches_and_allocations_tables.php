<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('variant_key', 32)->default('default');
            $table->string('lot_number', 100);
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('received_quantity');
            $table->unsignedInteger('remaining_quantity')->default(0);
            $table->string('status', 20)->default('active');
            $table->string('supplier')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'product_id', 'variant_key', 'lot_number']);
            $table->index(['store_id', 'status', 'expires_at']);
        });

        Schema::create('inventory_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['inventory_batch_id', 'order_item_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('inventory_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('reason', 80);
            $table->integer('quantity_change');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'created_at']);
        });

        DB::table('branch_stocks')->orderBy('id')->get()->each(function (object $stock): void {
            DB::table('inventory_batches')->insert([
                'id' => (string) Str::ulid(),
                'store_id' => $stock->store_id,
                'product_id' => $stock->product_id,
                'product_variant_id' => $stock->product_variant_id,
                'variant_key' => $stock->variant_key,
                'lot_number' => 'OPENING-'.$stock->id,
                'received_quantity' => $stock->quantity,
                'remaining_quantity' => $stock->quantity,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_allocations');
        Schema::dropIfExists('inventory_batches');
    }
};
