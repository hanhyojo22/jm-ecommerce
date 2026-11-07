<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WebPushKeyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $publicKey = config('webpush.vapid.public_key');

        abort_unless(filled($publicKey), 404);

        return response()->json(['key' => $publicKey]);
    }
}
