<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageAttachmentController extends Controller
{
    /**
     * Serve chat media by its short-lived signed URL, so image widgets need no bearer token.
     */
    public function show(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->status === 'available', 404);

        if (
            in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
            || ($attachment->mime_type === 'application/pdf' && $request->boolean('preview'))
        ) {
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
