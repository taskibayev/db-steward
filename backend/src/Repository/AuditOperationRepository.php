<?php

namespace App\Repository;

use App\Entity\AuditOperation;
use App\Entity\ClientConnection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditOperation> */
final class AuditOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditOperation::class);
    }

    /** @return list<AuditOperation> */
    public function findPage(ClientConnection $connection, int $page, int $pageSize): array
    {
        return $this->findBy(
            ['connection' => $connection],
            ['createdAt' => 'DESC'],
            $pageSize,
            ($page - 1) * $pageSize,
        );
    }

    public function countForConnection(ClientConnection $connection): int
    {
        return $this->count(['connection' => $connection]);
    }
}
