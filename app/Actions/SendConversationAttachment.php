<?php

namespace App\Actions;

use App\Events\MessageAttachmentStored;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendConversationAttachment
{
    public function __construct(private SendConversationMessage $sendMessage) {}

    /**
     * Store one chat attachment and the message that carries it.
     *
     * The file is checked by content, not just by the extension the client sent, and
     * the row and the message are created together so a failure leaves neither behind.
     */
    public function handle(
        Conversation $conversation,
        User $sender,
        UploadedFile $file,
        string $body,
        string $clientMessageId,
        ?string $productId = null,
        ?string $sellerOrderId = null,
    ): ConversationMessage {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $allowedTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        $contents = $extension === 'pdf' ? file_get_contents($file->getRealPath()) : null;
        $validContent = $extension === 'pdf'
            ? is_string($contents) && str_starts_with($contents, '%PDF-') && str_contains(substr($contents, -1024), '%%EOF')
            : ($image = @getimagesize($file->getRealPath())) !== false && $image['mime'] === $mimeType;

        if (($allowedTypes[$extension] ?? null) !== $mimeType || ! $validContent) {
            throw ValidationException::withMessages(['attachment' => 'Choose a valid JPG, PNG, WebP, or PDF file.']);
        }

        $existingMessage = $conversation->messages()
            ->where('sender_id', $sender->id)
            ->where('client_message_id', $clientMessageId)
            ->first();

        if ($existingMessage !== null) {
            abort_unless($existingMessage->type === 'file' && $existingMessage->product_id === $productId && $existingMessage->seller_order_id === $sellerOrderId, 409);

            return $existingMessage;
        }

        $disk = 'chat_attachments';
        $path = $file->storeAs('chat/'.now()->format('Y/m'), Str::ulid().'.'.($file->guessExtension() ?? 'bin'), $disk);
        abort_if($path === false, 503);
        $stored = false;
        $attachment = null;
        $message = null;

        try {
            DB::transaction(function () use ($conversation, $sender, $body, $clientMessageId, $productId, $sellerOrderId, $file, $disk, $path, &$stored, &$attachment, &$message): void {
                $message = $this->sendMessage->handle($conversation, $sender, $body, $clientMessageId, 'file', $productId, $sellerOrderId);
                if (! $message->wasRecentlyCreated) {
                    return;
                }

                $attachment = $message->attachments()->create([
                    'uploaded_by' => $sender->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'byte_size' => $file->getSize(),
                    'status' => 'available',
                ]);
                $stored = true;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        if (! $stored) {
            Storage::disk($disk)->delete($path);
        } elseif ($attachment !== null) {
            MessageAttachmentStored::dispatch($attachment);
        }

        return $message;
    }
}
