<?php

namespace App\Entity;

use App\Job\JobStatus;
use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\Table(name: 'jobs')]
#[ORM\Index(name: 'idx_jobs_actor_created', columns: ['actor_id', 'created_at'])]
#[ORM\Index(name: 'idx_jobs_connection_created', columns: ['connection_id', 'created_at'])]
class Job
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

    #[ORM\Column(enumType: JobStatus::class)]
    private JobStatus $status = JobStatus::Queued;

    #[ORM\Column(length: 64)]
    private string $correlationId;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\OneToOne(targetEntity: SqlExecution::class, mappedBy: 'job', cascade: ['persist'])]
    private ?SqlExecution $sqlExecution = null;

    public function __construct(User $actor, ClientConnection $connection, string $correlationId)
    {
        $this->id = Uuid::v7();
        $this->actor = $actor;
        $this->connection = $connection;
        $this->correlationId = $correlationId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function attachSqlExecution(SqlExecution $execution): void
    {
        if (null !== $this->sqlExecution) {
            throw new \LogicException('A SQL execution is already attached.');
        }
        $this->sqlExecution = $execution;
    }

    public function start(): bool
    {
        if (JobStatus::Cancelled === $this->status) {
            return false;
        }
        if (JobStatus::Queued !== $this->status) {
            return false;
        }
        $this->status = JobStatus::Running;
        $this->startedAt = new \DateTimeImmutable();

        return true;
    }

    public function requestCancellation(): bool
    {
        if (JobStatus::Queued === $this->status) {
            $this->status = JobStatus::Cancelled;
            $this->completedAt = new \DateTimeImmutable();

            return true;
        } elseif (JobStatus::Running === $this->status) {
            $this->status = JobStatus::CancelRequested;
        }

        return false;
    }

    public function succeed(): void
    {
        if (!in_array($this->status, [JobStatus::Running, JobStatus::CancelRequested], true)) {
            throw new \LogicException('Only a running job can succeed.');
        }
        $this->status = JobStatus::Succeeded;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function fail(string $errorCode): void
    {
        if (!in_array($this->status, [JobStatus::Running, JobStatus::CancelRequested], true)) {
            throw new \LogicException('Only a running job can fail.');
        }
        $this->status = JobStatus::Failed;
        $this->errorCode = $errorCode;
        $this->completedAt = new \DateTimeImmutable();
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

    public function getStatus(): JobStatus
    {
        return $this->status;
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

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getSqlExecution(): ?SqlExecution
    {
        return $this->sqlExecution;
    }
}
