<?php

namespace App\Domain\BomLines;

use App\Domain\BillsOfMaterial\BillsOfMaterial;
use App\Domain\Products\Products;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BomLinesRepository::class)]
#[ORM\Table(name: 'bom_lines')]
#[ORM\UniqueConstraint(name: 'bom_component_unique', columns: ['bom_id', 'component_id'])]
#[ORM\HasLifecycleCallbacks]
class BomLines
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['bom_line:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['bom_line:read'])]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: BillsOfMaterial::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'bom_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['bom_line:read'])]
    private BillsOfMaterial $bom;

    #[ORM\ManyToOne(targetEntity: Products::class)]
    #[ORM\JoinColumn(name: 'component_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['bom_line:read', 'bom_line:write'])]
    private Products $component;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['bom_line:read', 'bom_line:write'])]
    private string $quantityRequired;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['bom_line:read', 'bom_line:write'])]
    private string $wasteFactor = '0';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    #[Assert\Positive]
    #[Groups(['bom_line:read', 'bom_line:write'])]
    private int $sequence = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['bom_line:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['bom_line:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Champs virtuels
    private ?string $effectiveQuantity = null;
    private ?string $wasteQuantity = null;

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

    public function getBom(): BillsOfMaterial
    {
        return $this->bom;
    }

    public function setBom(BillsOfMaterial $bom): self
    {
        $this->bom = $bom;
        return $this;
    }

    public function getComponent(): Products
    {
        return $this->component;
    }

    public function setComponent(Products $component): self
    {
        $this->component = $component;
        return $this;
    }

    public function getQuantityRequired(): string
    {
        return $this->quantityRequired;
    }

    public function setQuantityRequired(string $quantityRequired): self
    {
        $this->quantityRequired = $quantityRequired;
        return $this;
    }

    public function getWasteFactor(): string
    {
        return $this->wasteFactor;
    }

    public function setWasteFactor(string $wasteFactor): self
    {
        $this->wasteFactor = $wasteFactor;
        return $this;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;
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
    public function getEffectiveQuantity(): string
    {
        if ($this->effectiveQuantity === null) {
            $wasteMultiplier = bcadd('1', bcdiv($this->wasteFactor, '100', 4), 4);
            $this->effectiveQuantity = bcmul($this->quantityRequired, $wasteMultiplier, 6);
        }
        return $this->effectiveQuantity;
    }

    public function getWasteQuantity(): string
    {
        if ($this->wasteQuantity === null) {
            $effective = $this->getEffectiveQuantity();
            $this->wasteQuantity = bcsub($effective, $this->quantityRequired, 6);
        }
        return $this->wasteQuantity;
    }

    public function getComponentCost(): ?string
    {
        return $this->component->getCostPrice();
    }

    public function getComponentUnit(): ?string
    {
        return $this->component->getUnit()?->getSymbol();
    }

    public function calculateCostForQuantity(string $quantity): ?string
    {
        $componentCost = $this->getComponentCost();
        if ($componentCost === null) {
            return null;
        }

        $effectiveQuantity = $this->getEffectiveQuantity();
        $unitCost = bcdiv($effectiveQuantity, $this->bom->getQuantityProduced(), 6);
        $requiredQuantity = bcmul($unitCost, $quantity, 6);

        return bcmul($requiredQuantity, $componentCost, 4);
    }

    public function getRequiredQuantityForProduction(string $quantityToProduce): string
    {
        $ratio = bcdiv($quantityToProduce, $this->bom->getQuantityProduced(), 6);
        return bcmul($this->getEffectiveQuantity(), $ratio, 6);
    }

    public function updateFromArray(array $data): self
    {
        if (isset($data['quantityRequired'])) {
            $this->quantityRequired = $data['quantityRequired'];
        }

        if (isset($data['wasteFactor'])) {
            $this->wasteFactor = $data['wasteFactor'];
        }

        if (isset($data['sequence'])) {
            $this->sequence = $data['sequence'];
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
            'bomId' => $this->bom->getUuid(),
            'component' => $this->component->toArray(),
            'quantityRequired' => $this->quantityRequired,
            'effectiveQuantity' => $this->getEffectiveQuantity(),
            'wasteFactor' => $this->wasteFactor,
            'wasteQuantity' => $this->getWasteQuantity(),
            'sequence' => $this->sequence,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'componentCost' => $this->getComponentCost(),
            'componentUnit' => $this->getComponentUnit(),
        ];
    }
}
