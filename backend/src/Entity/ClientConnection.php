<?php

namespace App\Entity;

use App\Connection\ConnectionStatus;
use App\Repository\ClientConnectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ClientConnectionRepository::class)]
#[ORM\Table(name: 'client_connections')]
#[ORM\UniqueConstraint(name: 'uniq_client_connections_name', columns: ['name'])]
class ClientConnection
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $host;

    #[ORM\Column]
    private int $port;

    #[ORM\Column(length: 64)]
    private string $databaseName;

    #[ORM\Column(type: 'text')]
    private string $encryptedUsername;

    #[ORM\Column(type: 'text')]
    private string $encryptedPassword;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(enumType: ConnectionStatus::class)]
    private ConnectionStatus $status = ConnectionStatus::Untested;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serverVersion = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lastErrorCode = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastCheckedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $host, int $port, string $databaseName, string $encryptedUsername, string $encryptedPassword)
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v7();
        $this->name = trim($name);
        $this->host = trim($host);
        $this->port = $port;
        $this->databaseName = trim($databaseName);
        $this->encryptedUsername = $encryptedUsername;
        $this->encryptedPassword = $encryptedPassword;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function getEncryptedUsername(): string
    {
        return $this->encryptedUsername;
    }

    public function getEncryptedPassword(): string
    {
        return $this->encryptedPassword;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getStatus(): ConnectionStatus
    {
        return $this->status;
    }

    public function getServerVersion(): ?string
    {
        return $this->serverVersion;
    }

    public function getLastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    public function getLastCheckedAt(): ?\DateTimeImmutable
    {
        return $this->lastCheckedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateDetails(string $name, string $host, int $port, string $databaseName, ?string $encryptedUsername, ?string $encryptedPassword): void
    {
        $this->name = trim($name);
        $this->host = trim($host);
        $this->port = $port;
        $this->databaseName = trim($databaseName);
        if (null !== $encryptedUsername) {
            $this->encryptedUsername = $encryptedUsername;
        }
        if (null !== $encryptedPassword) {
            $this->encryptedPassword = $encryptedPassword;
        }
        $this->status = ConnectionStatus::Untested;
        $this->serverVersion = null;
        $this->lastErrorCode = null;
        $this->lastCheckedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordTest(\App\Connection\ConnectionTestResult $result): void
    {
        $this->status = $result->successful ? ConnectionStatus::Reachable : ConnectionStatus::Unreachable;
        $this->serverVersion = $result->serverVersion;
        $this->lastErrorCode = $result->errorCode;
        $this->lastCheckedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->lastCheckedAt;
    }
}
