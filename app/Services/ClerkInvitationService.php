<?php

namespace App\Services;

use App\Models\TenantMember;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClerkInvitationService
{
    /**
     * Invitations land on the sign-up page, where Clerk's sign-up form accepts the ticket and creates the
     * login; the first sign-in then links it to the account made here by email.
     */
    public function inviteAdministrator(string $email): void
    {
        $this->invite(['email_address' => $email, 'redirect_url' => url('/sign-up')]);
    }

    /**
     * Emails a new employee a sign-up link that lapses when their place on the team does. An earlier
     * pending invitation for the same email is not an error, so adding someone again just resends.
     */
    public function inviteEmployee(string $email): void
    {
        $this->invite([
            'email_address' => $email,
            'redirect_url' => url('/sign-up'),
            'expires_in_days' => TenantMember::INVITATION_DAYS,
            'ignore_existing' => true,
        ]);
    }

    /** @param  array<string, mixed>  $invitation */
    private function invite(array $invitation): void
    {
        $secretKey = config('services.clerk.secret_key');

        if (! is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('Clerk is not configured.');
        }

        Http::baseUrl('https://api.clerk.com/v1')
            ->connectTimeout(3)
            ->timeout(5)
            ->withToken($secretKey)
            ->acceptJson()
            ->post('/invitations', $invitation)
            ->throw();
    }
}
