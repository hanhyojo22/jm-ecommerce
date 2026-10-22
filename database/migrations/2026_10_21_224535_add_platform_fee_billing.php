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
        // Orders delivered before a store accepted the fee terms are never charged.
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('platform_fee_accepted_at')->nullable();
        });

        Schema::create('platform_fee_statements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->decimal('gross_total', 12, 2);
            $table->decimal('commission_total', 12, 2);
            $table->decimal('refund_credit', 12, 2)->default(0);
            $table->decimal('credit_carried_in', 12, 2)->default(0);
            $table->decimal('credit_carried_out', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2);
            $table->enum('status', ['unpaid', 'submitted', 'paid'])->default('unpaid');
            $table->timestamp('due_at');
            $table->string('payment_reference')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->string('payment_proof_name')->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            // One statement per store per month, so a second billing run is a no-op.
            $table->unique(['tenant_id', 'period_start']);
            $table->index(['status', 'due_at']);
        });

        // The rate is stored per earning so a later rate change never rewrites what was billed.
        Schema::table('seller_earnings', function (Blueprint $table): void {
            $table->decimal('commission_rate', 5, 4)->default(0);
            $table->foreignUlid('platform_fee_statement_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Dropping the statements discards billing history; prefer a forward fix in production.
     */
    public function down(): void
    {
        Schema::table('seller_earnings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('platform_fee_statement_id');
            $table->dropColumn('commission_rate');
        });

        Schema::dropIfExists('platform_fee_statements');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('platform_fee_accepted_at');
        });
    }
};
