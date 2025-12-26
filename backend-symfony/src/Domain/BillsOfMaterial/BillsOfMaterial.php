<?php

namespace App\Domain\BillsOfMaterial;

use App\Domain\BomLines\BomLines;
use App\Domain\Products\Products;
use App\Domain\MeasurementUnits\MeasurementUnits;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BillsOfMaterialRepository::class)]
#[ORM\Table(name: 'bills_of_material')]
#[ORM\HasLifecycleCallbacks]
class BillsOfMaterial
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['bom:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['bom:read'])]
    private string $uuid;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 100)]
    #[Groups(['bom:read', 'bom:write'])]
    private string $code;

    #[ORM\ManyToOne(targetEntity: Products::class)]
    #[ORM\JoinColumn(name: 'finished_product_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['bom:read', 'bom:write'])]
    private Products $finishedProduct;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    #[Assert\Positive]
    #[Groups(['bom:read', 'bom:write'])]
    private int $version = 1;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['bom:read', 'bom:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['bom:read', 'bom:write'])]
    private string $quantityProduced;

    #[ORM\ManyToOne(targetEntity: MeasurementUnits::class)]
    #[ORM\JoinColumn(name: 'production_unit_id', referencedColumnName: 'id')]
    #[Groups(['bom:read', 'bom:write'])]
    private ?MeasurementUnits $productionUnit = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Groups(['bom:read', 'bom:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['bom:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['bom:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\OneToMany(targetEntity: BomLines::class, mappedBy: 'bom', cascade: ['persist', 'remove'])]
    #[Groups(['bom:read'])]
    private Collection $lines;

    // Champs virtuels
    private ?string $totalCost = null;
    private ?string $unitCost = null;
    private ?int $componentsCount = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->lines = new ArrayCollection();
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
        $this->code = $code;
        return $this;
    }

    public function getFinishedProduct(): Products
    {
        return $this->finishedProduct;
    }

    public function setFinishedProduct(Products $finishedProduct): self
    {
        $this->finishedProduct = $finishedProduct;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getQuantityProduced(): string
    {
        return $this->quantityProduced;
    }

    public function setQuantityProduced(string $quantityProduced): self
    {
        $this->quantityProduced = $quantityProduced;
        return $this;
    }

    public function getProductionUnit(): ?MeasurementUnits
    {
        return $this->productionUnit;
    }

    public function setProductionUnit(?MeasurementUnits $productionUnit): self
    {
        $this->productionUnit = $productionUnit;
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
     * @return Collection|BomLines[]
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(BomLines $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setBom($this);
        }

        return $this;
    }

    public function removeLine(BomLines $line): self
    {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getBom() === $this) {
                $line->setBom(null);
            }
        }

        return $this;
    }

    // Méthodes de calcul
    public function getTotalCost(): string
    {
        if ($this->totalCost === null) {
            $totalCost = '0';
            foreach ($this->lines as $line) {
                $componentCost = $line->getComponentCost();
                if ($componentCost !== null) {
                    $lineCost = bcmul($line->getEffectiveQuantity(), $componentCost, 4);
                    $totalCost = bcadd($totalCost, $lineCost, 4);
                }
            }
            $this->totalCost = $totalCost;
        }
        return $this->totalCost;
    }

    public function getUnitCost(): ?string
    {
        if ($this->unitCost === null && bccomp($this->quantityProduced, '0', 6) > 0) {
            $totalCost = $this->getTotalCost();
            $this->unitCost = bcdiv($totalCost, $this->quantityProduced, 4);
        }
        return $this->unitCost;
    }

    public function getComponentsCount(): int
    {
        if ($this->componentsCount === null) {
            $this->componentsCount = $this->lines->count();
        }
        return $this->componentsCount;
    }

    public function getComponentQuantities(): array
    {
        $quantities = [];
        foreach ($this->lines as $line) {
            $quantities[$line->getComponent()->getUuid()] = $line->getEffectiveQuantity();
        }
        return $quantities;
    }

    public function getComponentCosts(): array
    {
        $costs = [];
        foreach ($this->lines as $line) {
            $componentCost = $line->getComponentCost();
            if ($componentCost !== null) {
                $costs[$line->getComponent()->getUuid()] = [
                    'quantity' => $line->getEffectiveQuantity(),
                    'unit_cost' => $componentCost,
                    'total_cost' => bcmul($line->getEffectiveQuantity(), $componentCost, 4),
                ];
            }
        }
        return $costs;
    }

    public function calculateRequiredQuantities(string $quantityToProduce): array
    {
        if (bccomp($quantityToProduce, '0', 6) <= 0) {
            throw new \InvalidArgumentException('La quantité à produire doit être positive');
        }

        $ratio = bcdiv($quantityToProduce, $this->quantityProduced, 6);
        $requiredQuantities = [];

        foreach ($this->lines as $line) {
            $requiredQuantity = bcmul($line->getEffectiveQuantity(), $ratio, 6);
            $requiredQuantities[$line->getComponent()->getUuid()] = $requiredQuantity;
        }

        return $requiredQuantities;
    }

    public function validateComponentsAvailability(array $availableStock): array
    {
        $errors = [];
        $requiredQuantities = $this->getComponentQuantities();

        foreach ($requiredQuantities as $componentUuid => $requiredQuantity) {
            $available = $availableStock[$componentUuid] ?? '0';
            if (bccomp($available, $requiredQuantity, 6) < 0) {
                $component = null;
                foreach ($this->lines as $line) {
                    if ($line->getComponent()->getUuid() === $componentUuid) {
                        $component = $line->getComponent();
                        break;
                    }
                }

                if ($component) {
                    $errors[] = sprintf(
                        'Stock insuffisant pour %s (%s). Requis: %s, Disponible: %s',
                        $component->getName(),
                        $component->getSku(),
                        $requiredQuantity,
                        $available
                    );
                }
            }
        }

        return $errors;
    }

    public function isLatestVersion(): bool
    {
        // À implémenter: vérifier s'il existe une version plus récente
        return true;
    }

    public function canBeDeleted(): bool
    {
        // À implémenter: vérifier si la NFE est utilisée dans des ordres de production
        return $this->lines->isEmpty();
    }

    public function cloneForNewVersion(): self
    {
        $newBom = new self();
        $newBom->setCode($this->code . '-V' . ($this->version + 1));
        $newBom->setFinishedProduct($this->finishedProduct);
        $newBom->setVersion($this->version + 1);
        $newBom->setQuantityProduced($this->quantityProduced);
        $newBom->setProductionUnit($this->productionUnit);
        $newBom->setNotes($this->notes);

        // Cloner les lignes
        foreach ($this->lines as $line) {
            $newLine = new BomLines();
            $newLine->setComponent($line->getComponent());
            $newLine->setQuantityRequired($line->getQuantityRequired());
            $newLine->setWasteFactor($line->getWasteFactor());
            $newLine->setSequence($line->getSequence());
            $newBom->addLine($newLine);
        }

        return $newBom;
    }

    public function deactivate(): self
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function activate(): self
    {
        $this->isActive = true;
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
            'code' => $this->code,
            'finishedProduct' => $this->finishedProduct->toArray(),
            'version' => $this->version,
            'isActive' => $this->isActive,
            'quantityProduced' => $this->quantityProduced,
            'productionUnit' => $this->productionUnit?->toArray(),
            'notes' => $this->notes,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'totalCost' => $this->getTotalCost(),
            'unitCost' => $this->getUnitCost(),
            'componentsCount' => $this->getComponentsCount(),
            'linesCount' => $this->lines->count(),
            'canBeDeleted' => $this->canBeDeleted(),
        ];
    }

    public function toDetailedArray(): array
    {
        $data = $this->toArray();
        $data['lines'] = array_map(fn($line) => $line->toArray(), $this->lines->toArray());
        $data['componentQuantities'] = $this->getComponentQuantities();
        $data['componentCosts'] = $this->getComponentCosts();

        return $data;
    }
}
