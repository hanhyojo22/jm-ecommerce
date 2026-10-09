<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\SendConversationMessage;
use App\Events\MessageAttachmentStored;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ConversationAttachmentController extends Controller
{
    public function store(Request $request, Conversation $conversation, SendConversationMessage $sendMessage): JsonResponse|RedirectResponse
    {
        $this->authorize('send', $conversation);
        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'client_message_id' => ['required', 'uuid'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);
        $file = $request->file('attachment');
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
            ->where('sender_id', $request->user()->id)
            ->where('client_message_id', $data['client_message_id'])
            ->first();

        if ($existingMessage !== null) {
            abort_unless($existingMessage->type === 'file', 409);

            return $request->expectsJson() ? response()->json(['message' => 'Attachment sent.']) : back();
        }

        $disk = 'chat_attachments';
        $path = $file->storeAs('chat/'.now()->format('Y/m'), Str::ulid().'.'.($file->guessExtension() ?? 'bin'), $disk);
        abort_if($path === false, 503);
        $stored = false;
        $attachment = null;

        try {
            DB::transaction(function () use ($request, $conversation, $sendMessage, $data, $file, $disk, $path, &$stored, &$attachment): void {
                $body = trim($data['body'] ?? '');
                $message = $sendMessage->handle($conversation, $request->user(), $body !== '' ? $body : $file->getClientOriginalName(), $data['client_message_id'], 'file');
                if (! $message->wasRecentlyCreated) {
                    return;
                }

                $attachment = $message->attachments()->create([
                    'uploaded_by' => $request->user()->id,
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

        return $request->expectsJson() ? response()->json(['message' => 'Attachment sent.']) : back()->with('status', 'Attachment sent.');
    }

    public function show(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('message.conversation');
        $this->authorize('view', $attachment->message->conversation);
        abort_unless($attachment->status === 'available', 404);

        if (in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true) || ($attachment->mime_type === 'application/pdf' && $request->boolean('preview'))) {
            return Storage::disk($attachment->disk)->response(
                $attachment->path,
                $attachment->original_name,
                [
                    'Content-Type' => $attachment->mime_type,
                    'X-Content-Type-Options' => 'nosniff',
                ],
            );
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
