<?php

namespace App\Connection;

use App\Dto\CreateConnectionRequest;
use App\Dto\UpdateConnectionRequest;
use App\Entity\ClientConnection;
use App\Repository\ClientConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ConnectionManager
{
    public function __construct(
        private readonly CredentialCipher $cipher,
        private readonly ClientDatabaseConnector $connector,
        private readonly ClientConnectionRepository $connections,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateConnectionRequest $request): ClientConnection
    {
        if ($this->connections->nameExists($request->name)) {
            throw new DuplicateConnectionName();
        }

        $credentials = new ClientDatabaseCredentials(
            trim($request->host),
            $request->port,
            trim($request->database),
            trim($request->username),
            $request->password,
        );
        $connection = new ClientConnection(
            $request->name,
            $credentials->host,
            $credentials->port,
            $credentials->database,
            $this->cipher->encrypt($credentials->username),
            $this->cipher->encrypt($credentials->password),
        );
        $connection->recordTest($this->connector->test($credentials));
        $this->entityManager->persist($connection);
        $this->entityManager->flush();

        return $connection;
    }

    public function update(ClientConnection $connection, UpdateConnectionRequest $request): ClientConnection
    {
        if ($this->connections->nameExists($request->name, $connection)) {
            throw new DuplicateConnectionName();
        }

        $username = null === $request->username
            ? $this->cipher->decrypt($connection->getEncryptedUsername())
            : trim($request->username);
        $password = null === $request->password
            ? $this->cipher->decrypt($connection->getEncryptedPassword())
            : $request->password;

        $connection->updateDetails(
            $request->name,
            $request->host,
            $request->port,
            $request->database,
            null === $request->username ? null : $this->cipher->encrypt($username),
            null === $request->password ? null : $this->cipher->encrypt($password),
        );
        $connection->recordTest($this->connector->test(new ClientDatabaseCredentials(
            $connection->getHost(),
            $connection->getPort(),
            $connection->getDatabaseName(),
            $username,
            $password,
        )));
        $this->entityManager->flush();

        return $connection;
    }

    public function test(ClientConnection $connection): ConnectionTestResult
    {
        $result = $this->connector->test(new ClientDatabaseCredentials(
            $connection->getHost(),
            $connection->getPort(),
            $connection->getDatabaseName(),
            $this->cipher->decrypt($connection->getEncryptedUsername()),
            $this->cipher->decrypt($connection->getEncryptedPassword()),
        ));
        $connection->recordTest($result);
        $this->entityManager->flush();

        return $result;
    }
}
