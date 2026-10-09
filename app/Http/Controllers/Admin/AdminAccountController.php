<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminAccountRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ClerkInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function store(StoreAdminAccountRequest $request, ClerkInvitationService $invitations): RedirectResponse
    {
        $invitations->inviteAdministrator($request->string('email')->toString());

        DB::transaction(function () use ($request): void {
            $user = User::query()->create([
                ...$request->validated(),
                'password' => str()->random(64),
                'account_type' => 'admin',
                'platform_role' => 'admin',
                'is_active' => true,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'admin_account.invited',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'new_values' => ['email' => $user->email, 'account_type' => 'admin', 'platform_role' => 'admin'],
                'ip_address' => $request->ip(),
            ]);
        });

        return to_route('admin.accounts.index')->with('status', 'Clerk invitation sent. The account links when the invitation is accepted.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeOwner($request);

        abort_unless($user->account_type === 'admin' && $user->platform_role === 'admin', 403);

        DB::transaction(function () use ($request, $user): void {
            $wasActive = $user->is_active;
            $user->update(['is_active' => false]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'admin_account.deactivated',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'old_values' => ['is_active' => $wasActive],
                'new_values' => ['is_active' => false],
                'ip_address' => $request->ip(),
            ]);
        });

        return to_route('admin.accounts.index')->with('status', 'Admin account deactivated.');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()->isOwner(), 403);
    }
}
