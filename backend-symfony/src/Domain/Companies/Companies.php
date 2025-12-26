<?php

namespace App\Domain\Companies;

use App\Repository\CompaniesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompaniesRepository::class)]
#[ORM\Table(name: 'companies')]
#[ORM\HasLifecycleCallbacks]
class Companies
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['company:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['company:read'])]
    private string $uuid;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['company:read', 'company:write'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 100)]
    #[Assert\Regex(pattern: '/^[a-z0-9\-]+$/', message: 'Le sous-domaine ne peut contenir que des lettres minuscules, chiffres et tirets')]
    #[Groups(['company:read', 'company:write'])]
    private string $subdomain;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice([
        'retail', 'wholesale', 'manufacturing',
        'ecommerce', 'service', 'restaurant', 'other'
    ])]
    #[Groups(['company:read', 'company:write'])]
    private string $companyType;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'basic'])]
    #[Assert\Choice(['trial', 'basic', 'pro', 'enterprise'])]
    #[Groups(['company:read', 'company:write'])]
    private string $planType = 'basic';

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'active'])]
    #[Assert\Choice(['pending', 'active', 'suspended', 'cancelled'])]
    #[Groups(['company:read'])]
    private string $status = 'active';

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['company:read', 'company:write'])]
    private array $settings = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['company:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['company:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();

        $this->settings = [
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'language' => 'fr',
            'tax_enabled' => true,
            'tax_rate' => 20.0,
            'inventory_method' => 'fifo',
            'low_stock_threshold' => 10,
            'default_warehouse_id' => null,
            'notifications' => [
                'low_stock' => true,
                'expiry_alert' => true,
                'daily_report' => false
            ]
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function setSubdomain(string $subdomain): self
    {
        $this->subdomain = strtolower($subdomain);
        return $this;
    }

    public function getCompanyType(): string
    {
        return $this->companyType;
    }

    public function setCompanyType(string $companyType): self
    {
        $this->companyType = $companyType;
        return $this;
    }

    public function getPlanType(): string
    {
        return $this->planType;
    }

    public function setPlanType(string $planType): self
    {
        $this->planType = $planType;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): self
    {
        $this->settings = array_merge($this->settings, $settings);
        return $this;
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, $value): self
    {
        $this->settings[$key] = $value;
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

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): self
    {
        $this->deletedAt = new \DateTime();
        $this->status = 'cancelled';
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Helper methods
    public function getFullDomain(): string
    {
        return $this->subdomain . '.' . ($_ENV['APP_DOMAIN'] ?? 'localhost');
    }

    public function getDatabaseName(): string
    {
        return 'tenant_' . $this->id;
    }

    public function getSchemaName(): string
    {
        return 'tenant_' . $this->subdomain;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'subdomain' => $this->subdomain,
            'companyType' => $this->companyType,
            'planType' => $this->planType,
            'status' => $this->status,
            'settings' => $this->settings,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'isActive' => $this->isActive(),
            'isDeleted' => $this->isDeleted()
        ];
    }
}
