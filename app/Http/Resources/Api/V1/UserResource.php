<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * The buyer-facing account shape. Clerk and moderation fields stay internal.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'account_type' => $this->account_type,
            'preferred_workspace' => $this->preferred_workspace,
            'email_verified' => $this->hasVerifiedClerkEmail(),
            'is_platform_administrator' => $this->isPlatformAdministrator(),
            'cart_reminders' => (bool) ($this->cart_reminders ?? true),
        ];
    }
}
