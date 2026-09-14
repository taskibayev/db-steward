<?php

namespace App\Tests\Functional;

use App\Audit\AuditAction;
use App\Audit\AuditStatus;
use App\Entity\AuditOperation;
use App\Entity\AuditSnapshot;
use App\Entity\ClientConnection;
use App\Entity\User;
use App\Repository\AuditOperationRepository;
use App\Tests\DatabaseWebTestCase;
use App\User\UserRole;

final class AuditPersistenceTest extends DatabaseWebTestCase
{
    public function testStoresFinalOperationWithTypedSnapshots(): void
    {
        static::createClient();
        $entityManager = $this->resetDatabase();
        $actor = new User('manager@example.com', UserRole::Manager);
        $connection = new ClientConnection('Client', 'db', 3306, 'client', 'encrypted-user', 'encrypted-password');
        $primaryKey = ['id' => ['type' => 'int', 'value' => 42]];
        $before = ['name' => ['type' => 'varchar(100)', 'value' => 'Before']];
        $after = ['name' => ['type' => 'varchar(100)', 'value' => 'After']];
        $diff = ['name' => ['before' => $before['name'], 'after' => $after['name']]];
        $operation = new AuditOperation(
            $actor,
            $connection,
            'customers',
            AuditAction::Update,
            $primaryKey,
            AuditStatus::Succeeded,
            1,
            '01994e15-6c00-7000-8000-000000000001',
        );
        $snapshot = new AuditSnapshot($operation, $before, $after, $diff);
        foreach ([$actor, $connection, $operation, $snapshot] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $entityManager->clear();

        $stored = static::getContainer()->get(AuditOperationRepository::class)->find($operation->getId());
        self::assertInstanceOf(AuditOperation::class, $stored);
        self::assertSame(AuditStatus::Succeeded, $stored->getStatus());
        self::assertSame($primaryKey, $stored->getPrimaryKey());
        $storedSnapshot = $stored->getSnapshot();
        self::assertInstanceOf(AuditSnapshot::class, $storedSnapshot);
        self::assertSame($before, $storedSnapshot->getBeforeData());
        self::assertSame($after, $storedSnapshot->getAfterData());
        self::assertSame($diff, $storedSnapshot->getDiff());
    }
}
