<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX product_review_responses_one_root_per_review ON product_review_responses (product_review_id) WHERE parent_response_id IS NULL AND author_type = 'seller'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX product_review_responses_one_root_per_review');
    }
};
