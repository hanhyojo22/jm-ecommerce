<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClerkSessionHandoffController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.handoff', [
            'afterAuthUrl' => $this->safeRedirectPath($request->string('redirect')->toString()),
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $response = response()->json([
            'redirect' => $this->safeRedirectPath($request->string('redirect')->toString()),
        ]);

        $sessionToken = $request->bearerToken();

        if (is_string($sessionToken) && $sessionToken !== '') {
            $response->withCookie(cookie(
                '__session',
                $sessionToken,
                1,
                '/',
                null,
                $request->isSecure(),
                false,
                false,
                'lax',
            ));
        }

        return $response;
    }

    private function safeRedirectPath(string $path): string
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return '/';
        }

        return $path;
    }
}
