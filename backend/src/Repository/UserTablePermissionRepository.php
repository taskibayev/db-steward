<?php

namespace App\Repository;

use App\Entity\UserDatabaseAccess;
use App\Entity\UserTablePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserTablePermission> */
final class UserTablePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTablePermission::class);
    }

    public function findRule(UserDatabaseAccess $access, string $tableName): ?UserTablePermission
    {
        return $this->findOneBy(['access' => $access, 'tableName' => $tableName]);
    }
}
