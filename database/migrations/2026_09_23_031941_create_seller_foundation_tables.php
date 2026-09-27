<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected', 'closed'])->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'manager', 'staff'])->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('stores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('phone', 30);
            $table->string('address_line');
            $table->string('city', 100);
            $table->enum('status', ['active', 'inactive', 'suspended'])->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('seller_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('tenant_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('store_name');
            $table->text('store_description')->nullable();
            $table->string('seller_name');
            $table->string('phone', 30);
            $table->string('address_line');
            $table->string('city', 100);
            $table->string('store_category')->nullable();
            $table->string('logo_path')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->ulid('entity_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('seller_applications');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('tenant_members');
        Schema::dropIfExists('tenants');
    }
};
