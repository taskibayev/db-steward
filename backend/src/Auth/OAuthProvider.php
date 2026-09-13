<?php

namespace App\Auth;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('app.oauth_provider')]
interface OAuthProvider
{
    public function name(): string;

    public function authorizationUrl(string $state, string $redirectUri, Request $request): string;

    public function fetchProfile(string $code, string $redirectUri, Request $request): OAuthProfile;
}
