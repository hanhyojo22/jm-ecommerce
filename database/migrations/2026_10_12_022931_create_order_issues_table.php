<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_issues', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('seller_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 32);
            $table->text('details');
            $table->string('status', 20)->default('open');
            $table->text('resolution_note')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['seller_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_issues');
    }
};
