<?php

namespace App\Policies;

use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessageAttachmentPolicy
{
    /**
     * Determine whether the user can read the attachment.
     *
     * Attachment access follows the conversation it was sent in, so a participant
     * rule change only has to be made once.
     */
    public function view(User $user, MessageAttachment $attachment): bool|Response
    {
        $attachment->loadMissing('message.conversation');

        $conversation = $attachment->message?->conversation;

        if ($conversation === null) {
            return Response::denyAsNotFound();
        }

        return $user->can('view', $conversation)
            ? true
            : Response::denyAsNotFound();
    }
}
