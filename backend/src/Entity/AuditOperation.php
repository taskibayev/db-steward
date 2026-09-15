<?php

namespace App\Entity;

use App\Audit\AuditAction;
use App\Audit\AuditStatus;
use App\Repository\AuditOperationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditOperationRepository::class)]
#[ORM\Table(name: 'audit_operations')]
#[ORM\Index(name: 'idx_audit_connection_created', columns: ['connection_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_actor_created', columns: ['actor_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_undoes_status', columns: ['undoes_operation_id', 'status'])]
class AuditOperation
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $actor;

    #[ORM\ManyToOne(targetEntity: ClientConnection::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ClientConnection $connection;

    #[ORM\Column(length: 64)]
    private string $tableName;

    #[ORM\Column(enumType: AuditAction::class)]
    private AuditAction $action;

    /** @var array<string, array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $primaryKey;

    #[ORM\Column(enumType: AuditStatus::class)]
    private AuditStatus $status;

    #[ORM\Column]
    private int $affectedRows;

    #[ORM\Column(length: 64)]
    private string $correlationId;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $errorCode;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(targetEntity: AuditSnapshot::class, mappedBy: 'operation', cascade: ['persist'])]
    private ?AuditSnapshot $snapshot = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?self $undoesOperation;

    /** @param array<string, array<string, mixed>> $primaryKey */
    public function __construct(
        User $actor,
        ClientConnection $connection,
        string $tableName,
        AuditAction $action,
        array $primaryKey,
        AuditStatus $status,
        int $affectedRows,
        string $correlationId,
        ?string $errorCode = null,
        ?self $undoesOperation = null,
    ) {
        $this->id = Uuid::v7();
        $this->actor = $actor;
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->action = $action;
        $this->primaryKey = $primaryKey;
        $this->status = $status;
        $this->affectedRows = $affectedRows;
        $this->correlationId = $correlationId;
        $this->errorCode = $errorCode;
        $this->createdAt = new \DateTimeImmutable();
        $this->undoesOperation = $undoesOperation;
    }

    public function attachSnapshot(AuditSnapshot $snapshot): void
    {
        if (null !== $this->snapshot) {
            throw new \LogicException('An audit snapshot is already attached.');
        }
        $this->snapshot = $snapshot;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getActor(): User
    {
        return $this->actor;
    }

    public function getConnection(): ClientConnection
    {
        return $this->connection;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getAction(): AuditAction
    {
        return $this->action;
    }

    /** @return array<string, array<string, mixed>> */
    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    public function getStatus(): AuditStatus
    {
        return $this->status;
    }

    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSnapshot(): ?AuditSnapshot
    {
        return $this->snapshot;
    }

    public function getUndoesOperation(): ?self
    {
        return $this->undoesOperation;
    }
}
