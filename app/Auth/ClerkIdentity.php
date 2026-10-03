<?php

namespace App\Auth;

final readonly class ClerkIdentity
{
    public function __construct(
        public string $id,
        public ?string $email = null,
        public bool $emailVerified = false,
        public ?string $name = null,
    ) {}
}
