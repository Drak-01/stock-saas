<?php

namespace App\Domain\GlobalUsers;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GlobalUsersRepository::class)]
#[ORM\Table(
    name: 'global_users',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'email_company_unique', columns: ['email', 'company_id'])
    ]
)]
#[ORM\HasLifecycleCallbacks]
class GlobalUsers
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(name: 'company_id', type: Types::GUID)]
    #[Assert\NotBlank]
    private string $companyId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(name: 'password_hash', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private string $passwordHash;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'last_login', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct(string $companyId = '', string $email = '', string $passwordHash = '')
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->companyId = $companyId;
        $this->email = mb_strtolower($email);
        $this->passwordHash = $passwordHash;
        $this->isActive = true;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCompanyId(): string
    {
        return $this->companyId;
    }

    public function setCompanyId(string $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower($email);
        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): self
    {
        $this->passwordHash = $hash;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $active): self
    {
        $this->isActive = $active;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $dt): self
    {
        $this->lastLogin = $dt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $dt): self
    {
        $this->deletedAt = $dt;
        return $this;
    }

    public function recordLogin(): self
    {
        $this->lastLogin = new \DateTime();
        return $this;
    }

    public function softDelete(): self
    {
        $this->deletedAt = new \DateTime();
        $this->isActive = false;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    #[ORM\PrePersist]
    public function prePersistTimestamps(): void
    {
        $nowImmutable = new \DateTimeImmutable();
        $this->createdAt = $this->createdAt ?? $nowImmutable;
        $this->updatedAt = $this->updatedAt ?? new \DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'email' => $this->email,
            'is_active' => $this->isActive,
            'last_login' => $this->lastLogin?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
