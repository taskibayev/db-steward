<?php

namespace App\Connection;

use App\Entity\ClientConnection;

final class ConnectionView
{
    /** @return array<string, bool|int|string|null> */
    public static function fromEntity(ClientConnection $connection): array
    {
        return [
            'id' => $connection->getId()->toRfc4122(),
            'name' => $connection->getName(),
            'host' => $connection->getHost(),
            'port' => $connection->getPort(),
            'database' => $connection->getDatabaseName(),
            'credentialsConfigured' => true,
            'active' => $connection->isActive(),
            'status' => $connection->getStatus()->value,
            'serverVersion' => $connection->getServerVersion(),
            'lastErrorCode' => $connection->getLastErrorCode(),
            'lastCheckedAt' => $connection->getLastCheckedAt()?->format(DATE_ATOM),
            'createdAt' => $connection->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $connection->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
