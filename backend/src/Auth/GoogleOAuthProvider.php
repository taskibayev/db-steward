<?php

namespace App\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleOAuthProvider implements OAuthProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_ID')]
        private readonly string $clientId,
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_SECRET')]
        private readonly string $clientSecret,
    ) {
    }

    public function name(): string
    {
        return 'google';
    }

    public function authorizationUrl(string $state, string $redirectUri, Request $request): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email',
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function fetchProfile(string $code, string $redirectUri, Request $request): OAuthProfile
    {
        $token = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ],
        ])->toArray();

        if (!isset($token['access_token']) || !is_string($token['access_token'])) {
            throw new \RuntimeException('OAuth provider did not return an access token.');
        }

        $profile = $this->httpClient->request('GET', 'https://openidconnect.googleapis.com/v1/userinfo', [
            'auth_bearer' => $token['access_token'],
        ])->toArray();

        if (!isset($profile['sub'], $profile['email']) || !is_string($profile['sub']) || !is_string($profile['email'])) {
            throw new \RuntimeException('OAuth provider returned an incomplete profile.');
        }

        return new OAuthProfile(
            $profile['sub'],
            $profile['email'],
            true === ($profile['email_verified'] ?? false),
        );
    }
}
