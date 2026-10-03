<?php

namespace App\Auth;

use Illuminate\Http\Request;

interface ClerkSessionVerifier
{
    public function verify(Request $request): ?ClerkIdentity;

    public function fetchUser(string $clerkUserId): ClerkIdentity;
}
