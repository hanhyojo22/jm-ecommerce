<?php

namespace App\Auth;

use App\Models\User;
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
            return $user;
        }

        $identity = $this->verifier->fetchUser($identity->id);

        if (! $identity->emailVerified || $identity->email === null) {
            return null;
        }

        return DB::transaction(function () use ($identity): ?User {
            $linkedUser = User::query()->where('clerk_user_id', $identity->id)->lockForUpdate()->first();

            if ($linkedUser !== null) {
                return $linkedUser;
            }

            $matchingUsers = User::query()
                ->where('email', $identity->email)
                ->lockForUpdate()
                ->get();

            if ($matchingUsers->count() === 1) {
                $user = $matchingUsers->sole();
                $user->forceFill([
                    'clerk_user_id' => $identity->id,
                    'email_verified_at' => now(),
                ])->save();

                return $user;
            }

            if ($matchingUsers->isNotEmpty()) {
                return null;
            }

            $user = User::query()->create([
                'clerk_user_id' => $identity->id,
                'name' => $identity->name ?? Str::before($identity->email, '@'),
                'email' => $identity->email,
                'password' => Str::random(64),
                'account_type' => 'buyer',
                'is_active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        });
    }
}
