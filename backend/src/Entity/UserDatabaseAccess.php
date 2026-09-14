<?php

namespace App\Entity;

use App\Permission\AccessMode;
use App\Repository\UserDatabaseAccessRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserDatabaseAccessRepository::class)]
#[ORM\Table(name: 'user_database_access')]
#[ORM\UniqueConstraint(name: 'uniq_user_database_access', columns: ['user_id', 'connection_id'])]
class UserDatabaseAccess
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: ClientConnection::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ClientConnection $connection;

    #[ORM\Column(enumType: AccessMode::class)]
    private AccessMode $mode;

    /** @var Collection<int, UserTablePermission> */
    #[ORM\OneToMany(targetEntity: UserTablePermission::class, mappedBy: 'access', cascade: ['persist'], orphanRemoval: true)]
    private Collection $tablePermissions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, ClientConnection $connection, AccessMode $mode)
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->connection = $connection;
        $this->mode = $mode;
        $this->tablePermissions = new ArrayCollection();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getConnection(): ClientConnection
    {
        return $this->connection;
    }

    public function getMode(): AccessMode
    {
        return $this->mode;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, UserTablePermission> */
    public function getTablePermissions(): Collection
    {
        return $this->tablePermissions;
    }

    public function setMode(AccessMode $mode): void
    {
        $this->mode = $mode;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
