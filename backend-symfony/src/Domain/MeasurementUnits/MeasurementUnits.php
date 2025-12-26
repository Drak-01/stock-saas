<?php

namespace App\Domain\MeasurementUnits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MeasurementUnitsRepository::class)]
#[ORM\Table(name: 'measurement_units')]
#[ORM\HasLifecycleCallbacks]
class MeasurementUnits
{
    // Types d'unités prédéfinis
    public const TYPE_WEIGHT = 'weight';    // Poids
    public const TYPE_VOLUME = 'volume';    // Volume
    public const TYPE_COUNT = 'count';      // Comptage
    public const TYPE_LENGTH = 'length';    // Longueur
    public const TYPE_AREA = 'area';        // Surface
    public const TYPE_TIME = 'time';        // Temps

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['measurement_unit:read'])]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 20)]
    #[Assert\Regex('/^[a-zA-Z0-9°µ]+$/')] // Symboles acceptés
    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    private string $symbol;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice([
        self::TYPE_WEIGHT,
        self::TYPE_VOLUME,
        self::TYPE_COUNT,
        self::TYPE_LENGTH,
        self::TYPE_AREA,
        self::TYPE_TIME
    ])]
    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    private string $unitType;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, options: ['default' => 1.0])]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    private string $conversionFactor = '1.0';

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'base_unit_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['measurement_unit:read'])]
    private ?MeasurementUnits $baseUnit = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['measurement_unit:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Relations inversées
    #[ORM\OneToMany(mappedBy: 'baseUnit', targetEntity: self::class)]
    private $derivedUnits;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
        $this->derivedUnits = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
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

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function getUnitType(): string
    {
        return $this->unitType;
    }

    public function setUnitType(string $unitType): self
    {
        $this->unitType = $unitType;
        return $this;
    }

    public function getConversionFactor(): string
    {
        return $this->conversionFactor;
    }

    public function setConversionFactor(string $conversionFactor): self
    {
        $this->conversionFactor = $conversionFactor;
        return $this;
    }

    public function getBaseUnit(): ?self
    {
        return $this->baseUnit;
    }

    public function setBaseUnit(?self $baseUnit): self
    {
        $this->baseUnit = $baseUnit;
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

    public function getDerivedUnits(): \Doctrine\Common\Collections\Collection
    {
        return $this->derivedUnits;
    }

    // Méthodes métier

    public function isBaseUnit(): bool
    {
        return $this->baseUnit === null && $this->conversionFactor == 1;
    }

    public function isDerivedUnit(): bool
    {
        return $this->baseUnit !== null;
    }

    /**
     * Convertit une quantité depuis cette unité vers l'unité de base
     */
    public function convertToBase(float $quantity): float
    {
        if ($this->isBaseUnit()) {
            return $quantity;
        }

        return $quantity * (float) $this->conversionFactor;
    }

    /**
     * Convertit une quantité depuis l'unité de base vers cette unité
     */
    public function convertFromBase(float $quantityInBase): float
    {
        if ($this->isBaseUnit()) {
            return $quantityInBase;
        }

        return $quantityInBase / (float) $this->conversionFactor;
    }

    /**
     * Convertit entre deux unités du même type
     */
    public function convertTo(MeasurementUnits $targetUnit, float $quantity): float
    {
        if ($this->unitType !== $targetUnit->unitType) {
            throw new \InvalidArgumentException(
                sprintf('Cannot convert between different unit types: %s to %s',
                    $this->unitType, $targetUnit->unitType)
            );
        }

        // Convertir vers l'unité de base
        $quantityInBase = $this->convertToBase($quantity);

        // Convertir depuis l'unité de base vers la cible
        return $targetUnit->convertFromBase($quantityInBase);
    }

    public function getUnitTypeLabel(): string
    {
        $labels = [
            self::TYPE_WEIGHT => 'Poids',
            self::TYPE_VOLUME => 'Volume',
            self::TYPE_COUNT => 'Comptage',
            self::TYPE_LENGTH => 'Longueur',
            self::TYPE_AREA => 'Surface',
            self::TYPE_TIME => 'Temps',
        ];

        return $labels[$this->unitType] ?? $this->unitType;
    }

    public function getFullSymbol(): string
    {
        return sprintf('%s (%s)', $this->name, $this->symbol);
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'full_symbol' => $this->getFullSymbol(),
            'unit_type' => $this->unitType,
            'unit_type_label' => $this->getUnitTypeLabel(),
            'conversion_factor' => $this->conversionFactor,
            'is_base_unit' => $this->isBaseUnit(),
            'is_derived_unit' => $this->isDerivedUnit(),
            'base_unit' => $this->baseUnit?->toArray(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'is_deleted' => $this->isDeleted(),
        ];
    }
}
