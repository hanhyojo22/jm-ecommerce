<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'blocked_user_id']);
        });

        Schema::create('message_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 100);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_message_id', 'reporter_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('byte_size');
            $table->string('status', 20)->default('available');
            $table->timestamps();
            $table->index(['conversation_message_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_reports');
        Schema::dropIfExists('blocked_users');
    }
};
