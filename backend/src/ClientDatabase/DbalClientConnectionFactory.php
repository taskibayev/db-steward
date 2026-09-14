<?php

namespace App\ClientDatabase;

use App\Connection\ClientDatabaseCredentials;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class DbalClientConnectionFactory
{
    public function create(ClientDatabaseCredentials $credentials): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $credentials->host,
            'port' => $credentials->port,
            'dbname' => $credentials->database,
            'user' => $credentials->username,
            'password' => $credentials->password,
            'charset' => 'utf8mb4',
            'driverOptions' => [\PDO::ATTR_TIMEOUT => 5],
        ]);
    }
}
