<?php

namespace App\Auth;

final readonly class OAuthProfile
{
    public function __construct(
        public string $subject,
        public string $email,
        public bool $emailVerified,
    ) {
    }
}
