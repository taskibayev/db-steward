<?php

namespace App\Undo;

use App\Audit\AuditAction;
use App\Audit\AuditStatus;
use App\Audit\TypedSnapshot;
use App\Connection\ClientDatabaseCredentials;
use App\Connection\CredentialCipher;
use App\Crud\ClientRowWriter;
use App\Crud\CrudAccessDenied;
use App\Crud\RowMutationFailed;
use App\Crud\RowMutationRejected;
use App\Entity\AuditOperation;
use App\Entity\AuditSnapshot;
use App\Entity\User;
use App\Permission\PermissionChecker;
use App\Permission\PermissionOperation;
use App\Repository\AuditOperationRepository;
use App\User\UserRole;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class UndoManager
{
    public function __construct(
        private readonly ClientRowWriter $writer,
        private readonly PermissionChecker $permissions,
        private readonly CredentialCipher $cipher,
        private readonly TypedSnapshot $snapshots,
        private readonly AuditOperationRepository $operations,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function canUndo(User $actor, AuditOperation $operation): bool
    {
        if (AuditStatus::Succeeded !== $operation->getStatus() || $operation->getAction()->isUndo() || null === $operation->getSnapshot()) {
            return false;
        }
        if (UserRole::Manager === $actor->getRole() && !$actor->getId()->equals($operation->getActor()->getId())) {
            return false;
        }
        $permission = $this->reversePermission($operation->getAction());

        return null !== $permission && !$this->operations->wasSuccessfullyUndone($operation)
            && $this->permissions->isAllowed($actor, $operation->getConnection(), $operation->getTableName(), $permission);
    }

    public function undo(User $actor, AuditOperation $original, string $correlationId): AuditOperation
    {
        return $this->entityManager->wrapInTransaction(function () use ($actor, $original, $correlationId): AuditOperation {
            $this->entityManager->lock($original, LockMode::PESSIMISTIC_WRITE);

            return $this->executeUndo($actor, $original, $correlationId);
        });
    }

    private function executeUndo(User $actor, AuditOperation $original, string $correlationId): AuditOperation
    {
        if (AuditStatus::Succeeded !== $original->getStatus() || $original->getAction()->isUndo() || null === $original->getSnapshot()) {
            throw new RowMutationRejected('operation_not_undoable');
        }
        if (UserRole::Manager === $actor->getRole() && !$actor->getId()->equals($original->getActor()->getId())) {
            throw new CrudAccessDenied();
        }

        $permission = $this->reversePermission($original->getAction());
        $action = $this->undoAction($original->getAction());
        if (null === $permission || null === $action) {
            throw new RowMutationRejected('operation_not_undoable');
        }
        if (!$this->permissions->isAllowed($actor, $original->getConnection(), $original->getTableName(), $permission)) {
            throw new CrudAccessDenied();
        }
        if ($this->operations->wasSuccessfullyUndone($original)) {
            return $this->recordConflict($actor, $original, $action, $correlationId, 'operation_already_undone');
        }

        $snapshot = $original->getSnapshot();
        $before = $snapshot->getBeforeData();
        $after = $snapshot->getAfterData();
        $primaryKey = $this->snapshots->decode($original->getPrimaryKey());
        $credentials = $this->credentials($original);

        try {
            $result = match ($original->getAction()) {
                AuditAction::Insert => $this->writer->undoInsert($credentials, $original->getTableName(), $primaryKey, $after ?? throw new RowMutationRejected('snapshot_missing')),
                AuditAction::Update => $this->writer->undoUpdate($credentials, $original->getTableName(), $primaryKey, $before ?? throw new RowMutationRejected('snapshot_missing'), $after ?? throw new RowMutationRejected('snapshot_missing')),
                AuditAction::Delete => $this->writer->undoDelete($credentials, $original->getTableName(), $primaryKey, $before ?? throw new RowMutationRejected('snapshot_missing')),
                default => throw new RowMutationRejected('operation_not_undoable'),
            };
        } catch (RowMutationRejected $exception) {
            return $this->recordConflict($actor, $original, $action, $correlationId, $exception->errorCode);
        } catch (RowMutationFailed) {
            $failed = $this->operation($actor, $original, $action, AuditStatus::Failed, $correlationId, 'client_write_failed');
            $this->save($failed);

            return $failed;
        }

        $audit = $this->operation($actor, $original, $action, AuditStatus::Succeeded, $correlationId);
        $encodedBefore = null === $result->before ? null : $this->snapshots->encode($result->table, $result->before);
        $encodedAfter = null === $result->after ? null : $this->snapshots->encode($result->table, $result->after);
        new AuditSnapshot($audit, $encodedBefore, $encodedAfter, $this->snapshots->diff($encodedBefore, $encodedAfter));
        $this->save($audit);

        return $audit;
    }

    private function recordConflict(User $actor, AuditOperation $original, AuditAction $action, string $correlationId, string $code): AuditOperation
    {
        $operation = $this->operation($actor, $original, $action, AuditStatus::Conflict, $correlationId, $code);
        $this->save($operation);

        return $operation;
    }

    private function operation(User $actor, AuditOperation $original, AuditAction $action, AuditStatus $status, string $correlationId, ?string $error = null): AuditOperation
    {
        return new AuditOperation($actor, $original->getConnection(), $original->getTableName(), $action, $original->getPrimaryKey(), $status, AuditStatus::Succeeded === $status ? 1 : 0, $correlationId, $error, $original);
    }

    private function save(AuditOperation $operation): void
    {
        $this->entityManager->persist($operation);
        $this->entityManager->flush();
    }

    private function reversePermission(AuditAction $action): ?PermissionOperation
    {
        return match ($action) {
            AuditAction::Insert => PermissionOperation::Delete,
            AuditAction::Update => PermissionOperation::Update,
            AuditAction::Delete => PermissionOperation::Insert,
            default => null,
        };
    }

    private function undoAction(AuditAction $action): ?AuditAction
    {
        return match ($action) {
            AuditAction::Insert => AuditAction::UndoInsert,
            AuditAction::Update => AuditAction::UndoUpdate,
            AuditAction::Delete => AuditAction::UndoDelete,
            default => null,
        };
    }

    private function credentials(AuditOperation $operation): ClientDatabaseCredentials
    {
        $connection = $operation->getConnection();

        return new ClientDatabaseCredentials($connection->getHost(), $connection->getPort(), $connection->getDatabaseName(), $this->cipher->decrypt($connection->getEncryptedUsername()), $this->cipher->decrypt($connection->getEncryptedPassword()));
    }
}
