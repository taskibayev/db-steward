<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\User\UserRole;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 254)]
    private string $email;

    #[ORM\Column(enumType: UserRole::class)]
    private UserRole $role;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /** @var Collection<int, OAuthIdentity> */
    #[ORM\OneToMany(targetEntity: OAuthIdentity::class, mappedBy: 'user', cascade: ['persist'], orphanRemoval: true)]
    private Collection $oauthIdentities;

    public function __construct(string $email, UserRole $role)
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v7();
        $this->email = self::normalizeEmail($email);
        $this->role = $role;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->oauthIdentities = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        assert('' !== $this->email);

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return [$this->role->securityRole()];
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $this->updatedAt = $this->lastLoginAt;
    }

    public function addOAuthIdentity(OAuthIdentity $identity): void
    {
        if (!$this->oauthIdentities->contains($identity)) {
            $this->oauthIdentities->add($identity);
        }
    }

    public function eraseCredentials(): void
    {
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
