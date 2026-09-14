<?php

namespace App\Tests\Connection;

use App\Connection\SodiumCredentialCipher;
use PHPUnit\Framework\TestCase;

final class SodiumCredentialCipherTest extends TestCase
{
    private const KEY = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

    public function testRoundTripUsesAuthenticatedRandomEncryption(): void
    {
        $cipher = new SodiumCredentialCipher(self::KEY);
        $first = $cipher->encrypt('sensitive-value');
        $second = $cipher->encrypt('sensitive-value');

        self::assertNotSame('sensitive-value', $first);
        self::assertNotSame($first, $second);
        self::assertSame('sensitive-value', $cipher->decrypt($first));
        self::assertSame('sensitive-value', $cipher->decrypt($second));
    }

    public function testTamperingIsRejected(): void
    {
        $cipher = new SodiumCredentialCipher(self::KEY);
        $encrypted = $cipher->encrypt('sensitive-value');
        $last = substr($encrypted, -1);
        $tampered = substr($encrypted, 0, -1).('A' === $last ? 'B' : 'A');

        $this->expectException(\RuntimeException::class);
        $cipher->decrypt($tampered);
    }
}
