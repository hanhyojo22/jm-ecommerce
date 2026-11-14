<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Address and shipping details the seller gave at onboarding, which approval used to drop.
     *
     * @var list<string>
     */
    private const ONBOARDING_COLUMNS = ['region', 'province', 'barangay', 'postal_code', 'pickup_address', 'return_address', 'processing_days', 'fulfillment_methods', 'delivery_areas'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('region', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('return_address')->nullable();
            $table->unsignedSmallInteger('processing_days')->nullable();
            $table->string('fulfillment_methods', 120)->nullable();
            $table->text('delivery_areas')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('delivery_radius_km')->nullable();
        });

        Schema::table('seller_applications', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('delivery_radius_km')->nullable();
        });

        DB::table('seller_applications')
            ->where('status', 'approved')
            ->whereNotNull('tenant_id')
            ->orderBy('id')
            ->each(function (object $application): void {
                DB::table('stores')
                    ->where('tenant_id', $application->tenant_id)
                    ->update(collect(self::ONBOARDING_COLUMNS)
                        ->mapWithKeys(fn (string $column): array => [$column => $application->{$column}])
                        ->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_applications', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'delivery_radius_km']);
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn([...self::ONBOARDING_COLUMNS, 'latitude', 'longitude', 'delivery_radius_km']);
        });
    }
};
