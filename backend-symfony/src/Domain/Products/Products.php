<?php

namespace App\Domain\Products;

use App\Domain\MeasurementUnits\MeasurementUnits;
use App\Domain\ProductCategories\ProductCategories;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductsRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\HasLifecycleCallbacks]
class Products
{
    // Types de produits
    public const TYPE_RAW_MATERIAL = 'raw_material';      // Matière première
    public const TYPE_SEMI_FINISHED = 'semi_finished';    // Produit semi-fini
    public const TYPE_FINISHED_GOOD = 'finished_good';    // Produit fini
    public const TYPE_SERVICE = 'service';                // Service
    public const TYPE_KIT = 'kit';                        // Kit/ensemble

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['product:read'])]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 100)]
    #[Assert\Regex('/^[A-Z0-9\-_\.]+$/')] // Format SKU standard
    #[Groups(['product:read', 'product:write'])]
    private string $sku;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $barcode = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['product:read', 'product:write'])]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice([
        self::TYPE_RAW_MATERIAL,
        self::TYPE_SEMI_FINISHED,
        self::TYPE_FINISHED_GOOD,
        self::TYPE_SERVICE,
        self::TYPE_KIT
    ])]
    #[Groups(['product:read', 'product:write'])]
    private string $productType;

    #[ORM\ManyToOne(targetEntity: ProductCategories::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    private ?ProductCategories $category = null;

    #[ORM\ManyToOne(targetEntity: MeasurementUnits::class)]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write'])]
    private MeasurementUnits $unit;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write'])]
    private ?string $weight = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write'])]
    private ?string $volume = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write'])]
    private ?string $costPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write'])]
    private ?string $sellingPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write'])]
    private string $minStockLevel = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write'])]
    private ?string $maxStockLevel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['product:read', 'product:write'])]
    private ?string $reorderPoint = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['product:read', 'product:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['product:read', 'product:write'])]
    private array $attributes = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['product:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Relations inversées
    #[ORM\OneToMany(targetEntity: 'App\Domain\StockLocations\StockLocations', mappedBy: 'product')]
    private $stockLocations;

    #[ORM\OneToMany(targetEntity: 'App\Domain\StockMovements\StockMovements', mappedBy: 'product')]
    private $stockMovements;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
        $this->stockLocations = new \Doctrine\Common\Collections\ArrayCollection();
        $this->stockMovements = new \Doctrine\Common\Collections\ArrayCollection();

        // Attributs par défaut
        $this->attributes = [
            'supplier_id' => null,
            'supplier_code' => null,
            'brand' => null,
            'model' => null,
            'color' => null,
            'size' => null,
            'material' => null,
            'warranty_months' => null,
            'lead_time_days' => 7,
            'tax_rate' => null, // Hérite de la catégorie si null
            'is_taxable' => true,
            'is_perishable' => false,
            'shelf_life_days' => null,
            'requires_serial_number' => false,
            'requires_batch_tracking' => false,
            'image_url' => null,
            'documents' => [],
            'custom_fields' => [],
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): self
    {
        $this->sku = strtoupper(trim($sku));
        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): self
    {
        $this->barcode = $barcode;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getProductType(): string
    {
        return $this->productType;
    }

    public function setProductType(string $productType): self
    {
        $this->productType = $productType;
        return $this;
    }

    public function getCategory(): ?ProductCategories
    {
        return $this->category;
    }

    public function setCategory(?ProductCategories $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getUnit(): MeasurementUnits
    {
        return $this->unit;
    }

    public function setUnit(MeasurementUnits $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function getVolume(): ?string
    {
        return $this->volume;
    }

    public function setVolume(?string $volume): self
    {
        $this->volume = $volume;
        return $this;
    }

    public function getCostPrice(): ?string
    {
        return $this->costPrice;
    }

    public function setCostPrice(?string $costPrice): self
    {
        $this->costPrice = $costPrice;
        return $this;
    }

    public function getSellingPrice(): ?string
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(?string $sellingPrice): self
    {
        $this->sellingPrice = $sellingPrice;
        return $this;
    }

    public function getMinStockLevel(): string
    {
        return $this->minStockLevel;
    }

    public function setMinStockLevel(string $minStockLevel): self
    {
        $this->minStockLevel = $minStockLevel;
        return $this;
    }

    public function getMaxStockLevel(): ?string
    {
        return $this->maxStockLevel;
    }

    public function setMaxStockLevel(?string $maxStockLevel): self
    {
        $this->maxStockLevel = $maxStockLevel;
        return $this;
    }

    public function getReorderPoint(): ?string
    {
        return $this->reorderPoint;
    }

    public function setReorderPoint(?string $reorderPoint): self
    {
        $this->reorderPoint = $reorderPoint;
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

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;
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

    public function getStockLocations()
    {
        return $this->stockLocations;
    }

    public function getStockMovements()
    {
        return $this->stockMovements;
    }

    // Méthodes métier importantes

    public function getMargin(): ?float
    {
        if ($this->costPrice === null || $this->sellingPrice === null) {
            return null;
        }

        $cost = (float) $this->costPrice;
        $selling = (float) $this->sellingPrice;

        if ($cost === 0.0) {
            return null;
        }

        return (($selling - $cost) / $cost) * 100;
    }

    public function getMarginAmount(): ?float
    {
        if ($this->costPrice === null || $this->sellingPrice === null) {
            return null;
        }

        return (float) $this->sellingPrice - (float) $this->costPrice;
    }

    public function getTaxRate(): ?float
    {
        // Priorité : attribut produit → catégorie → null
        $productTax = $this->getAttribute('tax_rate');

        if ($productTax !== null) {
            return (float) $productTax;
        }

        if ($this->category !== null) {
            $categoryTax = $this->category->getAttribute('tax_rate');
            if ($categoryTax !== null) {
                return (float) $categoryTax;
            }
        }

        return null;
    }

    public function getSellingPriceWithTax(): ?float
    {
        if ($this->sellingPrice === null) {
            return null;
        }

        $taxRate = $this->getTaxRate();
        if ($taxRate === null || !$this->getAttribute('is_taxable', true)) {
            return (float) $this->sellingPrice;
        }

        return (float) $this->sellingPrice * (1 + ($taxRate / 100));
    }

    public function getCostPriceWithTax(): ?float
    {
        if ($this->costPrice === null) {
            return null;
        }

        $taxRate = $this->getTaxRate();
        if ($taxRate === null || !$this->getAttribute('is_taxable', true)) {
            return (float) $this->costPrice;
        }

        return (float) $this->costPrice * (1 + ($taxRate / 100));
    }

    public function getProductTypeLabel(): string
    {
        $labels = [
            self::TYPE_RAW_MATERIAL => 'Matière première',
            self::TYPE_SEMI_FINISHED => 'Produit semi-fini',
            self::TYPE_FINISHED_GOOD => 'Produit fini',
            self::TYPE_SERVICE => 'Service',
            self::TYPE_KIT => 'Kit/Ensemble',
        ];

        return $labels[$this->productType] ?? $this->productType;
    }

    public function isRawMaterial(): bool
    {
        return $this->productType === self::TYPE_RAW_MATERIAL;
    }

    public function isFinishedGood(): bool
    {
        return $this->productType === self::TYPE_FINISHED_GOOD;
    }

    public function isService(): bool
    {
        return $this->productType === self::TYPE_SERVICE;
    }

    public function getDensity(): ?float
    {
        if ($this->weight === null || $this->volume === null || (float) $this->volume === 0.0) {
            return null;
        }

        return (float) $this->weight / (float) $this->volume; // kg/m³
    }

    public function calculateVolumeFromWeight(float $weight): ?float
    {
        $density = $this->getDensity();
        if ($density === null || $density === 0.0) {
            return null;
        }

        return $weight / $density;
    }

    public function calculateWeightFromVolume(float $volume): ?float
    {
        $density = $this->getDensity();
        if ($density === null) {
            return null;
        }

        return $volume * $density;
    }

    public function getStockValue(?string $warehouseId = null): float
    {
        $totalValue = 0.0;
        $costPrice = (float) ($this->costPrice ?? 0);

        foreach ($this->stockLocations as $stockLocation) {
            if ($warehouseId !== null && $stockLocation->getWarehouse()->getId() !== $warehouseId) {
                continue;
            }

            $totalValue += $stockLocation->getQuantityOnHand() * $costPrice;
        }

        return $totalValue;
    }

    public function getTotalStock(): float
    {
        $total = 0.0;

        foreach ($this->stockLocations as $stockLocation) {
            $total += $stockLocation->getQuantityOnHand();
        }

        return $total;
    }

    public function getReservedStock(): float
    {
        $total = 0.0;

        foreach ($this->stockLocations as $stockLocation) {
            $total += $stockLocation->getQuantityReserved();
        }

        return $total;
    }

    public function getAvailableStock(): float
    {
        return $this->getTotalStock() - $this->getReservedStock();
    }

    public function isBelowReorderPoint(): bool
    {
        if ($this->reorderPoint === null) {
            return false;
        }

        return $this->getAvailableStock() <= (float) $this->reorderPoint;
    }

    public function isOutOfStock(): bool
    {
        return $this->getAvailableStock() <= 0;
    }

    public function needsReorder(): bool
    {
        return $this->isBelowReorderPoint() && !$this->isOutOfStock();
    }

    public function isOverstocked(): bool
    {
        if ($this->maxStockLevel === null) {
            return false;
        }

        return $this->getTotalStock() > (float) $this->maxStockLevel;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): self
    {
        $this->deletedAt = new \DateTime();
        $this->isActive = false;
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function toArray(bool $includeStock = false, bool $includeMovements = false): array
    {
        $data = [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'product_type' => $this->productType,
            'product_type_label' => $this->getProductTypeLabel(),
            'category_id' => $this->category?->getId(),
            'category_name' => $this->category?->getName(),
            'unit_id' => $this->unit->getId(),
            'unit_symbol' => $this->unit->getSymbol(),
            'weight' => $this->weight,
            'volume' => $this->volume,
            'density' => $this->getDensity(),
            'cost_price' => $this->costPrice,
            'selling_price' => $this->sellingPrice,
            'selling_price_with_tax' => $this->getSellingPriceWithTax(),
            'margin_percent' => $this->getMargin(),
            'margin_amount' => $this->getMarginAmount(),
            'min_stock_level' => $this->minStockLevel,
            'max_stock_level' => $this->maxStockLevel,
            'reorder_point' => $this->reorderPoint,
            'tax_rate' => $this->getTaxRate(),
            'is_active' => $this->isActive,
            'attributes' => $this->attributes,
            'total_stock' => $this->getTotalStock(),
            'reserved_stock' => $this->getReservedStock(),
            'available_stock' => $this->getAvailableStock(),
            'stock_value' => $this->getStockValue(),
            'is_below_reorder_point' => $this->isBelowReorderPoint(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'needs_reorder' => $this->needsReorder(),
            'is_overstocked' => $this->isOverstocked(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'is_deleted' => $this->isDeleted(),
        ];

        if ($includeStock) {
            $data['stock_locations'] = array_map(
                fn($location) => $location->toArray(),
                $this->stockLocations->toArray()
            );
        }

        if ($includeMovements) {
            $data['recent_movements'] = array_map(
                fn($movement) => $movement->toArray(),
                $this->stockMovements->slice(0, 10) // 10 derniers mouvements
            );
        }

        return $data;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->sku, $this->name);
    }
}
