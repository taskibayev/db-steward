<?php

namespace App\Job;

use App\Entity\ClientConnection;
use App\Entity\Job;
use App\Entity\SqlExecution;
use App\Entity\User;
use App\Message\ExecuteSqlJob;
use App\Permission\PermissionChecker;
use App\Permission\PermissionOperation;
use App\Sql\ParsedSql;
use App\Sql\SqlRejected;
use App\Sql\SqlValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class SqlJobManager
{
    public function __construct(
        private readonly SqlValidator $validator,
        private readonly PermissionChecker $permissions,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function enqueue(User $actor, ClientConnection $connection, string $sql, bool $writeAcknowledged, string $correlationId): Job
    {
        if (!$this->permissions->canAccessDatabase($actor, $connection)) {
            throw new JobAccessDenied();
        }
        $parsed = $this->validator->validate($sql, $connection->getDatabaseName());
        if ($parsed->operation->isWrite() && !$writeAcknowledged) {
            throw new SqlRejected('write_acknowledgement_required');
        }
        $this->assertPermissions($actor, $connection, $parsed);

        $job = new Job($actor, $connection, $correlationId);
        new SqlExecution($job, $parsed->sql, $parsed->operation, $parsed->allTables());
        $this->entityManager->persist($job);
        $this->entityManager->flush();
        $this->bus->dispatch(new ExecuteSqlJob($job->getId()->toRfc4122()));

        return $job;
    }

    public function assertPermissions(User $actor, ClientConnection $connection, ParsedSql $parsed): void
    {
        foreach ($parsed->writeTables as $table) {
            if (!$this->permissions->isAllowed($actor, $connection, $table, $parsed->operation->permission())) {
                throw new JobAccessDenied();
            }
        }
        foreach ($parsed->readTables as $table) {
            if (!$this->permissions->isAllowed($actor, $connection, $table, PermissionOperation::Select)) {
                throw new JobAccessDenied();
            }
        }
    }
}
