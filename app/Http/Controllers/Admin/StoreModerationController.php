<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReactivateStore;
use App\Actions\SuspendStore;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class StoreModerationController extends Controller
{
    public function suspend(Request $request, Store $store, SuspendStore $action): RedirectResponse
    {
        $this->authorize('moderate', $store);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $action->handle($request->user(), $store, $data['reason'], $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['store' => $exception->getMessage()]);
        }

        return to_route('admin.stores.show', $store)->with('status', 'Store suspended. It is hidden from the storefront until reactivated.');
    }

    public function reactivate(Request $request, Store $store, ReactivateStore $action): RedirectResponse
    {
        $this->authorize('moderate', $store);

        try {
            $action->handle($request->user(), $store, $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['store' => $exception->getMessage()]);
        }

        return to_route('admin.stores.show', $store)->with('status', 'Store reactivated. It is visible on the storefront again.');
    }
}
