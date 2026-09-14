<?php

namespace App\Connection;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(CredentialCipher::class)]
final class SodiumCredentialCipher implements CredentialCipher
{
    private const PREFIX = 'v1.';
    private const ADDITIONAL_DATA = 'db-steward:client-database-password:v1';
    private readonly string $key;

    public function __construct(#[Autowire(env: 'CLIENT_CREDENTIALS_KEY')] string $encodedKey)
    {
        $key = base64_decode($encodedKey, true);
        if (false === $key || SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES !== strlen($key)) {
            throw new \InvalidArgumentException('CLIENT_CREDENTIALS_KEY must be a base64-encoded 32-byte key.');
        }
        $this->key = $key;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $encrypted = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            self::ADDITIONAL_DATA,
            $nonce,
            $this->key,
        );

        return self::PREFIX.base64_encode($nonce.$encrypted);
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new \RuntimeException('Unsupported encrypted credential format.');
        }

        $payload = base64_decode(substr($ciphertext, strlen(self::PREFIX)), true);
        if (false === $payload || strlen($payload) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw new \RuntimeException('Encrypted credential is malformed.');
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $encrypted = substr($payload, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $encrypted,
            self::ADDITIONAL_DATA,
            $nonce,
            $this->key,
        );
        if (false === $plaintext) {
            throw new \RuntimeException('Encrypted credential authentication failed.');
        }

        return $plaintext;
    }
}
