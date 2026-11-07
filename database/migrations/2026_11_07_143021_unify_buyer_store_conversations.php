<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('seller_order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('context_snapshot')->nullable();
        });
        Schema::create('conversation_message_reads', function (Blueprint $table): void {
            $table->foreignUlid('message_id')->constrained('conversation_messages')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->primary(['message_id', 'user_id']);
        });
        Schema::create('conversation_aliases', function (Blueprint $table): void {
            $table->ulid('old_id')->primary();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
        });

        DB::transaction(function (): void {
            $canonical = [];
            foreach (DB::table('conversations')->orderBy('created_at')->orderBy('id')->get() as $room) {
                $key = $room->buyer_id.':'.$room->store_id;
                $target = $canonical[$key] ??= $room->id;
                $messages = DB::table('conversation_messages')->where('conversation_id', $room->id)->orderBy('created_at')->orderBy('id')->get();
                if ($first = ($messages->firstWhere('sender_id', $room->buyer_id) ?? $messages->first())) {
                    $snapshot = null;
                    if ($room->product_id !== null) {
                        $product = DB::table('products')->where('id', $room->product_id)->first();
                        $price = (string) ($product?->price ?? '0');
                        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
                        $price = $whole.'.'.str_pad($fraction, 2, '0');
                        $image = DB::table('product_images')->where('product_id', $room->product_id)->orderBy('position')->value('path');
                        if ($image !== null && Storage::disk('product_images')->exists($image)) {
                            $preservedPath = 'chat-snapshots/'.Str::ulid().'.'.pathinfo($image, PATHINFO_EXTENSION);
                            Storage::disk('product_images')->copy($image, $preservedPath);
                            $image = $preservedPath;
                        }
                        $snapshot = ['type' => 'product', 'title' => $product?->name ?? 'Product', 'price' => $price, 'image_path' => $image];
                    } elseif ($room->seller_order_id !== null) {
                        $order = DB::table('seller_orders')->join('orders', 'orders.id', '=', 'seller_orders.order_id')->where('seller_orders.id', $room->seller_order_id)->select('orders.number', 'seller_orders.total', 'seller_orders.status')->first();
                        $total = (string) ($order?->total ?? '0');
                        [$whole, $fraction] = array_pad(explode('.', $total, 2), 2, '');
                        $snapshot = ['type' => 'order', 'number' => $order?->number ?? 'Order', 'total' => $whole.'.'.str_pad($fraction, 2, '0'), 'status' => $order?->status];
                    }
                    DB::table('conversation_messages')->where('id', $first->id)->update(['product_id' => $room->product_id, 'seller_order_id' => $room->seller_order_id, 'context_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                }
                foreach (DB::table('conversation_read_states')->where('conversation_id', $room->id)->get() as $state) {
                    foreach ($messages as $message) {
                        if ($message->sender_id !== $state->user_id && $state->last_read_message_id !== null && $message->id <= $state->last_read_message_id) {
                            DB::table('conversation_message_reads')->insertOrIgnore(['message_id' => $message->id, 'user_id' => $state->user_id, 'read_at' => $state->last_read_at ?? $state->updated_at]);
                        }
                    }
                }
                if ($target !== $room->id) {
                    DB::table('conversation_aliases')->insert(['old_id' => $room->id, 'conversation_id' => $target]);
                    DB::table('conversation_messages')->where('conversation_id', $room->id)->update(['conversation_id' => $target]);
                    DB::table('conversation_read_states')->where('conversation_id', $room->id)->delete();
                    DB::table('conversations')->where('id', $room->id)->delete();
                }
            }
            foreach (array_unique(array_values($canonical)) as $id) {
                DB::table('conversations')->where('id', $id)->update(['latest_message_at' => DB::table('conversation_messages')->where('conversation_id', $id)->max('created_at')]);
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS conversations_exactly_one_context');
            DB::statement('ALTER TABLE conversation_messages ADD CONSTRAINT conversation_messages_one_context CHECK (product_id IS NULL OR seller_order_id IS NULL)');
        }
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique(['buyer_id', 'product_id']);
            $table->dropUnique(['buyer_id', 'seller_order_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['seller_order_id']);
            $table->dropColumn(['product_id', 'seller_order_id']);
            $table->unique(['buyer_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Merged conversations cannot be reversed without losing message history.');
    }
};
