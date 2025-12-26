<?php

namespace App\Domain\SalesOrders;

use App\Domain\Customers\Customers;
use App\Domain\SalesOrderLines\SalesOrderLines;
use App\Domain\TenantUsers\TenantUsers;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SalesOrdersRepository::class)]
#[ORM\Table(name: 'sales_orders')]
#[ORM\HasLifecycleCallbacks]
class SalesOrders
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['sales_order:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['sales_order:read'])]
    private string $uuid;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Groups(['sales_order:read'])]
    private string $soNumber;

    #[ORM\ManyToOne(targetEntity: Customers::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['sales_order:read', 'sales_order:write'])]
    private Customers $customer;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'draft'])]
    #[Assert\Choice([
        'draft', 'confirmed', 'reserved', 'partially_fulfilled',
        'fulfilled', 'shipped', 'delivered', 'invoiced', 'cancelled', 'closed'
    ])]
    #[Groups(['sales_order:read', 'sales_order:write'])]
    private string $status = 'draft';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\LessThanOrEqual('today')]
    #[Groups(['sales_order:read', 'sales_order:write'])]
    private ?\DateTimeImmutable $orderDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'orderDate')]
    #[Groups(['sales_order:read', 'sales_order:write'])]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, options: ['default' => 0])]
    #[Groups(['sales_order:read'])]
    private string $totalAmount = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, options: ['default' => 0])]
    #[Groups(['sales_order:read'])]
    private string $taxAmount = '0';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['sales_order:read', 'sales_order:write'])]
    private ?string $shippingAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['sales_order:read', 'sales_order:write'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: TenantUsers::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false)]
    #[Groups(['sales_order:read'])]
    private TenantUsers $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['sales_order:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['sales_order:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\OneToMany(targetEntity: SalesOrderLines::class, mappedBy: 'salesOrder', cascade: ['persist', 'remove'])]
    #[Groups(['sales_order:read'])]
    private Collection $lines;

    // Champs virtuels
    private ?string $grandTotal = null;
    private ?string $quantityOrdered = null;
    private ?string $quantityFulfilled = null;
    private ?string $fulfilledPercentage = null;
    private ?string $outstandingAmount = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->orderDate = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
        $this->generateSoNumber();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getSoNumber(): string
    {
        return $this->soNumber;
    }

    private function generateSoNumber(): void
    {
        $date = new \DateTime();
        $this->soNumber = 'SO-' . $date->format('Ymd') . '-' . strtoupper(substr($this->uuid, 0, 8));
    }

    public function getCustomer(): Customers
    {
        return $this->customer;
    }

    public function setCustomer(Customers $customer): self
    {
        $this->customer = $customer;
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

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
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

    public function getShippingAddress(): ?string
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?string $shippingAddress): self
    {
        $this->shippingAddress = $shippingAddress;
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

    /**
     * @return Collection|SalesOrderLines[]
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(SalesOrderLines $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setSalesOrder($this);
            $this->updateTotals();
        }

        return $this;
    }

    public function removeLine(SalesOrderLines $line): self
    {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getSalesOrder() === $this) {
                $line->setSalesOrder(null);
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

    public function getQuantityFulfilled(): string
    {
        if ($this->quantityFulfilled === null) {
            $total = '0';
            foreach ($this->lines as $line) {
                $total = bcadd($total, $line->getQuantityFulfilled(), 6);
            }
            $this->quantityFulfilled = $total;
        }
        return $this->quantityFulfilled;
    }

    public function getFulfilledPercentage(): string
    {
        if ($this->fulfilledPercentage === null) {
            $ordered = $this->getQuantityOrdered();

            if (bccomp($ordered, '0', 6) === 0) {
                $this->fulfilledPercentage = '0';
            } else {
                $fulfilled = $this->getQuantityFulfilled();
                $percentage = bcdiv($fulfilled, $ordered, 4);
                $this->fulfilledPercentage = bcmul($percentage, '100', 2);
            }
        }
        return $this->fulfilledPercentage;
    }

    public function getOutstandingAmount(): string
    {
        if ($this->outstandingAmount === null) {
            // Pour l'instant, retourne le montant total
            // À étendre avec le système de facturation
            $this->outstandingAmount = $this->getGrandTotal();
        }
        return $this->outstandingAmount;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    public function isPartiallyFulfilled(): bool
    {
        return $this->status === 'partially_fulfilled';
    }

    public function isFulfilled(): bool
    {
        return $this->status === 'fulfilled';
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isInvoiced(): bool
    {
        return $this->status === 'invoiced';
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

    public function isFulfillable(): bool
    {
        return in_array($this->status, ['confirmed', 'reserved', 'partially_fulfilled']);
    }

    public function canBeConfirmed(): bool
    {
        return $this->status === 'draft' && $this->lines->count() > 0;
    }

    public function canBeReserved(): bool
    {
        return $this->status === 'confirmed';
    }

    public function canBeShipped(): bool
    {
        return in_array($this->status, ['fulfilled', 'partially_fulfilled']);
    }

    public function confirm(): self
    {
        if (!$this->canBeConfirmed()) {
            throw new \RuntimeException('Cette commande ne peut pas être confirmée');
        }

        $this->status = 'confirmed';
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function reserve(): self
    {
        if (!$this->canBeReserved()) {
            throw new \RuntimeException('Cette commande ne peut pas être réservée');
        }

        $this->status = 'reserved';
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function cancel(string $reason = null): self
    {
        if ($this->isShipped() || $this->isDelivered() || $this->isInvoiced() || $this->isClosed()) {
            throw new \RuntimeException('Impossible d\'annuler une commande déjà expédiée, livrée, facturée ou clôturée');
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
        if (!$this->isDelivered() && !$this->isInvoiced()) {
            throw new \RuntimeException('Seules les commandes livrées ou facturées peuvent être clôturées');
        }

        $this->status = 'closed';
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function updateStatusBasedOnFulfillment(): void
    {
        $ordered = $this->getQuantityOrdered();
        $fulfilled = $this->getQuantityFulfilled();

        if (bccomp($fulfilled, '0', 6) === 0) {
            $this->status = 'confirmed';
        } elseif (bccomp($fulfilled, $ordered, 6) < 0) {
            $this->status = 'partially_fulfilled';
        } else {
            $this->status = 'fulfilled';
        }

        $this->updatedAt = new \DateTime();
    }

    public function getLineByProductId(string $productId): ?SalesOrderLines
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
        $fulfilled = $this->getQuantityFulfilled();
        return bcsub($ordered, $fulfilled, 6);
    }

    public function checkCreditLimit(): bool
    {
        $customerCreditLimit = $this->customer->getCreditLimit();
        $grandTotal = $this->getGrandTotal();

        // Si le client n'a pas de limite de crédit, toujours autoriser
        if ($customerCreditLimit === null || bccomp($customerCreditLimit, '0', 4) === 0) {
            return true;
        }

        // TODO: Récupérer le solde actuel du client
        $currentBalance = '0'; // À implémenter avec le module de facturation

        $newBalance = bcadd($currentBalance, $grandTotal, 4);
        return bccomp($newBalance, $customerCreditLimit, 4) <= 0;
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
            'soNumber' => $this->soNumber,
            'customer' => $this->customer->toArray(),
            'status' => $this->status,
            'orderDate' => $this->orderDate?->format('Y-m-d'),
            'dueDate' => $this->dueDate?->format('Y-m-d'),
            'totalAmount' => $this->totalAmount,
            'taxAmount' => $this->taxAmount,
            'grandTotal' => $this->getGrandTotal(),
            'shippingAddress' => $this->shippingAddress,
            'notes' => $this->notes,
            'createdBy' => $this->createdBy->toArray(),
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'quantityOrdered' => $this->getQuantityOrdered(),
            'quantityFulfilled' => $this->getQuantityFulfilled(),
            'fulfilledPercentage' => $this->getFulfilledPercentage(),
            'openQuantity' => $this->getOpenQuantity(),
            'outstandingAmount' => $this->getOutstandingAmount(),
            'isDraft' => $this->isDraft(),
            'isFulfillable' => $this->isFulfillable(),
            'linesCount' => $this->lines->count(),
            'creditLimitCheck' => $this->checkCreditLimit(),
        ];
    }
}
