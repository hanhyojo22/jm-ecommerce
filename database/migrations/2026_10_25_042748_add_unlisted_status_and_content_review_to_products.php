<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sellers unlist and relist their own listings without review, and a live listing stays live
     * while its edited content waits for an administrator to re-check it.
     */
    public function up(): void
    {
        $this->changeStatuses(['draft', 'pending', 'active', 'changes_required', 'rejected', 'inactive', 'unlisted']);
        Schema::table('products', function (Blueprint $table): void {
            $table->timestamp('content_changed_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('content_changed_at'));
        DB::table('products')->where('status', 'unlisted')->update(['status' => 'inactive']);
        $this->changeStatuses(['draft', 'pending', 'active', 'changes_required', 'rejected', 'inactive']);
    }

    /** @param array<int, string> $statuses */
    private function changeStatuses(array $statuses): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products DROP CONSTRAINT products_status_check');
            $values = implode(', ', array_map(fn (string $status): string => "'{$status}'", $statuses));
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ({$values}))");
        } else {
            Schema::table('products', fn (Blueprint $table) => $table->enum('status', $statuses)->default('draft')->change());
        }
    }
};
