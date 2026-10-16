<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->nullable();
        });
        Schema::table('product_images', function (Blueprint $table): void {
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('image_path')->nullable();
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->text('moderation_reason')->nullable();
        });
        $this->changeStatuses(['draft', 'pending', 'active', 'changes_required', 'rejected', 'inactive']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('products')->whereIn('status', ['pending', 'changes_required', 'rejected'])->update(['status' => 'draft']);
        $this->changeStatuses(['draft', 'active', 'inactive']);
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('moderation_reason'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('image_path'));
        Schema::table('product_images', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_variant_id'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('price'));
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
