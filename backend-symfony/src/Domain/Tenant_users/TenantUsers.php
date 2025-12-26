<?php

namespace App\Domain\TenantUsers;

use App\Repository\TenantUsersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TenantUsersRepository::class)]
#[ORM\Table(name: 'tenant_users')]
#[ORM\HasLifecycleCallbacks]
class TenantUsers
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['tenant_user:read'])]
    private string $id;

    #[ORM\Column(name: 'global_user_id', type: Types::GUID)]
    #[Assert\NotBlank]
    #[Groups(['tenant_user:read'])]
    private string $globalUserId;

    #[ORM\Column(name: 'full_name', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['tenant_user:read', 'tenant_user:write'])]
    private string $fullName;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice([
        'super_admin', 'admin', 'manager',
        'employee', 'viewer', 'guest'
    ])]
    #[Groups(['tenant_user:read', 'tenant_user:write'])]
    private string $role;

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['tenant_user:read', 'tenant_user:write'])]
    private array $permissions = [];

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['tenant_user:read', 'tenant_user:write'])]
    private ?string $department = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['tenant_user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Champs virtuels (non persistés)
    private ?string $email = null; // Récupéré depuis global_users
    private ?bool $isActive = null; // Récupéré depuis global_users

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();

        // Permissions par défaut selon le rôle
        $this->setDefaultPermissions();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGlobalUserId(): string
    {
        return $this->globalUserId;
    }

    public function setGlobalUserId(string $globalUserId): self
    {
        $this->globalUserId = $globalUserId;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = trim($fullName);
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        $this->setDefaultPermissions(); // Mettre à jour les permissions par défaut
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function hasPermission(string $permission): bool
    {
        // Si super_admin, tout est autorisé
        if ($this->role === 'super_admin') {
            return true;
        }

        // Vérification hiérarchique des permissions
        return $this->checkPermissionRecursive($permission, $this->permissions);
    }

    private function checkPermissionRecursive(string $permission, array $permissions): bool
    {
        foreach ($permissions as $key => $value) {
            if ($key === $permission && $value === true) {
                return true;
            }

            if ($key === $permission && is_array($value) && isset($value['enabled']) && $value['enabled'] === true) {
                return true;
            }

            if (is_array($value)) {
                if ($this->checkPermissionRecursive($permission, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): self
    {
        $this->department = $department;
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

    // Getters pour les champs virtuels
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
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

    private function setDefaultPermissions(): void
    {
        $defaultPermissions = [
            'super_admin' => [
                'dashboard' => ['view' => true],
                'products' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'inventory' => ['view' => true, 'adjust' => true, 'transfer' => true],
                'purchases' => ['view' => true, 'create' => true, 'approve' => true],
                'sales' => ['view' => true, 'create' => true, 'cancel' => true],
                'customers' => ['view' => true, 'create' => true, 'edit' => true],
                'suppliers' => ['view' => true, 'create' => true, 'edit' => true],
                'reports' => ['view' => true, 'export' => true],
                'settings' => ['view' => true, 'edit' => true],
                'users' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
            ],
            'admin' => [
                'dashboard' => ['view' => true],
                'products' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'inventory' => ['view' => true, 'adjust' => true, 'transfer' => true],
                'purchases' => ['view' => true, 'create' => true, 'approve' => true],
                'sales' => ['view' => true, 'create' => true, 'cancel' => true],
                'customers' => ['view' => true, 'create' => true, 'edit' => true],
                'suppliers' => ['view' => true, 'create' => true, 'edit' => true],
                'reports' => ['view' => true, 'export' => true],
                'settings' => ['view' => true],
                'users' => ['view' => true],
            ],
            'manager' => [
                'dashboard' => ['view' => true],
                'products' => ['view' => true, 'create' => true, 'edit' => true],
                'inventory' => ['view' => true, 'adjust' => true],
                'purchases' => ['view' => true, 'create' => true],
                'sales' => ['view' => true, 'create' => true],
                'customers' => ['view' => true, 'create' => true],
                'suppliers' => ['view' => true],
                'reports' => ['view' => true],
            ],
            'employee' => [
                'dashboard' => ['view' => true],
                'products' => ['view' => true],
                'inventory' => ['view' => true],
                'purchases' => ['view' => true],
                'sales' => ['view' => true, 'create' => true],
                'customers' => ['view' => true],
            ],
            'viewer' => [
                'dashboard' => ['view' => true],
                'products' => ['view' => true],
                'inventory' => ['view' => true],
                'reports' => ['view' => true],
            ],
            'guest' => [
                'dashboard' => ['view' => true],
            ]
        ];

        // Garder les permissions personnalisées si elles existent déjà
        if (empty($this->permissions)) {
            $this->permissions = $defaultPermissions[$this->role] ?? [];
        }
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function toArray(bool $includeGlobalInfo = false): array
    {
        $data = [
            'id' => $this->id,
            'global_user_id' => $this->globalUserId,
            'full_name' => $this->fullName,
            'role' => $this->role,
            'permissions' => $this->permissions,
            'department' => $this->department,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'is_deleted' => $this->isDeleted(),
        ];

        if ($includeGlobalInfo) {
            $data['email'] = $this->email;
            $data['is_active'] = $this->isActive;
        }

        return $data;
    }

    public function getRoleLabel(): string
    {
        $labels = [
            'super_admin' => 'Super Administrateur',
            'admin' => 'Administrateur',
            'manager' => 'Manager',
            'employee' => 'Employé',
            'viewer' => 'Observateur',
            'guest' => 'Invité',
        ];

        return $labels[$this->role] ?? $this->role;
    }
}
