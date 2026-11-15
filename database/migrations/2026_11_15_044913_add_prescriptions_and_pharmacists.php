<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order with a prescription medicine carries the customer's uploaded prescription, which a
     * pharmacist at the filling branch approves or rejects before the order moves on.
     */
    public function up(): void
    {
        Schema::table('tenant_members', function (Blueprint $table): void {
            $table->boolean('is_pharmacist')->default(false);
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('status', 20)->default('pending');
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');

        Schema::table('tenant_members', function (Blueprint $table): void {
            $table->dropColumn('is_pharmacist');
        });
    }
};
