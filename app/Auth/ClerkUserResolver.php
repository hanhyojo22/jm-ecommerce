<?php

namespace App\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClerkUserResolver
{
    public function __construct(private ClerkSessionVerifier $verifier) {}

    public function resolve(Request $request): ?User
    {
        $identity = $this->verifier->verify($request);

        if ($identity === null) {
            return null;
        }

        $user = User::query()->where('clerk_user_id', $identity->id)->first();

        if ($user !== null) {
            return $user->is_active ? $user : null;
        }

        $identity = $this->verifier->fetchUser($identity->id);

        if (! $identity->emailVerified || $identity->email === null) {
            return null;
        }

        $linkedUser = User::query()->where('clerk_user_id', $identity->id)->first();

        if ($linkedUser !== null) {
            return $linkedUser->is_active ? $linkedUser : null;
        }

        $matchingUsers = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($identity->email)])
            ->get();

        if ($matchingUsers->count() === 1) {
            $user = $matchingUsers->sole();

            if (! $user->is_active) {
                return null;
            }

            /*
             * Administrator rows are created before the invitation is accepted and
             * have no Clerk ID yet. Linking one by verified email is only safe when
             * the row was created by the invitation flow, so require the marker and
             * a role that actually grants platform access.
             */
            if ($user->account_type === 'admin' && ($user->admin_invited_at === null || ! $user->isPlatformAdministrator())) {
                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'action' => 'user.clerk_link_blocked',
                    'entity_type' => User::class,
                    'entity_id' => $user->id,
                    'new_values' => ['clerk_user_id' => $identity->id, 'reason' => 'administrator_not_invited'],
                    'ip_address' => $request->ip(),
                ]);

                return null;
            }

            DB::transaction(function () use ($user, $identity, $request): void {
                $attributes = [
                    'clerk_user_id' => $identity->id,
                    'email_verified_at' => now(),
                ];

                if ($user->account_type === 'admin') {
                    $attributes['admin_invited_at'] = null;
                }

                $user->forceFill($attributes)->save();

                AuditLog::query()->create([
                    'actor_user_id' => null,
                    'action' => 'user.clerk_linked',
                    'entity_type' => User::class,
                    'entity_id' => $user->id,
                    'old_values' => ['clerk_user_id' => null],
                    'new_values' => ['clerk_user_id' => $identity->id, 'email' => $user->email],
                    'ip_address' => $request->ip(),
                ]);
            });

            return $user;
        }

        if ($matchingUsers->isNotEmpty()) {
            return null;
        }

        try {
            $user = User::query()->create([
                'clerk_user_id' => $identity->id,
                'name' => $identity->name ?? Str::before($identity->email, '@'),
                'email' => $identity->email,
                'password' => Str::random(64),
                'account_type' => 'buyer',
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            /*
             * Two first requests for the same Clerk identity can both reach this insert.
             * The unique clerk_user_id index lets one win; the other reads the winner's
             * row instead of failing the sign-in with a 500.
             */
            $existing = User::query()->where('clerk_user_id', $identity->id)->first();

            if ($existing === null) {
                throw $exception;
            }

            return $existing->is_active ? $existing : null;
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
