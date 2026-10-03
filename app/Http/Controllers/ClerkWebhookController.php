<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ClerkWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            return response()->json(['message' => 'Invalid webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $event = $request->json()->all();
        $type = $event['type'] ?? null;
        $data = $event['data'] ?? null;

        if (! is_string($type) || ! is_array($data) || ! isset($data['id']) || ! is_string($data['id'])) {
            return response()->json(['message' => 'Invalid webhook payload.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($type === 'user.deleted') {
            User::query()->where('clerk_user_id', $data['id'])->update(['is_active' => false]);

            return response()->json(['status' => 'ok']);
        }

        if (in_array($type, ['user.created', 'user.updated'], true)) {
            $this->syncUser($data);
        }

        return response()->json(['status' => 'ok']);
    }

    /** @param array<string, mixed> $data */
    private function syncUser(array $data): void
    {
        $user = User::query()->where('clerk_user_id', $data['id'])->first();

        if ($user === null) {
            return;
        }

        $email = $this->verifiedPrimaryEmail($data);
        $name = trim(implode(' ', array_filter([$data['first_name'] ?? null, $data['last_name'] ?? null])));
        $updates = [];

        if ($email !== null && ! User::query()->where('email', $email)->where('id', '!=', $user->getKey())->exists()) {
            $updates['email'] = $email;
            $updates['email_verified_at'] = now();
        }

        if ($name !== '') {
            $updates['name'] = $name;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    /** @param array<string, mixed> $data */
    private function verifiedPrimaryEmail(array $data): ?string
    {
        $primaryEmailId = $data['primary_email_address_id'] ?? null;
        $emailAddresses = $data['email_addresses'] ?? [];

        if (! is_string($primaryEmailId) || ! is_array($emailAddresses)) {
            return null;
        }

        foreach ($emailAddresses as $emailAddress) {
            if (! is_array($emailAddress) || ($emailAddress['id'] ?? null) !== $primaryEmailId) {
                continue;
            }

            if (($emailAddress['verification']['status'] ?? null) !== 'verified') {
                return null;
            }

            return is_string($emailAddress['email_address'] ?? null) ? $emailAddress['email_address'] : null;
        }

        return null;
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('services.clerk.webhook_signing_secret');
        $id = $request->header('svix-id');
        $timestamp = $request->header('svix-timestamp');
        $signatures = $request->header('svix-signature');

        if (! is_string($secret) || ! str_starts_with($secret, 'whsec_') || ! is_string($id) || ! ctype_digit((string) $timestamp) || ! is_string($signatures)) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > 300) {
            return false;
        }

        $key = base64_decode(substr($secret, 6), true);
        if ($key === false) {
            Log::warning('Clerk webhook signing secret is not valid base64.');

            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$request->getContent(), $key, true));

        foreach (explode(' ', $signatures) as $signature) {
            [$version, $value] = array_pad(explode(',', $signature, 2), 2, null);

            if ($version === 'v1' && is_string($value) && hash_equals($expected, $value)) {
                return true;
            }
        }

        return false;
    }
}
