<?php

namespace App\Entity;

use App\Permission\PermissionOperation;
use App\Repository\UserTablePermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserTablePermissionRepository::class)]
#[ORM\Table(name: 'user_table_permissions')]
#[ORM\UniqueConstraint(name: 'uniq_access_table_permission', columns: ['access_id', 'table_name'])]
class UserTablePermission
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: UserDatabaseAccess::class, inversedBy: 'tablePermissions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserDatabaseAccess $access;

    #[ORM\Column(length: 64)]
    private string $tableName;

    #[ORM\Column(nullable: true)]
    private ?bool $selectAllowed;

    #[ORM\Column(nullable: true)]
    private ?bool $insertAllowed;

    #[ORM\Column(nullable: true)]
    private ?bool $updateAllowed;

    #[ORM\Column(nullable: true)]
    private ?bool $deleteAllowed;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(UserDatabaseAccess $access, string $tableName)
    {
        $this->id = Uuid::v7();
        $this->access = $access;
        $this->tableName = $tableName;
        $this->selectAllowed = null;
        $this->insertAllowed = null;
        $this->updateAllowed = null;
        $this->deleteAllowed = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAccess(): UserDatabaseAccess
    {
        return $this->access;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getSelectAllowed(): ?bool
    {
        return $this->selectAllowed;
    }

    public function getInsertAllowed(): ?bool
    {
        return $this->insertAllowed;
    }

    public function getUpdateAllowed(): ?bool
    {
        return $this->updateAllowed;
    }

    public function getDeleteAllowed(): ?bool
    {
        return $this->deleteAllowed;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(?bool $select, ?bool $insert, ?bool $update, ?bool $delete): void
    {
        $this->selectAllowed = $select;
        $this->insertAllowed = $insert;
        $this->updateAllowed = $update;
        $this->deleteAllowed = $delete;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function decisionFor(PermissionOperation $operation): ?bool
    {
        return match ($operation) {
            PermissionOperation::Select => $this->selectAllowed,
            PermissionOperation::Insert => $this->insertAllowed,
            PermissionOperation::Update => $this->updateAllowed,
            PermissionOperation::Delete => $this->deleteAllowed,
        };
    }
}
