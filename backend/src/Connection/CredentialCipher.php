<?php

namespace App\Connection;

interface CredentialCipher
{
    public function encrypt(string $plaintext): string;

    public function decrypt(string $ciphertext): string;
}
