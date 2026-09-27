<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAccountController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeOwner($request);

        return view('admin.accounts', [
            'administrators' => User::query()
                ->where('account_type', 'admin')
                ->orderByDesc('platform_role')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreAdminAccountRequest $request): RedirectResponse
    {
        User::query()->create([
            ...$request->validated(),
            'account_type' => 'admin',
            'platform_role' => 'admin',
            'is_active' => true,
        ]);

        return to_route('admin.accounts.index')->with('status', 'Admin account created with the temporary password.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeOwner($request);

        abort_unless($user->account_type === 'admin' && $user->platform_role === 'admin', 403);

        $user->update(['is_active' => false]);

        return to_route('admin.accounts.index')->with('status', 'Admin account deactivated.');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()->isOwner(), 403);
    }
}
