<?php

namespace App\Domain\StockLocations;

use App\Domain\Warehouses\Warehouses;
use App\Domain\Products\Products;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockLocationsRepository::class)]
#[ORM\Table(name: 'stock_locations')]
#[ORM\UniqueConstraint(name: 'warehouse_product_unique', columns: ['warehouse_id', 'product_id'])]
#[ORM\HasLifecycleCallbacks]
class StockLocations
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['stock_location:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Warehouses::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotBlank]
    #[Groups(['stock_location:read', 'stock_location:write'])]
    private Warehouses $warehouse;

    #[ORM\ManyToOne(targetEntity: Products::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotBlank]
    #[Groups(['stock_location:read', 'stock_location:write'])]
    private Products $product;

    #[ORM\Column(name: 'quantity_on_hand', type: Types::DECIMAL, precision: 15, scale: 6, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['stock_location:read', 'stock_location:write'])]
    private string $quantityOnHand = '0';

    #[ORM\Column(name: 'quantity_reserved', type: Types::DECIMAL, precision: 15, scale: 6, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['stock_location:read', 'stock_location:write'])]
    private string $quantityReserved = '0';

    #[ORM\Column(name: 'quantity_ordered', type: Types::DECIMAL, precision: 15, scale: 6, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['stock_location:read', 'stock_location:write'])]
    private string $quantityOrdered = '0';

    #[ORM\Column(name: 'last_count_date', type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['stock_location:read', 'stock_location:write'])]
    private ?\DateTimeInterface $lastCountDate = null;

    #[ORM\Column(name: 'average_cost', type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['stock_location:read'])]
    private ?string $averageCost = null;

    #[ORM\Column(name: 'location_reference', type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['stock_location:read', 'stock_location:write'])]
    private ?string $locationReference = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['stock_location:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWarehouse(): Warehouses
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouses $warehouse): self
    {
        $this->warehouse = $warehouse;
        return $this;
    }

    public function getProduct(): Products
    {
        return $this->product;
    }

    public function setProduct(Products $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getQuantityOnHand(): string
    {
        return $this->quantityOnHand;
    }

    public function setQuantityOnHand(string $quantityOnHand): self
    {
        $this->quantityOnHand = $quantityOnHand;
        return $this;
    }

    public function getQuantityReserved(): string
    {
        return $this->quantityReserved;
    }

    public function setQuantityReserved(string $quantityReserved): self
    {
        $this->quantityReserved = $quantityReserved;
        return $this;
    }

    public function getQuantityOrdered(): string
    {
        return $this->quantityOrdered;
    }

    public function setQuantityOrdered(string $quantityOrdered): self
    {
        $this->quantityOrdered = $quantityOrdered;
        return $this;
    }

    public function getLastCountDate(): ?\DateTimeInterface
    {
        return $this->lastCountDate;
    }

    public function setLastCountDate(?\DateTimeInterface $lastCountDate): self
    {
        $this->lastCountDate = $lastCountDate;
        return $this;
    }

    public function getAverageCost(): ?string
    {
        return $this->averageCost;
    }

    public function setAverageCost(?string $averageCost): self
    {
        $this->averageCost = $averageCost;
        return $this;
    }

    public function getLocationReference(): ?string
    {
        return $this->locationReference;
    }

    public function setLocationReference(?string $locationReference): self
    {
        $this->locationReference = $locationReference;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
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

    // ==================== MÉTHODES MÉTIER ESSENTIELLES ====================

    /**
     * Stock disponible pour vente = On Hand - Reserved
     */
    public function getAvailableQuantity(): float
    {
        return (float) $this->quantityOnHand - (float) $this->quantityReserved;
    }

    /**
     * Stock futur = On Hand + Ordered
     */
    public function getFutureQuantity(): float
    {
        return (float) $this->quantityOnHand + (float) $this->quantityOrdered;
    }

    /**
     * Stock total dans cet emplacement
     */
    public function getTotalQuantity(): float
    {
        return (float) $this->quantityOnHand;
    }

    /**
     * Valeur du stock dans cet emplacement
     */
    public function getStockValue(): ?float
    {
        if ($this->averageCost === null) {
            return null;
        }

        return (float) $this->quantityOnHand * (float) $this->averageCost;
    }

    /**
     * Pourcentage d'occupation (si max_stock_level défini sur le produit)
     */
    public function getOccupancyPercentage(): ?float
    {
        $maxStock = $this->product->getMaxStockLevel();

        if ($maxStock === null || (float) $maxStock === 0.0) {
            return null;
        }

        return ((float) $this->quantityOnHand / (float) $maxStock) * 100;
    }

    /**
     * Jours de stock disponibles basé sur la demande moyenne
     */
    public function getDaysOfSupply(float $averageDailyDemand): ?float
    {
        if ($averageDailyDemand <= 0) {
            return null;
        }

        return $this->getAvailableQuantity() / $averageDailyDemand;
    }

    /**
     * Augmente le stock physique (réception)
     */
    public function increaseOnHand(float $quantity, ?float $unitCost = null): self
    {
        $newQuantity = (float) $this->quantityOnHand + $quantity;

        // Mettre à jour le coût moyen
        if ($unitCost !== null && $quantity > 0) {
            $this->updateAverageCost($quantity, $unitCost);
        }

        $this->quantityOnHand = (string) $newQuantity;

        return $this;
    }

    /**
     * Diminue le stock physique (expédition)
     */
    public function decreaseOnHand(float $quantity): self
    {
        $newQuantity = (float) $this->quantityOnHand - $quantity;

        // Vérifier le stock négatif
        if ($newQuantity < 0 && !$this->warehouse->getSetting('allow_negative_stock', false)) {
            throw new \RuntimeException(sprintf(
                'Negative stock not allowed. Current: %s, Requested decrease: %s',
                $this->quantityOnHand,
                $quantity
            ));
        }

        $this->quantityOnHand = (string) $newQuantity;

        return $this;
    }

    /**
     * Réserve du stock pour une commande
     */
    public function reserveStock(float $quantity): self
    {
        $available = $this->getAvailableQuantity();

        if ($quantity > $available) {
            throw new \RuntimeException(sprintf(
                'Insufficient available stock. Available: %s, Requested: %s',
                $available,
                $quantity
            ));
        }

        $newReserved = (float) $this->quantityReserved + $quantity;
        $this->quantityReserved = (string) $newReserved;

        return $this;
    }

    /**
     * Libère une réservation
     */
    public function releaseReservation(float $quantity): self
    {
        if ($quantity > (float) $this->quantityReserved) {
            throw new \RuntimeException(sprintf(
                'Cannot release more than reserved. Reserved: %s, Requested release: %s',
                $this->quantityReserved,
                $quantity
            ));
        }

        $newReserved = (float) $this->quantityReserved - $quantity;
        $this->quantityReserved = (string) $newReserved;

        return $this;
    }

    /**
     * Convertit une réservation en sortie de stock
     */
    public function convertReservationToShipment(float $quantity): self
    {
        // Libérer la réservation
        $this->releaseReservation($quantity);

        // Diminuer le stock physique
        return $this->decreaseOnHand($quantity);
    }

    /**
     * Met à jour le coût moyen (méthode FIFO simplifiée)
     */
    private function updateAverageCost(float $newQuantity, float $newUnitCost): void
    {
        if ((float) $this->quantityOnHand === 0.0) {
            // Premier stock
            $this->averageCost = (string) $newUnitCost;
            return;
        }

        if ($this->averageCost === null) {
            // Coût moyen non défini, utiliser le nouveau
            $this->averageCost = (string) $newUnitCost;
            return;
        }

        // Calcul du coût moyen pondéré
        $currentValue = (float) $this->quantityOnHand * (float) $this->averageCost;
        $newValue = $newQuantity * $newUnitCost;
        $totalQuantity = (float) $this->quantityOnHand + $newQuantity;

        $newAverageCost = ($currentValue + $newValue) / $totalQuantity;
        $this->averageCost = (string) round($newAverageCost, 4);
    }

    /**
     * Vérifie si le stock est en dessous du point de réappro
     */
    public function isBelowReorderPoint(): bool
    {
        $reorderPoint = $this->product->getReorderPoint();

        if ($reorderPoint === null) {
            return false;
        }

        return $this->getAvailableQuantity() <= (float) $reorderPoint;
    }

    /**
     * Quantité à commander pour atteindre le stock max
     */
    public function getReorderQuantity(): ?float
    {
        $maxStock = $this->product->getMaxStockLevel();

        if ($maxStock === null) {
            return null;
        }

        $toOrder = (float) $maxStock - $this->getFutureQuantity();

        return $toOrder > 0 ? $toOrder : 0;
    }

    /**
     * Dernier inventaire il y a plus de X jours ?
     */
    public function needsPhysicalCount(int $maxDaysWithoutCount = 90): bool
    {
        if ($this->lastCountDate === null) {
            return true;
        }

        $daysSinceLastCount = (new \DateTime())->diff($this->lastCountDate)->days;

        return $daysSinceLastCount > $maxDaysWithoutCount;
    }

    /**
     * Enregistre un inventaire physique
     */
    public function recordPhysicalCount(float $countedQuantity, \DateTimeInterface $countDate): self
    {
        $difference = $countedQuantity - (float) $this->quantityOnHand;

        $this->quantityOnHand = (string) $countedQuantity;
        $this->lastCountDate = $countDate;

        // Retourner la différence pour ajustement comptable
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

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function toArray(bool $includeWarehouseDetails = false, bool $includeProductDetails = false): array
    {
        $data = [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse->getId(),
            'warehouse_name' => $this->warehouse->getName(),
            'warehouse_code' => $this->warehouse->getCode(),
            'product_id' => $this->product->getId(),
            'product_sku' => $this->product->getSku(),
            'product_name' => $this->product->getName(),
            'quantity_on_hand' => $this->quantityOnHand,
            'quantity_reserved' => $this->quantityReserved,
            'quantity_ordered' => $this->quantityOrdered,
            'available_quantity' => $this->getAvailableQuantity(),
            'future_quantity' => $this->getFutureQuantity(),
            'last_count_date' => $this->lastCountDate?->format('Y-m-d'),
            'needs_physical_count' => $this->needsPhysicalCount(),
            'average_cost' => $this->averageCost,
            'stock_value' => $this->getStockValue(),
            'location_reference' => $this->locationReference,
            'is_below_reorder_point' => $this->isBelowReorderPoint(),
            'reorder_quantity' => $this->getReorderQuantity(),
            'occupancy_percentage' => $this->getOccupancyPercentage(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'is_deleted' => $this->isDeleted(),
        ];

        if ($includeWarehouseDetails) {
            $data['warehouse'] = $this->warehouse->toArray();
        }

        if ($includeProductDetails) {
            $data['product'] = $this->product->toArray();
        }

        return $data;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (%s)',
            $this->warehouse->getCode(),
            $this->product->getSku(),
            $this->locationReference ?? 'No location'
        );
    }
}
