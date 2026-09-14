<?php

namespace App\Repository;

use App\Entity\ClientConnection;
use App\Entity\User;
use App\Entity\UserDatabaseAccess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserDatabaseAccess> */
final class UserDatabaseAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDatabaseAccess::class);
    }

    public function findAssignment(User $user, ClientConnection $connection): ?UserDatabaseAccess
    {
        return $this->findOneBy(['user' => $user, 'connection' => $connection]);
    }

    /** @return list<UserDatabaseAccess> */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /** @return list<UserDatabaseAccess> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC']);
    }
}
