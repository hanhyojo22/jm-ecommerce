<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspacePreferenceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $workspace = $request->validate(['workspace' => ['required', 'in:customer,seller']])['workspace'];
        $user = $request->user();

        abort_if($workspace === 'seller' && ($user->account_type !== 'seller' || $user->sellerTenant() === null), 403);

        $user->update(['preferred_workspace' => $workspace]);

        return to_route($workspace === 'seller' ? 'seller.dashboard' : 'home');
    }
}
