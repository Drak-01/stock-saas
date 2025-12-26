<?php

namespace App\Domain\ProductCategories;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductCategoriesRepository::class)]
#[ORM\Table(name: 'product_categories')]
#[ORM\HasLifecycleCallbacks]
class ProductCategories
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['category:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['category:read'])]
    private ?ProductCategories $parent = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 50)]
    #[Assert\Regex('/^[A-Z0-9\-_]+$/')] // Code en majuscules
    #[Groups(['category:read', 'category:write'])]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['category:read', 'category:write'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Groups(['category:read'])]
    private string $fullPath;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['category:read', 'category:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['category:read', 'category:write'])]
    private array $attributes = [];

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['category:read', 'category:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['category:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: 'App\Domain\Products\Products')]
    private Collection $products;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
        $this->children = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->attributes = [
            'show_in_menu' => true,
            'require_serial_number' => false,
            'require_expiry_date' => false,
            'tax_rate' => null, // Taux TVA spécifique à la catégorie
            'profit_margin' => null, // Marge par défaut
            'reorder_point_multiplier' => 1.0,
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        // Éviter les références circulaires
        if ($parent !== null && $parent->isAncestorOf($this)) {
            throw new \InvalidArgumentException('Circular reference detected');
        }

        $this->parent = $parent;

        // Mettre à jour le fullPath
        $this->updateFullPath();

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper(trim($code));
        $this->updateFullPath();
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

    public function getFullPath(): string
    {
        return $this->fullPath;
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
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

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function getProducts(): Collection
    {
        return $this->products;
    }

    // Méthodes métier importantes

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    public function getLevel(): int
    {
        if ($this->isRoot()) {
            return 0;
        }

        return $this->parent->getLevel() + 1;
    }

    public function getDepth(): int
    {
        $maxDepth = 0;

        foreach ($this->children as $child) {
            $childDepth = $child->getDepth();
            if ($childDepth > $maxDepth) {
                $maxDepth = $childDepth;
            }
        }

        return $maxDepth + 1;
    }

    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current !== null) {
            $ancestors[] = $current;
            $current = $current->getParent();
        }

        return array_reverse($ancestors);
    }

    public function getDescendants(bool $includeSelf = false): array
    {
        $descendants = $includeSelf ? [$this] : [];

        foreach ($this->children as $child) {
            $descendants = array_merge(
                $descendants,
                $child->getDescendants(true)
            );
        }

        return $descendants;
    }

    public function isAncestorOf(self $category): bool
    {
        $current = $category->getParent();

        while ($current !== null) {
            if ($current->getId() === $this->getId()) {
                return true;
            }
            $current = $current->getParent();
        }

        return false;
    }

    public function getBreadcrumb(): array
    {
        $breadcrumb = [];
        $current = $this;

        while ($current !== null) {
            $breadcrumb[] = [
                'id' => $current->getId(),
                'code' => $current->getCode(),
                'name' => $current->getName(),
                'level' => $current->getLevel(),
            ];
            $current = $current->getParent();
        }

        return array_reverse($breadcrumb);
    }

    public function getHierarchicalName(string $separator = ' > '): string
    {
        $names = array_column($this->getBreadcrumb(), 'name');
        return implode($separator, $names);
    }

    public function updateFullPath(): void
    {
        if ($this->isRoot()) {
            $this->fullPath = $this->code;
        } else {
            $this->fullPath = $this->parent->getFullPath() . '.' . $this->code;
        }

        // Mettre à jour tous les enfants
        foreach ($this->children as $child) {
            $child->updateFullPath();
        }
    }

    public function getProductCount(): int
    {
        $count = $this->products->count();

        foreach ($this->children as $child) {
            $count += $child->getProductCount();
        }

        return $count;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): self
    {
        $this->deletedAt = new \DateTime();
        $this->isActive = false;

        // Désactiver également tous les descendants
        foreach ($this->getDescendants() as $descendant) {
            $descendant->setIsActive(false);
        }

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();

        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }

        // Garantir que fullPath est à jour
        $this->updateFullPath();
    }

    public function toArray(bool $includeChildren = false, bool $includeProducts = false): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'full_path' => $this->fullPath,
            'hierarchical_name' => $this->getHierarchicalName(),
            'description' => $this->description,
            'parent_id' => $this->parent?->getId(),
            'parent_name' => $this->parent?->getName(),
            'level' => $this->getLevel(),
            'depth' => $this->getDepth(),
            'attributes' => $this->attributes,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'is_root' => $this->isRoot(),
            'product_count' => $this->getProductCount(),
            'breadcrumb' => $this->getBreadcrumb(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'is_deleted' => $this->isDeleted(),
        ];

        if ($includeChildren) {
            $data['children'] = array_map(
                fn($child) => $child->toArray(false, false),
                $this->children->toArray()
            );
        }

        if ($includeProducts) {
            $data['products'] = array_map(
                fn($product) => $product->toArray(),
                $this->products->toArray()
            );
        }

        return $data;
    }
}
