<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\SendConversationAttachment;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationAttachmentController extends Controller
{
    public function store(Request $request, Conversation $conversation, SendConversationAttachment $sendAttachment): JsonResponse|RedirectResponse
    {
        $this->authorize('send', $conversation);
        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'client_message_id' => ['required', 'uuid'],
            'body' => ['nullable', 'string', 'max:2000'],
            'product_id' => ['nullable', 'ulid', 'prohibits:seller_order_id'],
            'seller_order_id' => ['nullable', 'ulid'],
        ]);

        $message = $sendAttachment->handle(
            $conversation,
            $request->user(),
            $request->file('attachment'),
            trim($data['body'] ?? ''),
            $data['client_message_id'],
            $data['product_id'] ?? null,
            $data['seller_order_id'] ?? null,
        );

        return $request->expectsJson()
            ? response()->json(['message' => 'Attachment sent.', 'id' => $message->id, 'client_message_id' => $message->client_message_id])
            : back()->with('status', 'Attachment sent.');
    }

    public function show(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);
        abort_unless($attachment->status === 'available', 404);

        if (in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true) || ($attachment->mime_type === 'application/pdf' && $request->boolean('preview'))) {
            return Storage::disk($attachment->disk)->response(
                $attachment->path,
                $attachment->original_name,
                [
                    'Content-Type' => $attachment->mime_type,
                    'X-Content-Type-Options' => 'nosniff',
                    'Content-Security-Policy' => "sandbox; default-src 'none'",
                ],
            );
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
