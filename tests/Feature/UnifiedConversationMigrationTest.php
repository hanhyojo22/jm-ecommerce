<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedConversationMigrationTest extends TestCase
{
    public function test_legacy_rooms_merge_with_exact_reads_and_aliases(): void
    {
        $previous = config('database.default');
        config(['database.connections.chat_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true], 'database.default' => 'chat_migration_test']);
        try {
            $this->legacySchema();
            $buyer = (string) Str::ulid();
            $seller = (string) Str::ulid();
            $store = (string) Str::ulid();
            $products = [(string) Str::ulid(), (string) Str::ulid()];
            $rooms = [(string) Str::ulid(), (string) Str::ulid()];
            $messages = [(string) Str::ulid(), (string) Str::ulid(), (string) Str::ulid(), (string) Str::ulid()];
            DB::table('users')->insert([['id' => $buyer], ['id' => $seller]]);
            DB::table('stores')->insert(['id' => $store]);
            DB::table('products')->insert([
                ['id' => $products[0], 'store_id' => $store, 'name' => 'Laptop Pro', 'price' => '42000.00'],
                ['id' => $products[1], 'store_id' => $store, 'name' => 'Mouse', 'price' => '850.00'],
            ]);
            DB::table('conversations')->insert([
                ['id' => $rooms[0], 'buyer_id' => $buyer, 'store_id' => $store, 'product_id' => $products[0], 'created_at' => '2026-01-01 10:00:00'],
                ['id' => $rooms[1], 'buyer_id' => $buyer, 'store_id' => $store, 'product_id' => $products[1], 'created_at' => '2026-01-02 10:00:00'],
            ]);
            DB::table('conversation_messages')->insert([
                ['id' => $messages[0], 'conversation_id' => $rooms[0], 'sender_id' => $buyer, 'body' => 'Laptop?', 'created_at' => '2026-01-01 10:00:00'],
                ['id' => $messages[1], 'conversation_id' => $rooms[0], 'sender_id' => $seller, 'body' => 'Yes', 'created_at' => '2026-01-01 10:01:00'],
                ['id' => $messages[2], 'conversation_id' => $rooms[1], 'sender_id' => $buyer, 'body' => 'Mouse?', 'created_at' => '2026-01-02 10:00:00'],
                ['id' => $messages[3], 'conversation_id' => $rooms[1], 'sender_id' => $seller, 'body' => 'Also yes', 'created_at' => '2026-01-02 10:01:00'],
            ]);
            DB::table('conversation_read_states')->insert([
                ['conversation_id' => $rooms[0], 'user_id' => $buyer, 'last_read_message_id' => $messages[1], 'last_read_at' => now()],
                ['conversation_id' => $rooms[1], 'user_id' => $seller, 'last_read_message_id' => $messages[2], 'last_read_at' => now()],
            ]);
            $attachment = (string) Str::ulid();
            $report = (string) Str::ulid();
            DB::table('message_attachments')->insert(['id' => $attachment, 'conversation_message_id' => $messages[2]]);
            DB::table('message_reports')->insert(['id' => $report, 'conversation_message_id' => $messages[3]]);

            (require database_path('migrations/2026_11_07_143021_unify_buyer_store_conversations.php'))->up();

            $this->assertSame(1, DB::table('conversations')->count());
            $this->assertSame(4, DB::table('conversation_messages')->where('conversation_id', $rooms[0])->count());
            $this->assertSame($messages, DB::table('conversation_messages')->where('conversation_id', $rooms[0])->orderBy('created_at')->orderBy('id')->pluck('id')->all());
            $this->assertSame($messages[2], DB::table('message_attachments')->where('id', $attachment)->value('conversation_message_id'));
            $this->assertSame($messages[3], DB::table('message_reports')->where('id', $report)->value('conversation_message_id'));
            $this->assertSame($rooms[0], DB::table('conversation_aliases')->where('old_id', $rooms[1])->value('conversation_id'));
            $this->assertSame(1, DB::table('conversation_message_reads')->where('user_id', $buyer)->count());
            $this->assertSame($messages[1], DB::table('conversation_message_reads')->where('user_id', $buyer)->value('message_id'));
            $this->assertSame('Laptop Pro', json_decode(DB::table('conversation_messages')->where('id', $messages[0])->value('context_snapshot'), true)['title']);
            $this->assertSame('850.00', json_decode(DB::table('conversation_messages')->where('id', $messages[2])->value('context_snapshot'), true)['price']);
        } finally {
            config(['database.default' => $previous]);
            DB::purge('chat_migration_test');
        }
    }

    private function legacySchema(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->ulid('id')->primary());
        Schema::create('stores', fn (Blueprint $table) => $table->ulid('id')->primary());
        Schema::create('products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('store_id')->constrained();
            $table->string('name');
            $table->decimal('price', 12, 2);
        });
        Schema::create('product_images', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained();
            $table->string('path');
            $table->integer('position');
        });
        Schema::create('seller_orders', fn (Blueprint $table) => $table->ulid('id')->primary());
        Schema::create('conversations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('buyer_id')->constrained('users');
            $table->foreignUlid('store_id')->constrained();
            $table->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('seller_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('latest_message_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['buyer_id', 'product_id']);
            $table->unique(['buyer_id', 'seller_order_id']);
        });
        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('sender_id')->constrained('users');
            $table->text('body');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('conversation_read_states', function (Blueprint $table): void {
            $table->ulid('id')->nullable();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained();
            $table->foreignUlid('last_read_message_id')->nullable()->constrained('conversation_messages');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_message_id')->constrained()->cascadeOnDelete();
        });
        Schema::create('message_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_message_id')->constrained()->cascadeOnDelete();
        });
    }
}
