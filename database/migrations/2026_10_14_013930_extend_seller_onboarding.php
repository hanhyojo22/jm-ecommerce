<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE seller_applications DROP CONSTRAINT IF EXISTS seller_applications_status_check');
        }
        Schema::table('seller_applications', function (Blueprint $table): void {
            foreach (['store_name', 'seller_name', 'phone', 'address_line', 'city'] as $column) {
                $table->string($column)->nullable()->change();
            }
            $table->string('status')->default('draft')->change();
            $table->string('seller_type', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('business_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('bir_details')->nullable();
            $table->string('representative_name')->nullable();
            $table->string('store_slug')->nullable()->unique();
            $table->string('banner_path')->nullable();
            $table->string('support_contact')->nullable();
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('barangay')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('return_address')->nullable();
            $table->unsignedSmallInteger('processing_days')->nullable();
            $table->string('fulfillment_methods')->nullable();
            $table->text('delivery_areas')->nullable();
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();
            $table->json('completed_steps')->nullable();
            $table->json('correction_steps')->nullable();
            $table->index(['seller_type', 'submitted_at']);
        });
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('banner_path')->nullable();
        });

        Schema::create('seller_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('seller_application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->timestamps();
            $table->unique(['seller_application_id', 'type']);
        });
        Schema::create('payout_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('seller_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->string('provider');
            $table->string('holder_name');
            $table->text('account_number');
            $table->boolean('name_requires_review')->default(false);
            $table->timestamps();
        });
        Schema::create('policy_acceptances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('seller_application_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('policy_type', 40);
            $table->string('version', 30);
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('accepted_at');
            $table->unique(['seller_application_id', 'policy_type', 'version']);
        });
        Schema::create('seller_application_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('seller_application_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 40);
            $table->text('note')->nullable();
            $table->json('correction_steps')->nullable();
            $table->timestamps();
            $table->index(['seller_application_id', 'created_at']);
        });
        Schema::create('seller_application_status_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('seller_application_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['seller_application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_application_status_histories');
        Schema::dropIfExists('seller_application_reviews');
        Schema::dropIfExists('policy_acceptances');
        Schema::dropIfExists('payout_profiles');
        Schema::dropIfExists('seller_documents');
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('banner_path');
        });
        Schema::table('seller_applications', function (Blueprint $table): void {
            $table->dropColumn(['seller_type', 'date_of_birth', 'business_name', 'registration_number', 'bir_details', 'representative_name', 'store_slug', 'banner_path', 'support_contact', 'region', 'province', 'barangay', 'postal_code', 'pickup_address', 'return_address', 'processing_days', 'fulfillment_methods', 'delivery_areas', 'id_type', 'id_number', 'completed_steps', 'correction_steps']);
        });
    }
};
