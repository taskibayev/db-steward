<?php

namespace App\Repository;

use App\Entity\ClientConnection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClientConnection> */
final class ClientConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientConnection::class);
    }

    /** @return list<ClientConnection> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC']);
    }

    public function nameExists(string $name, ?ClientConnection $except = null): bool
    {
        $found = $this->findOneBy(['name' => trim($name)]);

        return null !== $found && $found !== $except;
    }
}
