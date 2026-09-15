<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notifications_recipient_created', columns: ['recipient_id', 'created_at'])]
#[ORM\Index(name: 'idx_notifications_recipient_read', columns: ['recipient_id', 'read_at'])]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(length: 64)]
    private string $type;

    /** @var array<string, bool|float|int|string|null> */
    #[ORM\Column(type: 'json')]
    private array $data;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    /** @param array<string, bool|float|int|string|null> $data */
    public function __construct(User $recipient, string $type, array $data)
    {
        $this->id = Uuid::v7();
        $this->recipient = $recipient;
        $this->type = $type;
        $this->data = $data;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function markRead(): void
    {
        $this->readAt ??= new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return array<string, bool|float|int|string|null> */
    public function getData(): array
    {
        return $this->data;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }
}
