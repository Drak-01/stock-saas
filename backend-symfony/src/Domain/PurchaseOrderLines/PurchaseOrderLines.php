<?php

namespace App\Domain\PurchaseOrderLines;

use App\Domain\PurchaseOrders\PurchaseOrders;
use App\Domain\Products\Products;
use App\Domain\Warehouses\Warehouses;
use App\Repository\PurchaseOrderLines\PurchaseOrderLinesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PurchaseOrderLinesRepository::class)]
#[ORM\Table(name: 'purchase_order_lines')]
#[ORM\HasLifecycleCallbacks]
class PurchaseOrderLines
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['purchase_order_line:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['purchase_order_line:read'])]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: PurchaseOrders::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'purchase_order_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['purchase_order_line:read'])]
    private ?PurchaseOrders $purchaseOrder = null;

    #[ORM\ManyToOne(targetEntity: Products::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['purchase_order_line:read', 'purchase_order_line:write'])]
    private Products $product;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['purchase_order_line:read', 'purchase_order_line:write'])]
    private string $quantityOrdered;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, options: ['default' => 0])]
    #[Groups(['purchase_order_line:read'])]
    private string $quantityReceived = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Groups(['purchase_order_line:read', 'purchase_order_line:write'])]
    private string $unitPrice;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['purchase_order_line:read', 'purchase_order_line:write'])]
    private string $taxRate = '0';

    #[ORM\ManyToOne(targetEntity: Warehouses::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'id')]
    #[Groups(['purchase_order_line:read', 'purchase_order_line:write'])]
    private ?Warehouses $warehouse = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['purchase_order_line:read', 'purchase_order_line:write'])]
    private ?\DateTimeInterface $expectedDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['purchase_order_line:read'])]
    private ?\DateTimeInterface $receivedDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['purchase_order_line:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['purchase_order_line:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Champs virtuels
    private ?string $lineTotal = null;
    private ?string $taxAmount = null;
    private ?string $lineTotalWithTax = null;
    private ?string $openQuantity = null;
    private ?string $receivedPercentage = null;

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

    public function getPurchaseOrder(): ?PurchaseOrders
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(?PurchaseOrders $purchaseOrder): self
    {
        $this->purchaseOrder = $purchaseOrder;
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

    public function getQuantityReceived(): string
    {
        return $this->quantityReceived;
    }

    public function setQuantityReceived(string $quantityReceived): self
    {
        $this->quantityReceived = $quantityReceived;
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

    public function getExpectedDate(): ?\DateTimeInterface
    {
        return $this->expectedDate;
    }

    public function setExpectedDate(?\DateTimeInterface $expectedDate): self
    {
        $this->expectedDate = $expectedDate;
        return $this;
    }

    public function getReceivedDate(): ?\DateTimeInterface
    {
        return $this->receivedDate;
    }

    public function setReceivedDate(?\DateTimeInterface $receivedDate): self
    {
        $this->receivedDate = $receivedDate;
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
            $this->openQuantity = bcsub($this->quantityOrdered, $this->quantityReceived, 6);
        }
        return $this->openQuantity;
    }

    public function getReceivedPercentage(): string
    {
        if ($this->receivedPercentage === null) {
            if (bccomp($this->quantityOrdered, '0', 6) === 0) {
                $this->receivedPercentage = '0';
            } else {
                $percentage = bcdiv($this->quantityReceived, $this->quantityOrdered, 4);
                $this->receivedPercentage = bcmul($percentage, '100', 2);
            }
        }
        return $this->receivedPercentage;
    }

    public function isFullyReceived(): bool
    {
        return bccomp($this->quantityReceived, $this->quantityOrdered, 6) >= 0;
    }

    public function isPartiallyReceived(): bool
    {
        return bccomp($this->quantityReceived, '0', 6) > 0 &&
            bccomp($this->quantityReceived, $this->quantityOrdered, 6) < 0;
    }

    public function isNotReceived(): bool
    {
        return bccomp($this->quantityReceived, '0', 6) === 0;
    }

    public function receiveQuantity(string $quantity, ?\DateTimeInterface $date = null): self
    {
        if (bccomp($quantity, '0', 6) <= 0) {
            throw new \InvalidArgumentException('La quantité à recevoir doit être positive');
        }

        $newReceived = bcadd($this->quantityReceived, $quantity, 6);

        if (bccomp($newReceived, $this->quantityOrdered, 6) > 0) {
            throw new \RuntimeException(sprintf(
                'Quantité trop élevée. Commandé: %s, Déjà reçu: %s, Tentative: %s',
                $this->quantityOrdered,
                $this->quantityReceived,
                $quantity
            ));
        }

        $this->quantityReceived = $newReceived;
        $this->receivedDate = $date ?? new \DateTime();
        $this->updatedAt = new \DateTime();

        // Mettre à jour le statut de la commande
        $this->purchaseOrder->updateStatusBasedOnReceipt();

        return $this;
    }

    public function canReceiveMore(): bool
    {
        return bccomp($this->getOpenQuantity(), '0', 6) > 0;
    }

    public function getRemainingToReceive(): string
    {
        return $this->getOpenQuantity();
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


        if (isset($data['expectedDate'])) {
            $this->expectedDate = new \DateTime($data['expectedDate']);
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
            'purchaseOrderId' => $this->purchaseOrder->getUuid(),
            'product' => $this->product->toArray(),
            'quantityOrdered' => $this->quantityOrdered,
            'quantityReceived' => $this->quantityReceived,
            'unitPrice' => $this->unitPrice,
            'taxRate' => $this->taxRate,
            'warehouse' => $this->warehouse?->toArray(),
            'expectedDate' => $this->expectedDate?->format('Y-m-d'),
            'receivedDate' => $this->receivedDate?->format('Y-m-d'),
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'lineTotal' => $this->getLineTotal(),
            'taxAmount' => $this->getTaxAmount(),
            'lineTotalWithTax' => $this->getLineTotalWithTax(),
            'openQuantity' => $this->getOpenQuantity(),
            'receivedPercentage' => $this->getReceivedPercentage(),
            'isFullyReceived' => $this->isFullyReceived(),
            'isPartiallyReceived' => $this->isPartiallyReceived(),
            'canReceiveMore' => $this->canReceiveMore(),
        ];
    }
}
