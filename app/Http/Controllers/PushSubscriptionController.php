<?php

namespace App\Http\Controllers;

use App\Actions\SavePushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PushSubscriptionController extends Controller
{
    public function store(Request $request, SavePushSubscription $save): Response
    {
        if (! $save->handle($request->user(), $request->all())) {
            throw ValidationException::withMessages([
                'endpoint' => 'This push subscription could not be saved.',
            ]);
        }

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->noContent();
    }
}
