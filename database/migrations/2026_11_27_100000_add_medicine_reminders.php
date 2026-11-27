<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Medicine reminders: label directions on products (a pharmacist may override them per order
     * item), the buyer's opt-in, their plans and dose logs, family profiles and caregiver alerts.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('medicine_kind', 20)->default('none');
            $table->decimal('pack_units', 8, 2)->nullable();
            $table->decimal('dose_quantity', 6, 2)->nullable();
            $table->string('dose_unit', 20)->nullable();
            $table->unsignedTinyInteger('doses_per_day')->nullable();
            $table->unsignedSmallInteger('course_days')->nullable();
            $table->unsignedTinyInteger('min_hours_between')->nullable();
            $table->unsignedTinyInteger('max_doses_per_day')->nullable();
            $table->string('directions_note', 200)->nullable();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('medication_directions')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('medication_reminders_consent_at')->nullable();
        });

        Schema::create('care_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('relation', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('medication_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('care_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 160);
            $table->string('medicine_kind', 20);
            $table->decimal('dose_quantity', 6, 2);
            $table->string('dose_unit', 20);
            $table->json('dose_times');
            $table->unsignedTinyInteger('min_hours_between')->nullable();
            $table->unsignedTinyInteger('max_doses_per_day')->nullable();
            $table->string('directions_note', 200)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('units_anchor', 10, 2);
            $table->timestamp('anchored_at');
            $table->date('expires_on')->nullable();
            $table->boolean('show_name_on_lock_screen')->default(false);
            $table->string('status', 12)->default('active');
            $table->timestamp('last_count_prompt_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('medication_plan_order_items', function (Blueprint $table): void {
            $table->foreignUlid('medication_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('units', 10, 2);
        });

        Schema::create('medication_dose_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('medication_plan_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('status', 8);
            $table->decimal('quantity', 6, 2);
            $table->timestamp('logged_at');
            $table->uuid('client_uuid')->unique();
            $table->timestamps();
            $table->index(['medication_plan_id', 'logged_at']);
        });

        Schema::create('caregiver_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('caregiver_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('invited_email');
            $table->string('status', 10)->default('pending');
            $table->unsignedSmallInteger('notify_after_minutes')->default(60);
            $table->boolean('share_medicine_names')->default(false);
            $table->timestamps();
            $table->unique(['patient_user_id', 'invited_email']);
        });

        Schema::create('caregiver_alerts', function (Blueprint $table): void {
            $table->foreignUlid('caregiver_link_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('medication_plan_id')->constrained()->cascadeOnDelete();
            $table->timestamp('slot_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['caregiver_link_id', 'medication_plan_id', 'slot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_alerts');
        Schema::dropIfExists('caregiver_links');
        Schema::dropIfExists('medication_dose_logs');
        Schema::dropIfExists('medication_plan_order_items');
        Schema::dropIfExists('medication_plans');
        Schema::dropIfExists('care_profiles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('medication_reminders_consent_at'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('medication_directions'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'medicine_kind', 'pack_units', 'dose_quantity', 'dose_unit', 'doses_per_day', 'course_days', 'min_hours_between', 'max_doses_per_day', 'directions_note',
        ]));
    }
};
