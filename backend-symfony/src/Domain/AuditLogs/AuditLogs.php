<?php

namespace App\Domain\AuditLogs;

use App\Domain\TenantUsers\TenantUsers;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditLogsRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_audit_created', columns: ['created_at'])]
class AuditLogs
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['audit_log:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, unique: true)]
    #[Groups(['audit_log:read'])]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: TenantUsers::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    #[Groups(['audit_log:read'])]
    private ?TenantUsers $user = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['audit_log:read'])]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['audit_log:read'])]
    private string $entityType;

    #[ORM\Column(type: Types::GUID, nullable: true)]
    #[Groups(['audit_log:read'])]
    private ?string $entityId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['audit_log:read'])]
    private ?array $oldValues = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['audit_log:read'])]
    private ?array $newValues = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    #[Groups(['audit_log:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['audit_log:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['audit_log:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUser(): ?TenantUsers
    {
        return $this->user;
    }

    public function setUser(?TenantUsers $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function setOldValues(?array $oldValues): self
    {
        $this->oldValues = $oldValues;
        return $this;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function setNewValues(?array $newValues): self
    {
        $this->newValues = $newValues;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Méthodes utilitaires
    public function getActionType(): string
    {
        $actions = [
            'create' => 'Création',
            'update' => 'Modification',
            'delete' => 'Suppression',
            'login' => 'Connexion',
            'logout' => 'Déconnexion',
            'approve' => 'Approbation',
            'reject' => 'Rejet',
            'export' => 'Export',
            'import' => 'Import',
        ];

        return $actions[$this->action] ?? $this->action;
    }

    public function getEntityName(): string
    {
        $entities = [
            'product' => 'Produit',
            'purchase_order' => 'Commande d\'achat',
            'sales_order' => 'Commande client',
            'stock_movement' => 'Mouvement de stock',
            'warehouse' => 'Entrepôt',
            'supplier' => 'Fournisseur',
            'customer' => 'Client',
            'bom' => 'NFE',
            'production_order' => 'Ordre de production',
            'user' => 'Utilisateur',
            'company' => 'Entreprise',
        ];

        return $entities[$this->entityType] ?? $this->entityType;
    }

    public function getChangesSummary(): array
    {
        if (!$this->oldValues || !$this->newValues) {
            return [];
        }

        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($this->oldValues), array_keys($this->newValues)));

        foreach ($allKeys as $key) {
            $oldValue = $this->oldValues[$key] ?? null;
            $newValue = $this->newValues[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    public function hasChanges(): bool
    {
        return !empty($this->getChangesSummary());
    }

    public function getChangeCount(): int
    {
        return count($this->getChangesSummary());
    }

    public function getUsername(): string
    {
        return $this->user ? $this->user->getFullName() : 'System';
    }

    public function getUserEmail(): ?string
    {
        return $this->user ? $this->user->getEmail() : null;
    }

    public function getFormattedTimestamp(): string
    {
        return $this->createdAt->format('d/m/Y H:i:s');
    }

    public function getBrowserInfo(): ?string
    {
        if (!$this->userAgent) {
            return null;
        }

        // Simple browser detection
        if (strpos($this->userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($this->userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($this->userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($this->userAgent, 'Edge') !== false) {
            return 'Edge';
        } elseif (strpos($this->userAgent, 'Opera') !== false) {
            return 'Opera';
        }

        return 'Autre';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user' => $this->user?->toArray(),
            'action' => $this->action,
            'actionType' => $this->getActionType(),
            'entityType' => $this->entityType,
            'entityName' => $this->getEntityName(),
            'entityId' => $this->entityId,
            'oldValues' => $this->oldValues,
            'newValues' => $this->newValues,
            'changesSummary' => $this->getChangesSummary(),
            'changeCount' => $this->getChangeCount(),
            'hasChanges' => $this->hasChanges(),
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'browserInfo' => $this->getBrowserInfo(),
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'formattedTimestamp' => $this->getFormattedTimestamp(),
            'username' => $this->getUsername(),
            'userEmail' => $this->getUserEmail(),
        ];
    }

    public static function create(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?TenantUsers $user = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        $log = new self();
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setUser($user);
        $log->setOldValues($oldValues);
        $log->setNewValues($newValues);
        $log->setIpAddress($ipAddress);
        $log->setUserAgent($userAgent);

        return $log;
    }
}
