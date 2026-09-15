<?php

namespace App\Repository;

use App\Entity\Job;
use App\Entity\TemporaryQueryResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TemporaryQueryResult> */
final class TemporaryQueryResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TemporaryQueryResult::class);
    }

    public function findActiveForJob(Job $job): ?TemporaryQueryResult
    {
        $result = $this->findOneBy(['job' => $job]);

        return $result instanceof TemporaryQueryResult && $result->getExpiresAt() > new \DateTimeImmutable() ? $result : null;
    }

    public function purgeExpired(): int
    {
        return $this->createQueryBuilder('result')->delete()
            ->andWhere('result.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()->execute();
    }
}
