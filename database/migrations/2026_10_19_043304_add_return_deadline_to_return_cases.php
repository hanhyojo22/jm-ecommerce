<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_cases', function (Blueprint $table): void {
            $table->timestamp('return_by')->nullable()->after('buyer_respond_by');
        });

        DB::table('return_cases')->where('status', 'awaiting_return')->whereNull('return_by')->update(['return_by' => DB::raw('updated_at')]);
        DB::table('return_cases')->where('status', 'awaiting_receipt')->whereNull('return_by')->update(['return_by' => DB::raw('handed_off_at')]);
    }

    public function down(): void
    {
        Schema::table('return_cases', fn (Blueprint $table) => $table->dropColumn('return_by'));
    }
};
