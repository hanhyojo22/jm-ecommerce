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
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience', 20);
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 60);
            $table->string('body', 200);
            $table->string('url', 500);
            $table->unsignedInteger('recipient_count');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
