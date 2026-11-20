<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ClerkUsers;
use App\Support\SafeExceptionReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A customer deleting their own account. Personal details, saved addresses, saved IDs,
 * the cart and devices go; orders, prescriptions, chats and reviews stay as the pharmacy's
 * records under "Deleted customer", since orders and dispensing must be kept.
 */
class DeleteCustomerAccount
{
    public function __construct(private ClerkUsers $clerkUsers, private SafeExceptionReporter $reporter) {}

    public function handle(User $user, ?string $ipAddress = null): void
    {
        $clerkUserId = DB::transaction(function () use ($user, $ipAddress): ?string {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->isPlatformAdministrator() || $user->tenantMemberships()->exists()) {
                throw ValidationException::withMessages(['account' => 'Staff accounts are removed by head office. Ask your manager.']);
            }

            if ($user->orders()->whereNotIn('status', ['delivered', 'cancelled'])->exists()) {
                throw ValidationException::withMessages(['account' => 'You have an order in progress. Delete your account after it is delivered or cancelled.']);
            }

            $hasOpenCase = DB::table('return_cases')->where('buyer_id', $user->id)->whereNotIn('status', ['completed', 'closed', 'denied', 'cancelled'])->exists()
                || DB::table('order_issues')->where('buyer_id', $user->id)->where('status', 'open')->exists();
            if ($hasOpenCase) {
                throw ValidationException::withMessages(['account' => 'You have an open return or order problem. Delete your account after it is settled.']);
            }

            $clerkUserId = $user->clerk_user_id;

            $user->deliveryAddresses()->delete();
            // One by one so each saved ID's photo is removed with it.
            $user->discountCards()->get()->each->delete();
            $user->deviceTokens()->delete();
            $user->pushSubscriptions()->delete();
            $user->savedProducts()->detach();
            $user->cart()->first()?->delete();

            $user->forceFill([
                'name' => 'Deleted customer',
                'email' => "deleted-{$user->id}@deleted.invalid",
                'password' => Str::random(64),
                'clerk_user_id' => null,
                'is_active' => false,
                'email_verified_at' => null,
                'birth_date' => null,
                'phone' => null,
                'sex' => null,
                'name_confirmed_at' => null,
                'remember_token' => null,
            ])->save();

            AuditLog::query()->create([
                'actor_user_id' => $user->id,
                'action' => 'account.deleted',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'ip_address' => $ipAddress,
            ]);

            return $clerkUserId;
        });

        if ($clerkUserId === null) {
            return;
        }

        try {
            $this->clerkUsers->delete($clerkUserId);
        } catch (Throwable $exception) {
            // The local account is already gone; a leftover Clerk sign-in only reaches a fresh, empty account.
            $this->reporter->report($exception);
        }
    }
}
