<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->user()->update(['cart_reminders' => $request->boolean('cart_reminders')]);

        return to_route('profile.edit')->with('status', 'Notification settings saved.');
    }
}
