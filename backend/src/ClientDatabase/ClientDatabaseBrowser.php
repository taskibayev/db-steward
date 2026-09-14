<?php

namespace App\ClientDatabase;

use App\Connection\ClientDatabaseCredentials;
use App\Connection\CredentialCipher;
use App\Dto\BrowseRowsQuery;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Permission\PermissionChecker;
use App\Permission\PermissionOperation;

final class ClientDatabaseBrowser
{
    public function __construct(
        private readonly ClientDatabaseReader $reader,
        private readonly CredentialCipher $cipher,
        private readonly PermissionChecker $permissions,
    ) {
    }

    /** @return list<SchemaTable> */
    public function schema(User $user, ClientConnection $connection): array
    {
        if (!$this->permissions->canAccessDatabase($user, $connection)) {
            throw new DatabaseAccessDenied();
        }

        return array_values(array_filter(
            $this->reader->schema($this->credentials($connection)),
            fn (SchemaTable $table): bool => $this->permissions->isAllowed(
                $user,
                $connection,
                $table->name,
                PermissionOperation::Select,
            ),
        ));
    }

    public function rows(User $user, ClientConnection $connection, string $table, BrowseRowsQuery $query): RowPage
    {
        if (!$this->permissions->isAllowed($user, $connection, $table, PermissionOperation::Select)) {
            throw new DatabaseAccessDenied();
        }

        return $this->reader->rows($this->credentials($connection), $table, $query);
    }

    private function credentials(ClientConnection $connection): ClientDatabaseCredentials
    {
        return new ClientDatabaseCredentials(
            $connection->getHost(),
            $connection->getPort(),
            $connection->getDatabaseName(),
            $this->cipher->decrypt($connection->getEncryptedUsername()),
            $this->cipher->decrypt($connection->getEncryptedPassword()),
        );
    }
}
