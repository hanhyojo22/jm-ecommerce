<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceTokenController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
            'rich_notifications' => ['sometimes', 'boolean'],
        ]);

        // Matched globally, not through the relation: a token moving to a different
        // account (new sign-in on a shared device) re-points it instead of hitting
        // the unique constraint.
        DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'rich_notifications' => ($data['platform'] ?? 'android') === 'android' && $request->boolean('rich_notifications'),
            ],
        );

        return response()->noContent(201);
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        $request->user()->deviceTokens()->where('token', $data['token'])->delete();

        return response()->noContent();
    }
}
