<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branches of one pharmacy are not rated against each other.
     */
    public function up(): void
    {
        Schema::dropIfExists('store_ratings');
    }

    /**
     * Store ratings are not coming back; restore an older backup if their data is ever needed.
     */
    public function down(): void {}
};
