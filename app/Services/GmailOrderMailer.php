<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class GmailOrderMailer
{
    public function configured(): bool
    {
        return filled(config('services.gmail.client_id'))
            && filled(config('services.gmail.client_secret'))
            && filled(config('services.gmail.refresh_token'))
            && filled(config('services.gmail.from'));
    }

    public function send(User $recipient, string $subject, string $body): void
    {
        if (! $this->configured()) {
            Log::warning('Gmail email skipped: missing configuration.', [
                'missing' => array_keys(array_filter(config('services.gmail'), fn (mixed $value): bool => blank($value))),
            ]);

            return;
        }

        $token = Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'refresh_token' => config('services.gmail.refresh_token'),
            'grant_type' => 'refresh_token',
        ])->throw()->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Gmail did not return an access token.');
        }

        $message = (new Email)
            ->from(new Address(config('services.gmail.from'), 'JMMart'))
            ->to(new Address($recipient->email, $recipient->name))
            ->subject($subject)
            ->text($body);

        Http::withToken($token)->timeout(8)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
            'raw' => rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '='),
        ])->throw();

        Log::info('Gmail email accepted by API.', ['subject' => $subject]);
    }
}
