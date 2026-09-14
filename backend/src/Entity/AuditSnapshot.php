<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'audit_snapshots')]
class AuditSnapshot
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: AuditOperation::class, inversedBy: 'snapshot')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AuditOperation $operation;

    /** @var array<string, array<string, mixed>>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $beforeData;

    /** @var array<string, array<string, mixed>>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $afterData;

    /** @var array<string, array{before: array<string, mixed>|null, after: array<string, mixed>|null}> */
    #[ORM\Column(type: 'json')]
    private array $diff;

    /**
     * @param array<string, array<string, mixed>>|null                                                  $beforeData
     * @param array<string, array<string, mixed>>|null                                                  $afterData
     * @param array<string, array{before: array<string, mixed>|null, after: array<string, mixed>|null}> $diff
     */
    public function __construct(AuditOperation $operation, ?array $beforeData, ?array $afterData, array $diff)
    {
        $this->id = Uuid::v7();
        $this->operation = $operation;
        $this->beforeData = $beforeData;
        $this->afterData = $afterData;
        $this->diff = $diff;
        $operation->attachSnapshot($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOperation(): AuditOperation
    {
        return $this->operation;
    }

    /** @return array<string, array<string, mixed>>|null */
    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    /** @return array<string, array<string, mixed>>|null */
    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    /** @return array<string, array{before: array<string, mixed>|null, after: array<string, mixed>|null}> */
    public function getDiff(): array
    {
        return $this->diff;
    }
}
