<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'oauth_identities')]
#[ORM\UniqueConstraint(name: 'uniq_oauth_provider_subject', columns: ['provider', 'subject'])]
#[ORM\UniqueConstraint(name: 'uniq_oauth_provider_user', columns: ['provider', 'user_id'])]
class OAuthIdentity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'oauthIdentities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 32)]
    private string $provider;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastUsedAt;

    public function __construct(User $user, string $provider, string $subject)
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->provider = $provider;
        $this->subject = $subject;
        $this->createdAt = $now;
        $this->lastUsedAt = $now;
        $user->addOAuthIdentity($this);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): \DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touch(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
    }
}
