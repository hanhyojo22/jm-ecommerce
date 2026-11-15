<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One pharmacy company, many branches: staff can belong to one branch, medicines carry
     * their pharmacy details, and each branch counts its own stock.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id']);
            $table->index('tenant_id');
        });

        Schema::table('tenant_members', function (Blueprint $table): void {
            // Null means head office: the member works across every branch. The pair key keeps
            // a member's branch inside their own company.
            $table->ulid('store_id')->nullable()->after('tenant_id');
            $table->foreign(['store_id', 'tenant_id'], 'tenant_members_store_id_tenant_id_foreign')
                ->references(['id', 'tenant_id'])
                ->on('stores')
                ->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('generic_name', 160)->nullable()->after('name');
            $table->string('dosage_form', 60)->nullable()->after('generic_name');
            $table->string('strength', 60)->nullable()->after('dosage_form');
            $table->string('manufacturer', 160)->nullable()->after('brand');
            $table->boolean('requires_prescription')->default(false)->after('manufacturer');
        });

        Schema::create('branch_stocks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            // The variant id, or 'default' for a product without variants, so the unique key holds (NULLs never collide).
            $table->string('variant_key', 32)->default('default');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'product_id', 'variant_key']);
            $table->index(['product_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_stocks');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['generic_name', 'dosage_form', 'strength', 'manufacturer', 'requires_prescription']);
        });

        Schema::table('tenant_members', function (Blueprint $table): void {
            $table->dropForeign('tenant_members_store_id_tenant_id_foreign');
            $table->dropColumn('store_id');
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id']);
            $table->unique('tenant_id');
        });
    }
};
