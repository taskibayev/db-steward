<?php

namespace App\Crud;

use App\Audit\AuditAction;
use App\Audit\AuditStatus;
use App\Audit\TypedSnapshot;
use App\Connection\ClientDatabaseCredentials;
use App\Connection\CredentialCipher;
use App\Entity\AuditOperation;
use App\Entity\AuditSnapshot;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Permission\PermissionChecker;
use App\Permission\PermissionOperation;
use Doctrine\ORM\EntityManagerInterface;

final class CrudManager
{
    public function __construct(
        private readonly ClientRowWriter $writer,
        private readonly PermissionChecker $permissions,
        private readonly CredentialCipher $cipher,
        private readonly TypedSnapshot $snapshots,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<string, mixed> $values */
    public function insert(User $actor, ClientConnection $connection, string $table, array $values, string $correlationId): AuditOperation
    {
        return $this->execute($actor, $connection, $table, AuditAction::Insert, [], $correlationId, fn (): RowMutationResult => $this->writer->insert($this->credentials($connection), $table, $values));
    }

    /**
     * @param array<string, mixed> $primaryKey
     * @param array<string, mixed> $values
     */
    public function update(User $actor, ClientConnection $connection, string $table, array $primaryKey, array $values, string $correlationId): AuditOperation
    {
        return $this->execute($actor, $connection, $table, AuditAction::Update, $primaryKey, $correlationId, fn (): RowMutationResult => $this->writer->update($this->credentials($connection), $table, $primaryKey, $values));
    }

    /** @param array<string, mixed> $primaryKey */
    public function delete(User $actor, ClientConnection $connection, string $table, array $primaryKey, string $correlationId): AuditOperation
    {
        return $this->execute($actor, $connection, $table, AuditAction::Delete, $primaryKey, $correlationId, fn (): RowMutationResult => $this->writer->delete($this->credentials($connection), $table, $primaryKey));
    }

    /**
     * @param array<string, mixed>          $requestedPrimaryKey
     * @param callable(): RowMutationResult $operation
     */
    private function execute(User $actor, ClientConnection $connection, string $table, AuditAction $action, array $requestedPrimaryKey, string $correlationId, callable $operation): AuditOperation
    {
        $permission = PermissionOperation::from($action->value);
        if (!$this->permissions->isAllowed($actor, $connection, $table, $permission)) {
            throw new CrudAccessDenied();
        }

        try {
            $result = $operation();
        } catch (RowMutationRejected $exception) {
            $failed = new AuditOperation(
                $actor,
                $connection,
                $table,
                $action,
                $this->unknownPrimaryKey($requestedPrimaryKey),
                AuditStatus::Conflict,
                0,
                $correlationId,
                $exception->errorCode,
            );
            $this->entityManager->persist($failed);
            $this->entityManager->flush();
            throw $exception;
        } catch (RowMutationFailed $exception) {
            $failed = new AuditOperation(
                $actor,
                $connection,
                $table,
                $action,
                $this->unknownPrimaryKey($requestedPrimaryKey),
                AuditStatus::Failed,
                0,
                $correlationId,
                'client_write_failed',
            );
            $this->entityManager->persist($failed);
            $this->entityManager->flush();
            throw $exception;
        }

        $before = null === $result->before ? null : $this->snapshots->encode($result->table, $result->before);
        $after = null === $result->after ? null : $this->snapshots->encode($result->table, $result->after);
        $encodedPrimaryKey = [];
        foreach ($result->primaryKey as $column => $value) {
            $encodedPrimaryKey[$column] = $after[$column] ?? $before[$column] ?? ['type' => 'unknown', 'value' => $value];
        }
        $audit = new AuditOperation($actor, $connection, $table, $action, $encodedPrimaryKey, AuditStatus::Succeeded, $result->affectedRows, $correlationId);
        new AuditSnapshot($audit, $before, $after, $this->snapshots->diff($before, $after));
        $this->entityManager->persist($audit);
        $this->entityManager->flush();

        return $audit;
    }

    /**
     * @param array<string, mixed> $primaryKey
     *
     * @return array<string, array<string, mixed>>
     */
    private function unknownPrimaryKey(array $primaryKey): array
    {
        $result = [];
        foreach ($primaryKey as $column => $value) {
            $result[$column] = ['type' => 'unknown', 'value' => $value];
        }

        return $result;
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
