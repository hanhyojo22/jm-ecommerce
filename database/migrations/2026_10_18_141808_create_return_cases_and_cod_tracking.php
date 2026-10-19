<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cod_collected_at')->nullable();
        });
        DB::table('seller_orders')->where('status', 'delivered')->update(['delivered_at' => DB::raw('updated_at')]);
        DB::table('seller_orders')->where('status', 'delivered')->whereIn('order_id', DB::table('payments')->where('method', 'cod')->where('status', 'paid')->select('order_id'))->update(['cod_collected_at' => DB::raw('updated_at')]);

        Schema::table('seller_earnings', fn (Blueprint $table) => $table->timestamp('voided_at')->nullable());
        DB::table('seller_earnings')->where('status', 'available')->whereNull('seller_payout_id')->whereIn('seller_order_id', DB::table('seller_orders')->whereIn('order_id', DB::table('payments')->where('method', 'cod')->select('order_id'))->select('id'))->update(['voided_at' => now()]);

        Schema::create('return_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('seller_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('order_issue_id')->nullable()->unique()->constrained('order_issues')->nullOnDelete();
            $table->string('status');
            $table->string('reason');
            $table->string('requested_remedy');
            $table->string('remedy')->nullable();
            $table->text('details');
            $table->text('response_note')->nullable();
            $table->boolean('return_waived')->default(false);
            $table->text('return_instructions')->nullable();
            $table->text('return_method')->nullable();
            $table->timestamp('respond_by')->nullable();
            $table->timestamp('buyer_respond_by')->nullable();
            $table->timestamp('fulfill_by')->nullable();
            $table->timestamp('handed_off_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['seller_order_id', 'status']);
            $table->index(['status', 'respond_by']);
        });
        Schema::create('return_case_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('return_case_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('restocked_quantity')->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->unique(['return_case_id', 'order_item_id']);
        });
        Schema::create('return_case_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('return_case_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->text('note')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at');
            $table->index(['return_case_id', 'created_at']);
        });
        Schema::create('return_case_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('return_case_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('kind');
            $table->string('path');
            $table->string('original_name');
            $table->timestamp('created_at');
        });
        Schema::create('return_case_outcomes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('return_case_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('kind');
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignUlid('proof_evidence_id')->nullable()->constrained('return_case_evidence')->nullOnDelete();
            $table->string('shipment_reference')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_case_outcomes');
        Schema::dropIfExists('return_case_evidence');
        Schema::dropIfExists('return_case_events');
        Schema::dropIfExists('return_case_items');
        Schema::dropIfExists('return_cases');
        Schema::table('seller_earnings', fn (Blueprint $table) => $table->dropColumn('voided_at'));
        Schema::table('seller_orders', fn (Blueprint $table) => $table->dropColumn(['delivered_at', 'cod_collected_at']));
    }
};
