<?php

namespace App\Domain\SalesOrderLines;

use App\Domain\SalesOrders\SalesOrders;
use App\Domain\Products\Products;
use App\Domain\Warehouses\Warehouses;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SalesOrderLinesRepository::class)]
#[ORM\Table(name: 'sales_order_lines')]
#[ORM\HasLifecycleCallbacks]
class SalesOrderLines
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['sales_order_line:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['sales_order_line:read'])]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: SalesOrders::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'sales_order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['sales_order_line:read'])]
    private SalesOrders $salesOrder;

    #[ORM\ManyToOne(targetEntity: Products::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['sales_order_line:read', 'sales_order_line:write'])]
    private Products $product;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['sales_order_line:read', 'sales_order_line:write'])]
    private string $quantityOrdered;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, options: ['default' => 0])]
    #[Groups(['sales_order_line:read'])]
    private string $quantityFulfilled = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Groups(['sales_order_line:read', 'sales_order_line:write'])]
    private string $unitPrice;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['sales_order_line:read', 'sales_order_line:write'])]
    private string $taxRate = '0';

    #[ORM\ManyToOne(targetEntity: Warehouses::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'id')]
    #[Groups(['sales_order_line:read', 'sales_order_line:write'])]
    private ?Warehouses $warehouse = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['sales_order_line:read'])]
    private bool $reserved = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['sales_order_line:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['sales_order_line:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Champs virtuels
    private ?string $lineTotal = null;
    private ?string $taxAmount = null;
    private ?string $lineTotalWithTax = null;
    private ?string $openQuantity = null;
    private ?string $fulfilledPercentage = null;

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

    public function getSalesOrder(): SalesOrders
    {
        return $this->salesOrder;
    }

    public function setSalesOrder(SalesOrders $salesOrder): self
    {
        $this->salesOrder = $salesOrder;
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

    public function getQuantityOrdered(): string
    {
        return $this->quantityOrdered;
    }

    public function setQuantityOrdered(string $quantityOrdered): self
    {
        $this->quantityOrdered = $quantityOrdered;
        return $this;
    }

    public function getQuantityFulfilled(): string
    {
        return $this->quantityFulfilled;
    }

    public function setQuantityFulfilled(string $quantityFulfilled): self
    {
        $this->quantityFulfilled = $quantityFulfilled;
        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getTaxRate(): string
    {
        return $this->taxRate;
    }

    public function setTaxRate(string $taxRate): self
    {
        $this->taxRate = $taxRate;
        return $this;
    }

    public function getWarehouse(): ?Warehouses
    {
        return $this->warehouse;
    }

    public function setWarehouse(?Warehouses $warehouse): self
    {
        $this->warehouse = $warehouse;
        return $this;
    }

    public function isReserved(): bool
    {
        return $this->reserved;
    }

    public function setReserved(bool $reserved): self
    {
        $this->reserved = $reserved;
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
    public function getLineTotal(): string
    {
        if ($this->lineTotal === null) {
            $this->lineTotal = bcmul($this->quantityOrdered, $this->unitPrice, 4);
        }
        return $this->lineTotal;
    }

    public function getTaxAmount(): string
    {
        if ($this->taxAmount === null) {
            $lineTotal = $this->getLineTotal();
            $taxRate = bcdiv($this->taxRate, '100', 4);
            $this->taxAmount = bcmul($lineTotal, $taxRate, 4);
        }
        return $this->taxAmount;
    }

    public function getLineTotalWithTax(): string
    {
        if ($this->lineTotalWithTax === null) {
            $lineTotal = $this->getLineTotal();
            $taxAmount = $this->getTaxAmount();
            $this->lineTotalWithTax = bcadd($lineTotal, $taxAmount, 4);
        }
        return $this->lineTotalWithTax;
    }

    public function getOpenQuantity(): string
    {
        if ($this->openQuantity === null) {
            $this->openQuantity = bcsub($this->quantityOrdered, $this->quantityFulfilled, 6);
        }
        return $this->openQuantity;
    }

    public function getFulfilledPercentage(): string
    {
        if ($this->fulfilledPercentage === null) {
            if (bccomp($this->quantityOrdered, '0', 6) === 0) {
                $this->fulfilledPercentage = '0';
            } else {
                $percentage = bcdiv($this->quantityFulfilled, $this->quantityOrdered, 4);
                $this->fulfilledPercentage = bcmul($percentage, '100', 2);
            }
        }
        return $this->fulfilledPercentage;
    }

    public function isFullyFulfilled(): bool
    {
        return bccomp($this->quantityFulfilled, $this->quantityOrdered, 6) >= 0;
    }

    public function isPartiallyFulfilled(): bool
    {
        return bccomp($this->quantityFulfilled, '0', 6) > 0 &&
            bccomp($this->quantityFulfilled, $this->quantityOrdered, 6) < 0;
    }

    public function isNotFulfilled(): bool
    {
        return bccomp($this->quantityFulfilled, '0', 6) === 0;
    }

    public function fulfillQuantity(string $quantity, \DateTimeInterface $date = null): self
    {
        if (bccomp($quantity, '0', 6) <= 0) {
            throw new \InvalidArgumentException('La quantité à préparer doit être positive');
        }

        $newFulfilled = bcadd($this->quantityFulfilled, $quantity, 6);

        if (bccomp($newFulfilled, $this->quantityOrdered, 6) > 0) {
            throw new \RuntimeException(sprintf(
                'Quantité trop élevée. Commandé: %s, Déjà préparé: %s, Tentative: %s',
                $this->quantityOrdered,
                $this->quantityFulfilled,
                $quantity
            ));
        }

        $this->quantityFulfilled = $newFulfilled;
        $this->updatedAt = new \DateTime();

        // Si la ligne est maintenant entièrement préparée, la désactiver la réservation
        if ($this->isFullyFulfilled()) {
            $this->reserved = false;
        }

        // Mettre à jour le statut de la commande
        $this->salesOrder->updateStatusBasedOnFulfillment();

        return $this;
    }

    public function canFulfillMore(): bool
    {
        return bccomp($this->getOpenQuantity(), '0', 6) > 0;
    }

    public function getRemainingToFulfill(): string
    {
        return $this->getOpenQuantity();
    }

    public function reserveStock(): self
    {
        if (!$this->warehouse) {
            throw new \RuntimeException('Impossible de réserver du stock sans entrepôt spécifié');
        }

        if ($this->isReserved()) {
            return $this;
        }

        // TODO: Implémenter la réservation de stock
        // Cette méthode devrait mettre à jour la quantité réservée dans stock_locations
        $this->reserved = true;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function releaseReservation(): self
    {
        if (!$this->reserved) {
            return $this;
        }

        // TODO: Implémenter la libération de réservation de stock
        $this->reserved = false;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function updateFromArray(array $data): self
    {
        if (isset($data['quantityOrdered'])) {
            $this->quantityOrdered = $data['quantityOrdered'];
        }

        if (isset($data['unitPrice'])) {
            $this->unitPrice = $data['unitPrice'];
        }

        if (isset($data['taxRate'])) {
            $this->taxRate = $data['taxRate'];
        }

        if (isset($data['warehouseId'])) {
            // Géré par le service
        }

        if (isset($data['reserved'])) {
            $this->reserved = (bool) $data['reserved'];
        }

        $this->updatedAt = new \DateTime();

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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'salesOrderId' => $this->salesOrder->getUuid(),
            'product' => $this->product->toArray(),
            'quantityOrdered' => $this->quantityOrdered,
            'quantityFulfilled' => $this->quantityFulfilled,
            'unitPrice' => $this->unitPrice,
            'taxRate' => $this->taxRate,
            'warehouse' => $this->warehouse?->toArray(),
            'reserved' => $this->reserved,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'lineTotal' => $this->getLineTotal(),
            'taxAmount' => $this->getTaxAmount(),
            'lineTotalWithTax' => $this->getLineTotalWithTax(),
            'openQuantity' => $this->getOpenQuantity(),
            'fulfilledPercentage' => $this->getFulfilledPercentage(),
            'isFullyFulfilled' => $this->isFullyFulfilled(),
            'isPartiallyFulfilled' => $this->isPartiallyFulfilled(),
            'canFulfillMore' => $this->canFulfillMore(),
        ];
    }
}
