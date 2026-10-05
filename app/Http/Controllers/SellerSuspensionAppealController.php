<?php

namespace App\Http\Controllers;

use App\Actions\SubmitStoreSuspensionAppeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class SellerSuspensionAppealController extends Controller
{
    public function store(Request $request, SubmitStoreSuspensionAppeal $submit): RedirectResponse
    {
        $data = $request->validate([
            'statement' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        try {
            $submit->handle($request->user(), $data['statement'], $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['appeal' => $exception->getMessage()]);
        }

        return to_route('seller-application.create')->with('status', 'Your suspension appeal has been submitted for review.');
    }
}
