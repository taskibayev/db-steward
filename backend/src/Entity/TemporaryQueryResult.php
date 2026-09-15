<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'temporary_query_results')]
#[ORM\Index(name: 'idx_query_results_expires', columns: ['expires_at'])]
class TemporaryQueryResult
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Job::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Job $job;

    /** @var list<string> */
    #[ORM\Column(name: 'column_names', type: 'json')]
    private array $columns;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(name: 'result_rows', type: 'json')]
    private array $rows;

    #[ORM\Column]
    private int $rowCount;

    #[ORM\Column]
    private bool $truncated;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    /**
     * @param list<string>               $columns
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(Job $job, array $columns, array $rows, bool $truncated)
    {
        $this->id = Uuid::v7();
        $this->job = $job;
        $this->columns = $columns;
        $this->rows = $rows;
        $this->rowCount = count($rows);
        $this->truncated = $truncated;
        $this->expiresAt = new \DateTimeImmutable('+1 hour');
    }

    /** @return list<string> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getJob(): Job
    {
        return $this->job;
    }

    /** @return list<array<string, mixed>> */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
