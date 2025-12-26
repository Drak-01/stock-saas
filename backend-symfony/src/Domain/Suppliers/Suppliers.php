<?php

namespace App\Domain\Suppliers;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SuppliersRepository::class)]
#[ORM\Table(name: 'suppliers')]
#[ORM\HasLifecycleCallbacks]
class Suppliers
{
    // Catégories de fournisseurs
    public const CATEGORY_RAW_MATERIAL = 'raw_material';    // Matières premières
    public const CATEGORY_COMPONENTS = 'components';        // Composants
    public const CATEGORY_PACKAGING = 'packaging';          // Emballages
    public const CATEGORY_EQUIPMENT = 'equipment';          // Équipements
    public const CATEGORY_SERVICES = 'services';            // Services
    public const CATEGORY_LOGISTICS = 'logistics';          // Transport/logistique

    // Conditions de paiement
    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_NET_7 = 'net_7';
    public const PAYMENT_NET_30 = 'net_30';
    public const PAYMENT_NET_60 = 'net_60';
    public const PAYMENT_NET_90 = 'net_90';
    public const PAYMENT_50_ADVANCE = '50_advance';
    public const PAYMENT_100_ADVANCE = '100_advance';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['supplier:read'])]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex('/^[A-Z0-9\-_]+$/')]
    #[Groups(['supplier:read', 'supplier:write'])]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $contactPerson = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Choice([
        self::PAYMENT_CASH,
        self::PAYMENT_NET_7,
        self::PAYMENT_NET_30,
        self::PAYMENT_NET_60,
        self::PAYMENT_NET_90,
        self::PAYMENT_50_ADVANCE,
        self::PAYMENT_100_ADVANCE
    ])]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $paymentTerms = self::PAYMENT_NET_30;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $taxId = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['supplier:read', 'supplier:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['supplier:read', 'supplier:write'])]
    private array $attributes = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['supplier:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Relations inversées
    #[ORM\OneToMany(mappedBy: 'supplier', targetEntity: 'App\Domain\PurchaseOrders\PurchaseOrders')]
    private $purchaseOrders;

    #[ORM\OneToMany(mappedBy: 'supplier', targetEntity: 'App\Domain\Products\Products')]
    private $products;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
        $this->purchaseOrders = new \Doctrine\Common\Collections\ArrayCollection();
        $this->products = new \Doctrine\Common\Collections\ArrayCollection();

        // Attributs par défaut
        $this->attributes = [
            'category' => self::CATEGORY_RAW_MATERIAL,
            'lead_time_days' => 7,               // Délai de livraison moyen
            'minimum_order_value' => null,       // Valeur minimale de commande
            'currency' => 'EUR',                 // Devise par défaut
            'incoterms' => 'EXW',                // Conditions d'expédition
            'quality_rating' => 3,               // Note qualité 1-5
            'delivery_rating' => 3,              // Note livraison 1-5
            'payment_rating' => 3,               // Note paiement 1-5
            'overall_rating' => 3,               // Note globale
            'bank_details' => null,              // Coordonnées bancaires
            'website' => null,
            'country' => null,
            'is_preferred' => false,             // Fournisseur préféré
            'is_approved' => true,               // Fournisseur approuvé
            'risk_level' => 'medium',            // Niveau de risque
            'notes' => null,                     // Notes internes
            'certifications' => [],              // Certifications (ISO, etc.)
            'contract_expiry' => null,           // Date d'expiration du contrat
            'discount_rate' => 0.0,              // Remise générale en %
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

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): self
    {
        $this->contactPerson = $contactPerson;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email ? strtolower(trim($email)) : null;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
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

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): self
    {
        $this->paymentTerms = $paymentTerms;
        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): self
    {
        $this->taxId = $taxId;
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

    public function getPurchaseOrders()
    {
        return $this->purchaseOrders;
    }

    public function getProducts()
    {
        return $this->products;
    }

    // ==================== MÉTHODES MÉTIER ESSENTIELLES ====================

    /**
     * Catégorie du fournisseur
     */
    public function getCategory(): string
    {
        return $this->getAttribute('category', self::CATEGORY_RAW_MATERIAL);
    }

    public function setCategory(string $category): self
    {
        $this->setAttribute('category', $category);
        return $this;
    }

    public function getCategoryLabel(): string
    {
        $labels = [
            self::CATEGORY_RAW_MATERIAL => 'Matières premières',
            self::CATEGORY_COMPONENTS => 'Composants',
            self::CATEGORY_PACKAGING => 'Emballages',
            self::CATEGORY_EQUIPMENT => 'Équipements',
            self::CATEGORY_SERVICES => 'Services',
            self::CATEGORY_LOGISTICS => 'Logistique',
        ];

        return $labels[$this->getCategory()] ?? $this->getCategory();
    }

    /**
     * Délai de livraison en jours
     */
    public function getLeadTimeDays(): int
    {
        return (int) $this->getAttribute('lead_time_days', 7);
    }

    public function setLeadTimeDays(int $days): self
    {
        $this->setAttribute('lead_time_days', $days);
        return $this;
    }

    /**
     * Date de livraison estimée
     */
    public function getEstimatedDeliveryDate(\DateTimeInterface $orderDate = null): \DateTimeInterface
    {
        $orderDate = $orderDate ?? new \DateTime();
        $leadTime = $this->getLeadTimeDays();

        return (clone $orderDate)->modify("+{$leadTime} days");
    }

    /**
     * Fournisseur préféré
     */
    public function isPreferred(): bool
    {
        return $this->getAttribute('is_preferred', false) === true;
    }

    public function setPreferred(bool $preferred): self
    {
        $this->setAttribute('is_preferred', $preferred);
        return $this;
    }

    /**
     * Fournisseur approuvé
     */
    public function isApproved(): bool
    {
        return $this->getAttribute('is_approved', true) === true;
    }

    public function setApproved(bool $approved): self
    {
        $this->setAttribute('is_approved', $approved);
        return $this;
    }

    /**
     * Niveau de risque
     */
    public function getRiskLevel(): string
    {
        return $this->getAttribute('risk_level', 'medium');
    }

    public function setRiskLevel(string $riskLevel): self
    {
        $this->setAttribute('risk_level', $riskLevel);
        return $this;
    }

    /**
     * Calcul du score global du fournisseur
     */
    public function calculateOverallRating(): float
    {
        $quality = (float) $this->getAttribute('quality_rating', 3);
        $delivery = (float) $this->getAttribute('delivery_rating', 3);
        $payment = (float) $this->getAttribute('payment_rating', 3);

        // Pondération : qualité 40%, livraison 40%, paiement 20%
        $overall = ($quality * 0.4) + ($delivery * 0.4) + ($payment * 0.2);

        $this->setAttribute('overall_rating', round($overall, 1));

        return $overall;
    }

    /**
     * Vérifie si le fournisseur est fiable
     */
    public function isReliable(): bool
    {
        $this->calculateOverallRating();
        $overall = (float) $this->getAttribute('overall_rating', 3);

        return $overall >= 3.5; // Seuil de fiabilité
    }

    /**
     * Montant total des achats
     */
    public function getTotalPurchases(): float
    {
        $total = 0.0;

        foreach ($this->purchaseOrders as $order) {
            if ($order->getStatus() === 'completed') {
                $total += (float) $order->getTotalAmount();
            }
        }

        return $total;
    }

    /**
     * Valeur moyenne des commandes
     */
    public function getAverageOrderValue(): ?float
    {
        $completedOrders = array_filter(
            $this->purchaseOrders->toArray(),
            fn($order) => $order->getStatus() === 'completed'
        );

        if (empty($completedOrders)) {
            return null;
        }

        return $this->getTotalPurchases() / count($completedOrders);
    }

    /**
     * Fréquence des commandes (jours entre les commandes)
     */
    public function getOrderFrequency(): ?float
    {
        $completedOrders = array_filter(
            $this->purchaseOrders->toArray(),
            fn($order) => $order->getStatus() === 'completed'
        );

        if (count($completedOrders) < 2) {
            return null;
        }

        // Trier par date
        usort($completedOrders, fn($a, $b) => $a->getOrderDate() <=> $b->getOrderDate());

        $firstDate = reset($completedOrders)->getOrderDate();
        $lastDate = end($completedOrders)->getOrderDate();

        $daysBetween = $lastDate->diff($firstDate)->days;

        return $daysBetween / (count($completedOrders) - 1);
    }

    /**
     * Dernière date de commande
     */
    public function getLastOrderDate(): ?\DateTimeInterface
    {
        $lastOrder = null;

        foreach ($this->purchaseOrders as $order) {
            if ($order->getStatus() === 'completed') {
                if ($lastOrder === null || $order->getOrderDate() > $lastOrder->getOrderDate()) {
                    $lastOrder = $order;
                }
            }
        }

        return $lastOrder?->getOrderDate();
    }

    /**
     * Jours depuis la dernière commande
     */
    public function getDaysSinceLastOrder(): ?int
    {
        $lastOrder = $this->getLastOrderDate();

        if ($lastOrder === null) {
            return null;
        }

        return (new \DateTime())->diff($lastOrder)->days;
    }

    /**
     * Taux de livraison à temps
     */
    public function getOnTimeDeliveryRate(): ?float
    {
        $completedOrders = array_filter(
            $this->purchaseOrders->toArray(),
            fn($order) => $order->getStatus() === 'completed'
        );

        if (empty($completedOrders)) {
            return null;
        }

        $onTime = 0;

        foreach ($completedOrders as $order) {
            $expectedDate = $order->getExpectedDeliveryDate();
            $actualDate = $order->getDeliveryDate();

            if ($expectedDate && $actualDate && $actualDate <= $expectedDate) {
                $onTime++;
            }
        }

        return ($onTime / count($completedOrders)) * 100;
    }

    /**
     * Taux de qualité (pas de retours/rejets)
     */
    public function getQualityAcceptanceRate(): ?float
    {
        $completedOrders = array_filter(
            $this->purchaseOrders->toArray(),
            fn($order) => $order->getStatus() === 'completed'
        );

        if (empty($completedOrders)) {
            return null;
        }

        $accepted = 0;
        $totalLines = 0;

        foreach ($completedOrders as $order) {
            foreach ($order->getPurchaseOrderLines() as $line) {
                $totalLines++;
                if ($line->getQuantityReceived() === $line->getQuantityOrdered()) {
                    $accepted++;
                }
            }
        }

        return $totalLines > 0 ? ($accepted / $totalLines) * 100 : null;
    }

    /**
     * Impact sur la trésorerie (avances nécessaires)
     */
    public function getCashFlowImpact(): string
    {
        $terms = $this->paymentTerms ?? self::PAYMENT_NET_30;

        return match($terms) {
            self::PAYMENT_CASH => 'Immédiat (trésorerie impactée)',
            self::PAYMENT_50_ADVANCE, self::PAYMENT_100_ADVANCE => 'Avance nécessaire (risque trésorerie)',
            self::PAYMENT_NET_7, self::PAYMENT_NET_30 => 'Standard (bon pour trésorerie)',
            self::PAYMENT_NET_60, self::PAYMENT_NET_90 => 'Délai favorable (excellent pour trésorerie)',
            default => 'Standard',
        };
    }

    /**
     * Vérifie si le fournisseur respecte la valeur minimale de commande
     */
    public function meetsMinimumOrderValue(float $orderValue): bool
    {
        $minimum = $this->getAttribute('minimum_order_value');

        if ($minimum === null) {
            return true;
        }

        return $orderValue >= (float) $minimum;
    }

    /**
     * Applique la remise fournisseur sur un montant
     */
    public function applyDiscount(float $amount): float
    {
        $discountRate = $this->getAttribute('discount_rate', 0.0);

        if ($discountRate > 0) {
            return $amount * (1 - ($discountRate / 100));
        }

        return $amount;
    }

    /**
     * Vérifie si le contrat est expiré
     */
    public function isContractExpired(): bool
    {
        $expiry = $this->getAttribute('contract_expiry');

        if ($expiry === null) {
            return false;
        }

        $expiryDate = \DateTime::createFromFormat('Y-m-d', $expiry);

        return $expiryDate && $expiryDate < new \DateTime();
    }

    /**
     * Jours avant expiration du contrat
     */
    public function getDaysUntilContractExpiry(): ?int
    {
        $expiry = $this->getAttribute('contract_expiry');

        if ($expiry === null) {
            return null;
        }

        $expiryDate = \DateTime::createFromFormat('Y-m-d', $expiry);

        if (!$expiryDate) {
            return null;
        }

        $today = new \DateTime();
        return $today->diff($expiryDate)->days;
    }

    /**
     * Génère un code fournisseur automatique
     */
    public static function generateCode(string $name, string $category = self::CATEGORY_RAW_MATERIAL): string
    {
        $prefix = match($category) {
            self::CATEGORY_RAW_MATERIAL => 'RM',
            self::CATEGORY_COMPONENTS => 'COMP',
            self::CATEGORY_PACKAGING => 'PKG',
            self::CATEGORY_EQUIPMENT => 'EQP',
            self::CATEGORY_SERVICES => 'SVC',
            self::CATEGORY_LOGISTICS => 'LOG',
            default => 'SUP'
        };

        // Extraire les premières lettres des mots significatifs
        $words = preg_split('/\s+/', strtoupper($name));
        $acronym = '';

        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, ['ET', 'DE', 'LE', 'LA', 'LES', 'DU', 'DES', 'AND', 'THE'])) {
                $acronym .= $word[0];
            }
        }

        return $prefix . '-' . substr($acronym, 0, 3);
    }

    public function getPaymentTermsLabel(): string
    {
        $labels = [
            self::PAYMENT_CASH => 'Comptant',
            self::PAYMENT_NET_7 => 'Net 7 jours',
            self::PAYMENT_NET_30 => 'Net 30 jours',
            self::PAYMENT_NET_60 => 'Net 60 jours',
            self::PAYMENT_NET_90 => 'Net 90 jours',
            self::PAYMENT_50_ADVANCE => '50% d\'acompte',
            self::PAYMENT_100_ADVANCE => '100% d\'acompte',
        ];

        return $labels[$this->paymentTerms ?? self::PAYMENT_NET_30] ?? $this->paymentTerms;
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

    public function suspend(): self
    {
        $this->isActive = false;
        return $this;
    }

    public function activate(): self
    {
        $this->isActive = true;
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function toArray(bool $includeOrders = false, bool $includeProducts = false): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'contact_person' => $this->contactPerson,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'payment_terms' => $this->paymentTerms,
            'payment_terms_label' => $this->getPaymentTermsLabel(),
            'cash_flow_impact' => $this->getCashFlowImpact(),
            'tax_id' => $this->taxId,
            'category' => $this->getCategory(),
            'category_label' => $this->getCategoryLabel(),
            'lead_time_days' => $this->getLeadTimeDays(),
            'is_active' => $this->isActive,
            'is_preferred' => $this->isPreferred(),
            'is_approved' => $this->isApproved(),
            'is_reliable' => $this->isReliable(),
            'risk_level' => $this->getRiskLevel(),
            'total_purchases' => $this->getTotalPurchases(),
            'average_order_value' => $this->getAverageOrderValue(),
            'order_frequency' => $this->getOrderFrequency(),
            'last_order_date' => $this->getLastOrderDate()?->format('Y-m-d'),
            'days_since_last_order' => $this->getDaysSinceLastOrder(),
            'on_time_delivery_rate' => $this->getOnTimeDeliveryRate(),
            'quality_acceptance_rate' => $this->getQualityAcceptanceRate(),
            'overall_rating' => $this->calculateOverallRating(),
            'is_contract_expired' => $this->isContractExpired(),
            'days_until_contract_expiry' => $this->getDaysUntilContractExpiry(),
            'attributes' => $this->attributes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'is_deleted' => $this->isDeleted(),
        ];

        if ($includeOrders) {
            $data['purchase_orders'] = array_map(
                fn($order) => $order->toArray(),
                $this->purchaseOrders->slice(0, 10) // 10 dernières commandes
            );

            $data['orders_summary'] = [
                'total_orders' => $this->purchaseOrders->count(),
                'completed_orders' => count(array_filter(
                    $this->purchaseOrders->toArray(),
                    fn($order) => $order->getStatus() === 'completed'
                )),
                'pending_orders' => count(array_filter(
                    $this->purchaseOrders->toArray(),
                    fn($order) => $order->getStatus() === 'pending'
                )),
                'cancelled_orders' => count(array_filter(
                    $this->purchaseOrders->toArray(),
                    fn($order) => $order->getStatus() === 'cancelled'
                )),
            ];
        }

        if ($includeProducts) {
            $data['products'] = array_map(
                fn($product) => $product->toArray(),
                $this->products->slice(0, 10) // 10 premiers produits
            );
        }

        return $data;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->code, $this->name);
    }
}
