<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\SendConversationMessage;
use App\Events\MessageAttachmentStored;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ConversationAttachmentController extends Controller
{
    public function store(Request $request, Conversation $conversation, SendConversationMessage $sendMessage): RedirectResponse
    {
        $this->authorize('send', $conversation);
        $data = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'extensions:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'client_message_id' => ['required', 'uuid'],
        ]);
        $existingMessage = $conversation->messages()
            ->where('sender_id', $request->user()->id)
            ->where('client_message_id', $data['client_message_id'])
            ->first();

        if ($existingMessage !== null) {
            abort_unless($existingMessage->type === 'file', 409);

            return back();
        }

        $file = $request->file('attachment');
        $disk = 'chat_attachments';
        $path = $file->storeAs('chat/'.now()->format('Y/m'), Str::ulid().'.'.($file->guessExtension() ?? 'bin'), $disk);
        abort_if($path === false, 503);
        $stored = false;
        $attachment = null;

        try {
            DB::transaction(function () use ($request, $conversation, $sendMessage, $data, $file, $disk, $path, &$stored, &$attachment): void {
                $message = $sendMessage->handle($conversation, $request->user(), $file->getClientOriginalName(), $data['client_message_id'], 'file');
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

        return back()->with('status', 'Attachment sent.');
    }

    public function show(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('message.conversation');
        $this->authorize('view', $attachment->message->conversation);
        abort_unless($attachment->status === 'available', 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
