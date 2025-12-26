<?php

namespace App\Domain\MovementTypes;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MovementTypesRepository::class)]
#[ORM\Table(name: 'movement_types')]
#[ORM\HasLifecycleCallbacks]
class MovementTypes
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['movement_type:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['movement_type:read'])]
    private string $uuid;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    #[Assert\Regex(pattern: '/^[A-Z_]+$/', message: 'Le code doit être en majuscules avec des underscores')]
    #[Groups(['movement_type:read', 'movement_type:write'])]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['movement_type:read', 'movement_type:write'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(['in', 'out', 'transfer', 'adjustment'])]
    #[Groups(['movement_type:read', 'movement_type:write'])]
    private string $effect;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['movement_type:read', 'movement_type:write'])]
    private bool $requiresReference = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['movement_type:read'])]
    private bool $isSystem = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['movement_type:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['movement_type:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);
        return $this;
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

    public function getEffect(): string
    {
        return $this->effect;
    }

    public function setEffect(string $effect): self
    {
        $this->effect = $effect;
        return $this;
    }

    public function isRequiresReference(): bool
    {
        return $this->requiresReference;
    }

    public function setRequiresReference(bool $requiresReference): self
    {
        $this->requiresReference = $requiresReference;
        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): self
    {
        $this->isSystem = $isSystem;
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

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): self
    {
        $this->deletedAt = new \DateTime();
        return $this;
    }

    public function restore(): self
    {
        $this->deletedAt = null;
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Business logic methods
    public function isIncoming(): bool
    {
        return $this->effect === 'in';
    }

    public function isOutgoing(): bool
    {
        return $this->effect === 'out';
    }

    public function isTransfer(): bool
    {
        return $this->effect === 'transfer';
    }

    public function isAdjustment(): bool
    {
        return $this->effect === 'adjustment';
    }

    public function getEffectSign(): int
    {
        return match($this->effect) {
            'in' => 1,
            'out' => -1,
            'transfer' => 0,
            'adjustment' => 0,
            default => 0,
        };
    }

    public static function getSystemTypes(): array
    {
        return [
            [
                'code' => 'PURCHASE_RECEIPT',
                'name' => 'Réception d\'achat',
                'effect' => 'in',
                'requiresReference' => true,
                'isSystem' => true,
            ],
            [
                'code' => 'SALES_SHIPMENT',
                'name' => 'Expédition vente',
                'effect' => 'out',
                'requiresReference' => true,
                'isSystem' => true,
            ],
            [
                'code' => 'TRANSFER_IN',
                'name' => 'Transfert entrant',
                'effect' => 'in',
                'requiresReference' => true,
                'isSystem' => true,
            ],
            [
                'code' => 'TRANSFER_OUT',
                'name' => 'Transfert sortant',
                'effect' => 'out',
                'requiresReference' => true,
                'isSystem' => true,
            ],
            [
                'code' => 'INVENTORY_ADJUSTMENT',
                'name' => 'Ajustement d\'inventaire',
                'effect' => 'adjustment',
                'requiresReference' => false,
                'isSystem' => true,
            ],
            [
                'code' => 'RETURN_TO_SUPPLIER',
                'name' => 'Retour fournisseur',
                'effect' => 'out',
                'requiresReference' => true,
                'isSystem' => true,
            ],
            [
                'code' => 'CUSTOMER_RETURN',
                'name' => 'Retour client',
                'effect' => 'in',
                'requiresReference' => true,
                'isSystem' => true,
            ],
            [
                'code' => 'SCRAP',
                'name' => 'Mise au rebut',
                'effect' => 'out',
                'requiresReference' => true,
                'isSystem' => true,
            ],
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'effect' => $this->effect,
            'requiresReference' => $this->requiresReference,
            'isSystem' => $this->isSystem,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'isDeleted' => $this->isDeleted(),
            'isIncoming' => $this->isIncoming(),
            'isOutgoing' => $this->isOutgoing(),
            'effectSign' => $this->getEffectSign(),
        ];
    }
}
