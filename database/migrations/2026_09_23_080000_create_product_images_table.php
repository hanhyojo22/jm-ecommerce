<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
            $t->unique(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
