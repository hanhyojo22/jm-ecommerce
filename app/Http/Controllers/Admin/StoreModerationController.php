<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReactivateStore;
use App\Actions\ReopenStoreSuspensionAppeal;
use App\Actions\ReviewStoreSuspensionAppeal;
use App\Actions\SuspendStore;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreSuspensionAppeal;
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

    public function updateAppeal(Request $request, Store $store, StoreSuspensionAppeal $appeal, ReviewStoreSuspensionAppeal $action): RedirectResponse
    {
        $this->authorize('moderate', $store);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_note' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $action->handle(
                $request->user(),
                $store,
                $appeal,
                $data['decision'],
                $data['review_note'],
                $request->ip(),
            );
        } catch (LogicException $exception) {
            return back()->withErrors(['appeal' => $exception->getMessage()]);
        }

        $message = $data['decision'] === 'approved'
            ? 'Appeal approved. The store and seller workspace have been reactivated.'
            : 'Appeal rejected. The store remains suspended.';

        return to_route('admin.stores.show', $store)->with('status', $message);
    }

    public function reopenAppeal(Request $request, Store $store, StoreSuspensionAppeal $appeal, ReopenStoreSuspensionAppeal $action): RedirectResponse
    {
        $this->authorize('moderate', $store);
        $reasonKey = 'reason.'.$appeal->id;
        $validator = validator($request->all(), [$reasonKey => ['required', 'string', 'min:10', 'max:1000']]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->withFragment('appeal-'.$appeal->id);
        }

        $data = $validator->validated();

        try {
            $action->handle($request->user(), $store, $appeal, $data['reason'][$appeal->id], $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['appeal.'.$appeal->id => $exception->getMessage()])->withFragment('appeal-'.$appeal->id);
        }

        return to_route('admin.stores.show', $store)->with('status', 'Appeal reopened. The store is suspended until a new review decision.');
    }
}
