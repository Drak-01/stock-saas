<?php

namespace App\Domain\ProductionOrders;

use App\Domain\BillsOfMaterial\BillsOfMaterial;
use App\Domain\Warehouses\Warehouses;
use App\Domain\TenantUsers\TenantUsers;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductionOrdersRepository::class)]
#[ORM\Table(name: 'production_orders')]
#[ORM\HasLifecycleCallbacks]
class ProductionOrders
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['production_order:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['production_order:read'])]
    private string $uuid;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Groups(['production_order:read'])]
    private string $poNumber;

    #[ORM\ManyToOne(targetEntity: BillsOfMaterial::class)]
    #[ORM\JoinColumn(name: 'bom_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['production_order:read', 'production_order:write'])]
    private BillsOfMaterial $bom;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'planned'])]
    #[Assert\Choice([
        'planned', 'reserved', 'in_progress', 'completed',
        'partially_completed', 'cancelled', 'closed'
    ])]
    #[Groups(['production_order:read', 'production_order:write'])]
    private string $status = 'planned';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['production_order:read', 'production_order:write'])]
    private string $quantityToProduce;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, options: ['default' => 0])]
    #[Groups(['production_order:read'])]
    private string $quantityProduced = '0';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['production_order:read', 'production_order:write'])]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['production_order:read', 'production_order:write'])]
    private ?\DateTimeInterface $completionDate = null;

    #[ORM\ManyToOne(targetEntity: Warehouses::class)]
    #[ORM\JoinColumn(name: 'source_warehouse_id', referencedColumnName: 'id')]
    #[Groups(['production_order:read', 'production_order:write'])]
    private ?Warehouses $sourceWarehouse = null;

    #[ORM\ManyToOne(targetEntity: Warehouses::class)]
    #[ORM\JoinColumn(name: 'destination_warehouse_id', referencedColumnName: 'id')]
    #[Groups(['production_order:read', 'production_order:write'])]
    private ?Warehouses $destinationWarehouse = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['production_order:read', 'production_order:write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: TenantUsers::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false)]
    #[Groups(['production_order:read'])]
    private TenantUsers $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['production_order:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['production_order:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Champs virtuels
    private ?string $completionPercentage = null;
    private ?string $remainingQuantity = null;
    private ?array $requiredComponents = null;
    private ?array $consumedComponents = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->generatePoNumber();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getPoNumber(): string
    {
        return $this->poNumber;
    }

    private function generatePoNumber(): void
    {
        $date = new \DateTime();
        $this->poNumber = 'PROD-' . $date->format('Ymd') . '-' . strtoupper(substr($this->uuid, 0, 8));
    }

    public function getBom(): BillsOfMaterial
    {
        return $this->bom;
    }

    public function setBom(BillsOfMaterial $bom): self
    {
        $this->bom = $bom;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        // Mettre à jour les dates selon le statut
        if ($status === 'in_progress' && !$this->startDate) {
            $this->startDate = new \DateTime();
        } elseif ($status === 'completed' && !$this->completionDate) {
            $this->completionDate = new \DateTime();
        }

        return $this;
    }

    public function getQuantityToProduce(): string
    {
        return $this->quantityToProduce;
    }

    public function setQuantityToProduce(string $quantityToProduce): self
    {
        $this->quantityToProduce = $quantityToProduce;
        return $this;
    }

    public function getQuantityProduced(): string
    {
        return $this->quantityProduced;
    }

    public function setQuantityProduced(string $quantityProduced): self
    {
        $this->quantityProduced = $quantityProduced;

        // Mettre à jour le statut basé sur la quantité produite
        $this->updateStatusBasedOnProduction();

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getCompletionDate(): ?\DateTimeInterface
    {
        return $this->completionDate;
    }

    public function setCompletionDate(?\DateTimeInterface $completionDate): self
    {
        $this->completionDate = $completionDate;
        return $this;
    }

    public function getSourceWarehouse(): ?Warehouses
    {
        return $this->sourceWarehouse;
    }

    public function setSourceWarehouse(?Warehouses $sourceWarehouse): self
    {
        $this->sourceWarehouse = $sourceWarehouse;
        return $this;
    }

    public function getDestinationWarehouse(): ?Warehouses
    {
        return $this->destinationWarehouse;
    }

    public function setDestinationWarehouse(?Warehouses $destinationWarehouse): self
    {
        $this->destinationWarehouse = $destinationWarehouse;
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

    public function getCreatedBy(): TenantUsers
    {
        return $this->createdBy;
    }

    public function setCreatedBy(TenantUsers $createdBy): self
    {
        $this->createdBy = $createdBy;
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

    // Méthodes de calcul
    public function getCompletionPercentage(): string
    {
        if ($this->completionPercentage === null) {
            if (bccomp($this->quantityToProduce, '0', 6) === 0) {
                $this->completionPercentage = '0';
            } else {
                $percentage = bcdiv($this->quantityProduced, $this->quantityToProduce, 4);
                $this->completionPercentage = bcmul($percentage, '100', 2);
            }
        }
        return $this->completionPercentage;
    }

    public function getRemainingQuantity(): string
    {
        if ($this->remainingQuantity === null) {
            $this->remainingQuantity = bcsub($this->quantityToProduce, $this->quantityProduced, 6);
        }
        return $this->remainingQuantity;
    }

    public function getRequiredComponents(): array
    {
        if ($this->requiredComponents === null) {
            $this->requiredComponents = $this->bom->calculateRequiredQuantities($this->quantityToProduce);
        }
        return $this->requiredComponents;
    }

    public function getEstimatedCost(): ?string
    {
        $unitCost = $this->bom->getUnitCost();
        if ($unitCost === null) {
            return null;
        }
        return bcmul($unitCost, $this->quantityToProduce, 4);
    }

    public function getActualCost(): ?string
    {
        // À implémenter: calculer le coût réel basé sur les composants consommés
        return null;
    }

    // Méthodes de statut
    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPartiallyCompleted(): bool
    {
        return $this->status === 'partially_completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function canBeStarted(): bool
    {
        return in_array($this->status, ['planned', 'reserved']);
    }

    public function canBeCompleted(): bool
    {
        return in_array($this->status, ['in_progress', 'partially_completed']);
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled', 'closed']);
    }

    public function canBeReserved(): bool
    {
        return $this->status === 'planned';
    }

    public function updateStatusBasedOnProduction(): void
    {
        $remaining = $this->getRemainingQuantity();

        if (bccomp($this->quantityProduced, '0', 6) === 0) {
            $this->status = $this->sourceWarehouse ? 'reserved' : 'planned';
        } elseif (bccomp($remaining, '0', 6) > 0) {
            $this->status = 'partially_completed';
        } else {
            $this->status = 'completed';
            $this->completionDate = $this->completionDate ?? new \DateTime();
        }

        $this->updatedAt = new \DateTime();
    }

    public function startProduction(): self
    {
        if (!$this->canBeStarted()) {
            throw new \RuntimeException('Cet ordre de production ne peut pas être démarré');
        }

        $this->status = 'in_progress';
        $this->startDate = new \DateTime();
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function completeProduction(string $quantityProduced = null): self
    {
        if (!$this->canBeCompleted()) {
            throw new \RuntimeException('Cet ordre de production ne peut pas être complété');
        }

        if ($quantityProduced !== null) {
            $this->quantityProduced = $quantityProduced;
        } else {
            $this->quantityProduced = $this->quantityToProduce;
        }

        $this->status = 'completed';
        $this->completionDate = new \DateTime();
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function recordProduction(string $quantity): self
    {
        if (!$this->isInProgress() && !$this->isPartiallyCompleted()) {
            throw new \RuntimeException('Seuls les ordres en cours ou partiellement complétés peuvent enregistrer de la production');
        }

        if (bccomp($quantity, '0', 6) <= 0) {
            throw new \InvalidArgumentException('La quantité produite doit être positive');
        }

        $newQuantityProduced = bcadd($this->quantityProduced, $quantity, 6);

        if (bccomp($newQuantityProduced, $this->quantityToProduce, 6) > 0) {
            throw new \RuntimeException(sprintf(
                'Quantité trop élevée. À produire: %s, Déjà produit: %s, Tentative: %s',
                $this->quantityToProduce,
                $this->quantityProduced,
                $quantity
            ));
        }

        $this->quantityProduced = $newQuantityProduced;
        $this->updateStatusBasedOnProduction();

        return $this;
    }

    public function cancel(string $reason = null): self
    {
        if (!$this->canBeCancelled()) {
            throw new \RuntimeException('Cet ordre de production ne peut pas être annulé');
        }

        $this->status = 'cancelled';
        if ($reason) {
            $this->notes .= "\n\nAnnulé le " . date('d/m/Y') . " : " . $reason;
        }
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function close(): self
    {
        if (!$this->isCompleted()) {
            throw new \RuntimeException('Seuls les ordres de production complétés peuvent être clôturés');
        }

        $this->status = 'closed';
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getProductionDuration(): ?int
    {
        if (!$this->startDate || !$this->completionDate) {
            return null;
        }

        $interval = $this->startDate->diff($this->completionDate);
        return $interval->days;
    }

    public function getEstimatedCompletionDate(): ?\DateTimeInterface
    {
        if (!$this->startDate || bccomp($this->completionPercentage, '0', 2) === 0) {
            return null;
        }

        $daysElapsed = (new \DateTime())->diff($this->startDate)->days;
        if ($daysElapsed <= 0 || bccomp($this->completionPercentage, '0', 2) <= 0) {
            return null;
        }

        $dailyRate = bcdiv($this->completionPercentage, (string) $daysElapsed, 4);
        if (bccomp($dailyRate, '0', 4) <= 0) {
            return null;
        }

        $daysRemaining = bcdiv(bcsub('100', $this->completionPercentage, 2), $dailyRate, 0);
        $estimatedDate = clone $this->startDate;
        $estimatedDate->modify('+' . ceil($daysRemaining) . ' days');

        return $estimatedDate;
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'poNumber' => $this->poNumber,
            'bom' => $this->bom->toArray(),
            'status' => $this->status,
            'quantityToProduce' => $this->quantityToProduce,
            'quantityProduced' => $this->quantityProduced,
            'startDate' => $this->startDate?->format('Y-m-d'),
            'completionDate' => $this->completionDate?->format('Y-m-d'),
            'sourceWarehouse' => $this->sourceWarehouse?->toArray(),
            'destinationWarehouse' => $this->destinationWarehouse?->toArray(),
            'notes' => $this->notes,
            'createdBy' => $this->createdBy->toArray(),
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'completionPercentage' => $this->getCompletionPercentage(),
            'remainingQuantity' => $this->getRemainingQuantity(),
            'estimatedCost' => $this->getEstimatedCost(),
            'actualCost' => $this->getActualCost(),
            'isPlanned' => $this->isPlanned(),
            'isInProgress' => $this->isInProgress(),
            'isCompleted' => $this->isCompleted(),
            'canBeStarted' => $this->canBeStarted(),
            'canBeCompleted' => $this->canBeCompleted(),
            'productionDuration' => $this->getProductionDuration(),
            'estimatedCompletionDate' => $this->getEstimatedCompletionDate()?->format('Y-m-d'),
        ];
    }

    public function toDetailedArray(): array
    {
        $data = $this->toArray();
        $data['requiredComponents'] = $this->getRequiredComponents();
        $data['bomDetails'] = $this->bom->toDetailedArray();

        return $data;
    }
}
