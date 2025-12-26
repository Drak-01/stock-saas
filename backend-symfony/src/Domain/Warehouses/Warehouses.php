<?php

namespace App\Domain\Warehouses;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WarehousesRepository::class)]
#[ORM\Table(name: 'warehouses')]
#[ORM\HasLifecycleCallbacks]
class Warehouses
{
    // Types d'entrepôts prédéfinis
    public const TYPE_WAREHOUSE = 'warehouse';      // Entrepôt classique
    public const TYPE_STORE = 'store';              // Point de vente
    public const TYPE_PRODUCTION = 'production';    // Atelier de production
    public const TYPE_VIRTUAL = 'virtual';          // Stock virtuel (dropshipping)
    public const TYPE_TRANSIT = 'transit';          // En transit
    public const TYPE_RETURNS = 'returns';          // Zone de retours

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['warehouse:read'])]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    #[Assert\Regex('/^[A-Z0-9\-_]+$/')] // Code en majuscules
    #[Groups(['warehouse:read', 'warehouse:write'])]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['warehouse:read', 'warehouse:write'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice([
        self::TYPE_WAREHOUSE,
        self::TYPE_STORE,
        self::TYPE_PRODUCTION,
        self::TYPE_VIRTUAL,
        self::TYPE_TRANSIT,
        self::TYPE_RETURNS
    ])]
    #[Groups(['warehouse:read', 'warehouse:write'])]
    private string $type;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['warehouse:read', 'warehouse:write'])]
    private ?string $address = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['warehouse:read', 'warehouse:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['warehouse:read', 'warehouse:write'])]
    private array $settings = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['warehouse:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();

        // Paramètres par défaut
        $this->settings = [
            'allow_negative_stock' => false,
            'require_location_for_movements' => false,
            'default_location' => 'A-01',
            'contact_person' => null,
            'phone' => null,
            'email' => null,
            'operating_hours' => null,
            'capacity' => null, // Capacité en m³ ou palettes
            'temperature_zone' => 'ambient', // ambient, refrigerated, frozen
            'is_default' => false, // Entrepôt par défaut pour l'entreprise
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper(trim($code));
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        // Ajuster les settings selon le type
        $this->adjustSettingsForType();

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
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

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): self
    {
        $this->settings = array_merge($this->settings, $settings);
        return $this;
    }

    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, $value): self
    {
        $this->settings[$key] = $value;
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

    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_WAREHOUSE => 'Entrepôt',
            self::TYPE_STORE => 'Magasin',
            self::TYPE_PRODUCTION => 'Production',
            self::TYPE_VIRTUAL => 'Virtuel',
            self::TYPE_TRANSIT => 'Transit',
            self::TYPE_RETURNS => 'Retours',
        ];

        return $labels[$this->type] ?? $this->type;
    }

    public function isPhysical(): bool
    {
        // Les entrepôts virtuels n'ont pas de stock physique
        return !in_array($this->type, [self::TYPE_VIRTUAL, self::TYPE_TRANSIT]);
    }

    public function canReceiveGoods(): bool
    {
        // Certains types ne peuvent pas recevoir de marchandises
        return !in_array($this->type, [self::TYPE_TRANSIT, self::TYPE_RETURNS]);
    }

    public function canShipGoods(): bool
    {
        // Certains types ne peuvent pas expédier
        return !in_array($this->type, [self::TYPE_TRANSIT, self::TYPE_RETURNS]);
    }

    private function adjustSettingsForType(): void
    {
        switch ($this->type) {
            case self::TYPE_STORE:
                $this->settings['allow_negative_stock'] = true; // Vente possible même si stock théoriquement épuisé
                $this->settings['require_location_for_movements'] = false; // Magasin simple
                break;

            case self::TYPE_PRODUCTION:
                $this->settings['allow_negative_stock'] = false; // Production planifiée
                $this->settings['require_location_for_movements'] = true; // Traçabilité importante
                break;

            case self::TYPE_VIRTUAL:
                $this->settings['allow_negative_stock'] = true; // Dropshipping
                $this->settings['require_location_for_movements'] = false;
                break;

            case self::TYPE_WAREHOUSE:
            default:
                $this->settings['allow_negative_stock'] = false;
                $this->settings['require_location_for_movements'] = true;
                break;
        }
    }

    public function getFullAddress(): string
    {
        $parts = [];

        if ($this->name) {
            $parts[] = $this->name;
        }

        if ($this->address) {
            $parts[] = $this->address;
        }

        return implode("\n", $parts);
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
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'address' => $this->address,
            'full_address' => $this->getFullAddress(),
            'is_active' => $this->isActive,
            'is_physical' => $this->isPhysical(),
            'can_receive_goods' => $this->canReceiveGoods(),
            'can_ship_goods' => $this->canShipGoods(),
            'settings' => $this->settings,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'is_deleted' => $this->isDeleted(),
        ];
    }
}
