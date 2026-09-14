<?php

namespace App\Connection;

use Doctrine\DBAL\DriverManager;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ClientDatabaseConnector::class)]
final class DbalClientDatabaseConnector implements ClientDatabaseConnector
{
    public function test(ClientDatabaseCredentials $credentials): ConnectionTestResult
    {
        $connection = null;
        try {
            $connection = DriverManager::getConnection([
                'driver' => 'pdo_mysql',
                'host' => $credentials->host,
                'port' => $credentials->port,
                'dbname' => $credentials->database,
                'user' => $credentials->username,
                'password' => $credentials->password,
                'charset' => 'utf8mb4',
                'driverOptions' => [\PDO::ATTR_TIMEOUT => 5],
            ]);

            $connection->executeQuery('SELECT 1')->fetchOne();
            $version = $connection->executeQuery('SELECT VERSION()')->fetchOne();
            $database = $connection->executeQuery('SELECT DATABASE()')->fetchOne();

            if (!is_string($version) || !is_string($database) || '' === $database) {
                return ConnectionTestResult::failure('unexpected_server_response');
            }

            return ConnectionTestResult::success($version, $database);
        } catch (\Throwable) {
            return ConnectionTestResult::failure('connection_failed');
        } finally {
            $connection?->close();
        }
    }
}
