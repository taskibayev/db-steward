<?php

namespace App\Repository;

use App\Entity\Job;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Job> */
final class JobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    /** @return list<Job> */
    public function findPage(User $user, bool $all, int $page, int $pageSize): array
    {
        return $this->findBy($all ? [] : ['actor' => $user], ['createdAt' => 'DESC'], $pageSize, ($page - 1) * $pageSize);
    }

    public function countVisible(User $user, bool $all): int
    {
        return $this->count($all ? [] : ['actor' => $user]);
    }
}
