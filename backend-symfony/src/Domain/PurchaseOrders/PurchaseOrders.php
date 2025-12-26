<?php

namespace App\Domain\PurchaseOrders;

use App\Domain\Suppliers\Suppliers;
use App\Domain\TenantUsers\TenantUsers;
use App\Domain\PurchaseOrderLines\PurchaseOrderLines;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PurchaseOrdersRepository::class)]
#[ORM\Table(name: 'purchase_orders')]
#[ORM\HasLifecycleCallbacks]
class PurchaseOrders
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['purchase_order:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['purchase_order:read'])]
    private string $uuid;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Groups(['purchase_order:read'])]
    private string $poNumber;

    #[ORM\ManyToOne(targetEntity: Suppliers::class)]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    private Suppliers $supplier;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'draft'])]
    #[Assert\Choice([
        'draft', 'pending_approval', 'approved', 'ordered',
        'partially_received', 'received', 'cancelled', 'closed'
    ])]
    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    private string $status = 'draft';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\LessThanOrEqual('today')]
    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    private ?\DateTimeImmutable $orderDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'orderDate')]
    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    private ?\DateTimeInterface $expectedDeliveryDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    private ?\DateTimeInterface $deliveryDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, options: ['default' => 0])]
    #[Groups(['purchase_order:read'])]
    private string $totalAmount = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, options: ['default' => 0])]
    #[Groups(['purchase_order:read'])]
    private string $taxAmount = '0';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['purchase_order:read', 'purchase_order:write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: TenantUsers::class)]
    #[ORM\JoinColumn(name: 'approved_by', referencedColumnName: 'id')]
    #[Groups(['purchase_order:read'])]
    private ?TenantUsers $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['purchase_order:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['purchase_order:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\OneToMany(targetEntity: PurchaseOrderLines::class, mappedBy: 'purchaseOrder', cascade: ['persist', 'remove'])]
    #[Groups(['purchase_order:read'])]
    private Collection $lines;

    // Champs virtuels
    private ?string $grandTotal = null;
    private ?string $quantityOrdered = null;
    private ?string $quantityReceived = null;
    private ?string $receivedPercentage = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->orderDate = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
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
        $this->poNumber = 'PO-' . $date->format('Ymd') . '-' . strtoupper(substr($this->uuid, 0, 8));
    }

    public function getSupplier(): Suppliers
    {
        return $this->supplier;
    }

    public function setSupplier(Suppliers $supplier): self
    {
        $this->supplier = $supplier;
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

    public function getOrderDate(): ?\DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function setOrderDate(\DateTimeImmutable $orderDate): self
    {
        $this->orderDate = $orderDate;
        return $this;
    }

    public function getExpectedDeliveryDate(): ?\DateTimeInterface
    {
        return $this->expectedDeliveryDate;
    }

    public function setExpectedDeliveryDate(?\DateTimeInterface $expectedDeliveryDate): self
    {
        $this->expectedDeliveryDate = $expectedDeliveryDate;
        return $this;
    }

    public function getDeliveryDate(): ?\DateTimeInterface
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?\DateTimeInterface $deliveryDate): self
    {
        $this->deliveryDate = $deliveryDate;

        if ($deliveryDate && $this->status === 'ordered') {
            $this->status = 'received';
        }

        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function getTaxAmount(): string
    {
        return $this->taxAmount;
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

    public function getApprovedBy(): ?TenantUsers
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?TenantUsers $approvedBy): self
    {
        $this->approvedBy = $approvedBy;
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

    /**
     * @return Collection|PurchaseOrderLines[]
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(PurchaseOrderLines $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setPurchaseOrder($this);
            $this->updateTotals();
        }

        return $this;
    }

    public function removeLine(PurchaseOrderLines $line): self
    {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getPurchaseOrder() === $this) {
                $line->setPurchaseOrder(null);
            }
            $this->updateTotals();
        }

        return $this;
    }

    public function updateTotals(): void
    {
        $totalAmount = '0';
        $taxAmount = '0';

        foreach ($this->lines as $line) {
            $lineTotal = bcmul($line->getQuantityOrdered(), $line->getUnitPrice(), 4);
            $lineTax = bcmul($lineTotal, bcdiv($line->getTaxRate(), '100', 4), 4);

            $totalAmount = bcadd($totalAmount, $lineTotal, 4);
            $taxAmount = bcadd($taxAmount, $lineTax, 4);
        }

        $this->totalAmount = $totalAmount;
        $this->taxAmount = $taxAmount;
    }

    public function getGrandTotal(): string
    {
        if ($this->grandTotal === null) {
            $this->grandTotal = bcadd($this->totalAmount, $this->taxAmount, 4);
        }
        return $this->grandTotal;
    }

    public function getQuantityOrdered(): string
    {
        if ($this->quantityOrdered === null) {
            $total = '0';
            foreach ($this->lines as $line) {
                $total = bcadd($total, $line->getQuantityOrdered(), 6);
            }
            $this->quantityOrdered = $total;
        }
        return $this->quantityOrdered;
    }

    public function getQuantityReceived(): string
    {
        if ($this->quantityReceived === null) {
            $total = '0';
            foreach ($this->lines as $line) {
                $total = bcadd($total, $line->getQuantityReceived(), 6);
            }
            $this->quantityReceived = $total;
        }
        return $this->quantityReceived;
    }

    public function getReceivedPercentage(): string
    {
        if ($this->receivedPercentage === null) {
            $ordered = $this->getQuantityOrdered();

            if (bccomp($ordered, '0', 6) === 0) {
                $this->receivedPercentage = '0';
            } else {
                $received = $this->getQuantityReceived();
                $percentage = bcdiv($received, $ordered, 4);
                $this->receivedPercentage = bcmul($percentage, '100', 2);
            }
        }
        return $this->receivedPercentage;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isOrdered(): bool
    {
        return $this->status === 'ordered';
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === 'partially_received';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isDeletable(): bool
    {
        return in_array($this->status, ['draft', 'cancelled']);
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, ['ordered', 'partially_received']);
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending_approval' && $this->lines->count() > 0;
    }

    public function canBeOrdered(): bool
    {
        return $this->status === 'approved' || $this->status === 'draft';
    }

    public function approve(TenantUsers $approver): self
    {
        if (!$this->canBeApproved()) {
            throw new \RuntimeException('Cette commande ne peut pas être approuvée');
        }

        $this->status = 'approved';
        $this->approvedBy = $approver;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function markAsOrdered(): self
    {
        if (!$this->canBeOrdered()) {
            throw new \RuntimeException('Cette commande ne peut pas être passée');
        }

        $this->status = 'ordered';
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function cancel(?string $reason = null): self
    {
        if ($this->isReceived() || $this->isClosed()) {
            throw new \RuntimeException('Impossible d\'annuler une commande déjà reçue ou clôturée');
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
        if (!$this->isReceived()) {
            throw new \RuntimeException('Seules les commandes reçues peuvent être clôturées');
        }

        $this->status = 'closed';
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function updateStatusBasedOnReceipt(): void
    {
        $ordered = $this->getQuantityOrdered();
        $received = $this->getQuantityReceived();

        if (bccomp($received, '0', 6) === 0) {
            $this->status = 'ordered';
        } elseif (bccomp($received, $ordered, 6) < 0) {
            $this->status = 'partially_received';
        } else {
            $this->status = 'received';
            $this->deliveryDate = $this->deliveryDate ?? new \DateTime();
        }

        $this->updatedAt = new \DateTime();
    }

    public function getLineByProductId(string $productId): ?PurchaseOrderLines
    {
        foreach ($this->lines as $line) {
            if ($line->getProduct()->getUuid() === $productId) {
                return $line;
            }
        }

        return null;
    }

    public function getOpenQuantity(): string
    {
        $ordered = $this->getQuantityOrdered();
        $received = $this->getQuantityReceived();
        return bcsub($ordered, $received, 6);
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
    public function updateTimestamps(): void
    {
        $this->updateTotals();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'poNumber' => $this->poNumber,
            'supplier' => $this->supplier->toArray(),
            'status' => $this->status,
            'orderDate' => $this->orderDate?->format('Y-m-d'),
            'expectedDeliveryDate' => $this->expectedDeliveryDate?->format('Y-m-d'),
            'deliveryDate' => $this->deliveryDate?->format('Y-m-d'),
            'totalAmount' => $this->totalAmount,
            'taxAmount' => $this->taxAmount,
            'grandTotal' => $this->getGrandTotal(),
            'notes' => $this->notes,
            'approvedBy' => $this->approvedBy?->toArray(),
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'quantityOrdered' => $this->getQuantityOrdered(),
            'quantityReceived' => $this->getQuantityReceived(),
            'receivedPercentage' => $this->getReceivedPercentage(),
            'openQuantity' => $this->getOpenQuantity(),
            'isDraft' => $this->isDraft(),
            'isReceivable' => $this->isReceivable(),
            'linesCount' => $this->lines->count(),
        ];
    }
}
