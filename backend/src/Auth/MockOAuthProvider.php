<?php

namespace App\Auth;

use Symfony\Component\HttpFoundation\Request;

final class MockOAuthProvider implements OAuthProvider
{
    public function __construct(string $environment)
    {
        if ('prod' === $environment) {
            throw new \LogicException('Mock OAuth cannot be enabled in production.');
        }
    }

    public function name(): string
    {
        return 'mock';
    }

    public function authorizationUrl(string $state, string $redirectUri, Request $request): string
    {
        $email = $request->query->getString('email', 'admin@example.com');

        return $redirectUri.'?'.http_build_query(['state' => $state, 'code' => $email]);
    }

    public function fetchProfile(string $code, string $redirectUri, Request $request): OAuthProfile
    {
        if (false === filter_var($code, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Mock OAuth requires a valid email address.');
        }

        return new OAuthProfile('mock:'.mb_strtolower($code), $code, true);
    }
}
