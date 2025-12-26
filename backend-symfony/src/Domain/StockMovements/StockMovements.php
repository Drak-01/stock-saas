<?php

namespace App\Domain\StockMovements;

use App\Domain\MovementTypes\MovementTypes;
use App\Domain\Products\Products;
use App\Domain\Warehouses\Warehouses;
use App\Domain\TenantUsers\TenantUsers;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockMovementsRepository::class)]
#[ORM\Table(name: 'stock_movements')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_movement_date', columns: ['movement_date'])]
#[ORM\Index(name: 'idx_reference', columns: ['reference_type', 'reference_id'])]
class StockMovements
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['stock_movement:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['stock_movement:read'])]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: MovementTypes::class)]
    #[ORM\JoinColumn(name: 'movement_type_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private MovementTypes $movementType;

    #[ORM\ManyToOne(targetEntity: Products::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private Products $product;

    #[ORM\ManyToOne(targetEntity: Warehouses::class)]
    #[ORM\JoinColumn(name: 'from_warehouse_id', referencedColumnName: 'id')]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private ?Warehouses $fromWarehouse = null;

    #[ORM\ManyToOne(targetEntity: Warehouses::class)]
    #[ORM\JoinColumn(name: 'to_warehouse_id', referencedColumnName: 'id')]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private ?Warehouses $toWarehouse = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private string $quantity;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private ?string $unitCost = null;

    #[ORM\Column(type: Types::GUID, nullable: true)]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private ?string $referenceId = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private ?string $referenceType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: TenantUsers::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    #[Groups(['stock_movement:read'])]
    private ?TenantUsers $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    #[Groups(['stock_movement:read', 'stock_movement:write'])]
    private ?\DateTimeImmutable $movementDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['stock_movement:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['stock_movement:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    // Champ virtuel pour la quantité signée (positive ou négative selon l'effet)
    private ?string $signedQuantity = null;

    // Champ virtuel pour le coût total
    private ?string $totalCost = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->movementDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getMovementType(): MovementTypes
    {
        return $this->movementType;
    }

    public function setMovementType(MovementTypes $movementType): self
    {
        $this->movementType = $movementType;
        return $this;
    }

    public function getProduct(): Products
    {
        return $this->product;
    }

    public function setProduct(Products $product): self
    {
        $this->product = $product;

        // Si le coût unitaire n'est pas défini, utiliser le coût du produit
        if ($this->unitCost === null && $product->getCostPrice() !== null) {
            $this->unitCost = (string) $product->getCostPrice();
        }

        return $this;
    }

    public function getFromWarehouse(): ?Warehouses
    {
        return $this->fromWarehouse;
    }

    public function setFromWarehouse(?Warehouses $fromWarehouse): self
    {
        $this->fromWarehouse = $fromWarehouse;
        return $this;
    }

    public function getToWarehouse(): ?Warehouses
    {
        return $this->toWarehouse;
    }

    public function setToWarehouse(?Warehouses $toWarehouse): self
    {
        $this->toWarehouse = $toWarehouse;
        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unitCost;
    }

    public function setUnitCost(?string $unitCost): self
    {
        $this->unitCost = $unitCost;
        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): self
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function setReferenceType(?string $referenceType): self
    {
        $this->referenceType = $referenceType;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getUser(): ?TenantUsers
    {
        return $this->user;
    }

    public function setUser(?TenantUsers $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getMovementDate(): ?\DateTimeImmutable
    {
        return $this->movementDate;
    }

    public function setMovementDate(\DateTimeImmutable $movementDate): self
    {
        $this->movementDate = $movementDate;
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

    // Méthodes utilitaires
    public function getSignedQuantity(): string
    {
        if ($this->signedQuantity === null) {
            $sign = $this->movementType->getEffectSign();
            $this->signedQuantity = bcmul($this->quantity, (string) $sign, 6);
        }
        return $this->signedQuantity;
    }

    public function getTotalCost(): ?string
    {
        if ($this->unitCost === null) {
            return null;
        }

        if ($this->totalCost === null) {
            $this->totalCost = bcmul($this->quantity, $this->unitCost, 4);
        }
        return $this->totalCost;
    }

    public function getWarehouse(): ?Warehouses
    {
        // Pour les mouvements entrants, le stock va vers toWarehouse
        // Pour les mouvements sortants, le stock vient de fromWarehouse
        return $this->movementType->isIncoming() ? $this->toWarehouse : $this->fromWarehouse;
    }

    public function getAffectedWarehouse(): ?Warehouses
    {
        // Retourne l'entrepôt affecté par le mouvement
        if ($this->movementType->isIncoming()) {
            return $this->toWarehouse;
        } elseif ($this->movementType->isOutgoing()) {
            return $this->fromWarehouse;
        } elseif ($this->movementType->isTransfer()) {
            // Pour les transferts, les deux entrepôts sont affectés
            return $this->fromWarehouse; // ou $this->toWarehouse selon le point de vue
        }

        return null;
    }

    public function getMovementDirection(): string
    {
        if ($this->movementType->isTransfer()) {
            return 'transfer';
        }

        return $this->movementType->isIncoming() ? 'in' : 'out';
    }

    public function isIncoming(): bool
    {
        return $this->movementType->isIncoming();
    }

    public function isOutgoing(): bool
    {
        return $this->movementType->isOutgoing();
    }

    public function isTransfer(): bool
    {
        return $this->movementType->isTransfer();
    }

    public function isAdjustment(): bool
    {
        return $this->movementType->isAdjustment();
    }

    public function requiresReference(): bool
    {
        return $this->movementType->isRequiresReference();
    }

    public function hasValidReference(): bool
    {
        if (!$this->requiresReference()) {
            return true;
        }

        return $this->referenceId !== null && $this->referenceType !== null;
    }

    public function getReferenceDescription(): string
    {
        if (!$this->referenceType || !$this->referenceId) {
            return 'N/A';
        }

        $types = [
            'purchase_order' => 'Commande d\'achat',
            'sales_order' => 'Commande client',
            'transfer_order' => 'Ordre de transfert',
            'inventory_adjustment' => 'Ajustement inventaire',
            'return_supplier' => 'Retour fournisseur',
            'return_customer' => 'Retour client',
            'production_order' => 'Ordre de production',
            'work_order' => 'Ordre de travail',
        ];

        $typeName = $types[$this->referenceType] ?? $this->referenceType;
        return sprintf('%s #%s', $typeName, substr($this->referenceId, 0, 8));
    }

    public function getMovementEffect(): string
    {
        $effects = [
            'in' => 'Entrée',
            'out' => 'Sortie',
            'transfer' => 'Transfert',
            'adjustment' => 'Ajustement',
        ];

        return $effects[$this->movementType->getEffect()] ?? $this->movementType->getEffect();
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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function validateMovement(): void
    {
        // Validation des champs requis selon le type de mouvement
        if ($this->movementType->isRequiresReference() && !$this->hasValidReference()) {
            throw new \RuntimeException(sprintf(
                'Le type de mouvement "%s" nécessite une référence',
                $this->movementType->getCode()
            ));
        }

        // Validation des entrepôts
        if ($this->movementType->isTransfer()) {
            if (!$this->fromWarehouse || !$this->toWarehouse) {
                throw new \RuntimeException('Un transfert nécessite un entrepôt source et un entrepôt destination');
            }

            if ($this->fromWarehouse->getId() === $this->toWarehouse->getId()) {
                throw new \RuntimeException('L\'entrepôt source et destination ne peuvent pas être identiques');
            }
        } elseif ($this->movementType->isIncoming()) {
            if (!$this->toWarehouse) {
                throw new \RuntimeException('Une entrée nécessite un entrepôt destination');
            }
        } elseif ($this->movementType->isOutgoing()) {
            if (!$this->fromWarehouse) {
                throw new \RuntimeException('Une sortie nécessite un entrepôt source');
            }
        }

        // Validation de la quantité
        if (bccomp($this->quantity, '0', 6) <= 0) {
            throw new \RuntimeException('La quantité doit être supérieure à zéro');
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'movementType' => $this->movementType->toArray(),
            'product' => [
                'id' => $this->product->getId(),
//                'uuid' => $this->product->getUuid(),
                'sku' => $this->product->getSku(),
                'name' => $this->product->getName(),
            ],
            'fromWarehouse' => $this->fromWarehouse?->toArray(),
            'toWarehouse' => $this->toWarehouse?->toArray(),
            'quantity' => $this->quantity,
            'signedQuantity' => $this->getSignedQuantity(),
            'unitCost' => $this->unitCost,
            'totalCost' => $this->getTotalCost(),
            'referenceId' => $this->referenceId,
            'referenceType' => $this->referenceType,
            'referenceDescription' => $this->getReferenceDescription(),
            'notes' => $this->notes,
            'user' => $this->user?->toArray(),
            'movementDate' => $this->movementDate?->format('Y-m-d H:i:s'),
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'isIncoming' => $this->isIncoming(),
            'isOutgoing' => $this->isOutgoing(),
            'isTransfer' => $this->isTransfer(),
            'movementEffect' => $this->getMovementEffect(),
            'requiresReference' => $this->requiresReference(),
            'hasValidReference' => $this->hasValidReference(),
        ];
    }
}
