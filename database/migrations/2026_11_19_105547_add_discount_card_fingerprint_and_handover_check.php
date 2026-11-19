<?php

use App\Support\SeniorPwdDiscount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_card_fingerprint', 64)->nullable()->index();
        });
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->timestamp('discount_card_checked_at')->nullable();
            $table->foreignUlid('discount_card_checked_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Earlier discounted orders get a fingerprint too, so reuse across accounts shows from day one.
        DB::table('orders')->whereNotNull('discount_card_number')->select(['id', 'discount_card_type', 'discount_card_number'])->orderBy('id')
            ->each(function (object $order): void {
                try {
                    $number = Crypt::decryptString($order->discount_card_number);
                } catch (DecryptException) {
                    return;
                }
                DB::table('orders')->where('id', $order->id)->update(['discount_card_fingerprint' => SeniorPwdDiscount::fingerprint($order->discount_card_type, $number)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_card_checked_by');
            $table->dropColumn('discount_card_checked_at');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['discount_card_fingerprint']);
            $table->dropColumn('discount_card_fingerprint');
        });
    }
};
