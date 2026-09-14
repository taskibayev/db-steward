<?php

namespace App\Connection;

final readonly class ConnectionTestResult
{
    private function __construct(
        public bool $successful,
        public ?string $serverVersion,
        public ?string $databaseName,
        public ?string $errorCode,
    ) {
    }

    public static function success(string $serverVersion, string $databaseName): self
    {
        return new self(true, $serverVersion, $databaseName, null);
    }

    public static function failure(string $errorCode): self
    {
        return new self(false, null, null, $errorCode);
    }
}
