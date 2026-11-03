<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin MessageAttachment
 */
class MessageAttachmentResource extends JsonResource
{
    /**
     * A short-lived signed URL, so an image widget can load private chat media
     * without carrying a bearer token.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'byte_size' => (int) $this->byte_size,
            'is_image' => str_starts_with((string) $this->mime_type, 'image/'),
            'url' => $this->status === 'available'
                ? URL::temporarySignedRoute('api.v1.media.attachments.show', now()->addHour(), ['attachment' => $this->id])
                : null,
        ];
    }
}
