<?php

namespace App\Http\Controllers;

use App\Actions\RecordOnlinePayment;
use App\Services\PayMongo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PayMongoWebhookController extends Controller
{
    /**
     * PayMongo retries an event until it gets a 2xx, so each event ID is handled once and a
     * failure clears it again for the retry.
     */
    public function __invoke(Request $request, PayMongo $payMongo, RecordOnlinePayment $recordOnlinePayment): JsonResponse
    {
        $event = $request->json('data');

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['attributes']['type'] ?? null)) {
            return response()->json(['message' => 'Invalid webhook payload.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $payMongo->hasValidSignature($request->header('Paymongo-Signature'), $request->getContent(), (bool) ($event['attributes']['livemode'] ?? false))) {
            return response()->json(['message' => 'Invalid webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $type = $event['attributes']['type'];

        if (DB::table('paymongo_events')->insertOrIgnore(['id' => $event['id'], 'type' => $type, 'received_at' => now()]) === 0) {
            return response()->json(['status' => 'duplicate']);
        }

        try {
            if ($type === 'checkout_session.payment.paid') {
                $session = $event['attributes']['data'] ?? [];
                $payments = collect($session['attributes']['payments'] ?? []);
                $paid = $payments->first(fn (array $payment): bool => ($payment['attributes']['status'] ?? null) === 'paid') ?? $payments->first();

                if (is_string($session['id'] ?? null) && is_string($paid['id'] ?? null)) {
                    $recordOnlinePayment->handle($session['id'], $paid['id']);
                }
            }
        } catch (Throwable $exception) {
            DB::table('paymongo_events')->where('id', $event['id'])->delete();

            throw $exception;
        }

        return response()->json(['status' => 'ok']);
    }
}
