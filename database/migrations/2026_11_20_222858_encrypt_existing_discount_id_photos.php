<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * SC/PWD ID photos are now stored encrypted. Photos saved before that, on saved IDs and on
     * orders, are encrypted in place; saved IDs would otherwise keep copying plain photos into
     * new orders. Safe to run again: an encrypted file is left alone.
     */
    public function up(): void
    {
        $disk = Storage::disk('chat_attachments');
        $paths = DB::table('discount_cards')->whereNotNull('document_path')->pluck('document_path')
            ->merge(DB::table('orders')->whereNotNull('discount_document_path')->pluck('discount_document_path'))
            ->unique();

        foreach ($paths as $path) {
            if (! $disk->exists($path)) {
                continue;
            }
            $contents = $disk->get($path);
            try {
                Crypt::decryptString($contents);
            } catch (DecryptException) {
                $disk->put($path, Crypt::encryptString($contents));
            }
        }
    }

    /**
     * Decrypting every ID photo back to plain files is not something to undo by rollback.
     */
    public function down(): void {}
};
