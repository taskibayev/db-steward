<?php

namespace App\Auth;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class OAuthProviderRegistry
{
    /** @var array<string, OAuthProvider> */
    private array $providers = [];

    /** @param iterable<OAuthProvider> $providers */
    public function __construct(#[AutowireIterator('app.oauth_provider')] iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->name()] = $provider;
        }
    }

    public function get(string $name): ?OAuthProvider
    {
        return $this->providers[$name] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}
