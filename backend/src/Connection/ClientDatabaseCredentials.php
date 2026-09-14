<?php

namespace App\Connection;

final readonly class ClientDatabaseCredentials
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {
    }
}
