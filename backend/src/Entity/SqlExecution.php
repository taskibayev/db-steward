<?php

namespace App\Entity;

use App\Sql\SqlOperation;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sql_executions')]
class SqlExecution
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Job::class, inversedBy: 'sqlExecution')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Job $job;

    #[ORM\Column(type: 'text')]
    private string $sqlText;

    #[ORM\Column(enumType: SqlOperation::class)]
    private SqlOperation $operation;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $tables;

    #[ORM\Column(nullable: true)]
    private ?int $affectedRows = null;

    /** @param list<string> $tables */
    public function __construct(Job $job, string $sqlText, SqlOperation $operation, array $tables)
    {
        $this->id = Uuid::v7();
        $this->job = $job;
        $this->sqlText = $sqlText;
        $this->operation = $operation;
        $this->tables = $tables;
        $job->attachSqlExecution($this);
    }

    public function recordAffectedRows(int $affectedRows): void
    {
        $this->affectedRows = $affectedRows;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getJob(): Job
    {
        return $this->job;
    }

    public function getSqlText(): string
    {
        return $this->sqlText;
    }

    public function getOperation(): SqlOperation
    {
        return $this->operation;
    }

    /** @return list<string> */
    public function getTables(): array
    {
        return $this->tables;
    }

    public function getAffectedRows(): ?int
    {
        return $this->affectedRows;
    }
}
