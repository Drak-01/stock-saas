<?php

namespace App\Domain\Customers;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomersRepository::class)]
#[ORM\Table(name: 'customers')]
#[ORM\HasLifecycleCallbacks]
class Customers
{
    // Types de clients
    public const TYPE_RETAIL = 'retail';        // Détail/particulier
    public const TYPE_WHOLESALE = 'wholesale';  // Gros/revendeur
    public const TYPE_CORPORATE = 'corporate';  // Entreprise
    public const TYPE_ONLINE = 'online';        // E-commerce
    public const TYPE_GOVERNMENT = 'government';// Administration
    public const TYPE_NON_PROFIT = 'non_profit';// Association

    // Conditions de paiement courantes
    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_NET_7 = 'net_7';
    public const PAYMENT_NET_30 = 'net_30';
    public const PAYMENT_NET_60 = 'net_60';
    public const PAYMENT_NET_90 = 'net_90';
    public const PAYMENT_50_ADVANCE = '50_advance';

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['customer:read'])]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex('/^[A-Z0-9\-_]+$/')]
    #[Groups(['customer:read', 'customer:write'])]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['customer:read', 'customer:write'])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'retail'])]
    #[Assert\Choice([
        self::TYPE_RETAIL,
        self::TYPE_WHOLESALE,
        self::TYPE_CORPORATE,
        self::TYPE_ONLINE,
        self::TYPE_GOVERNMENT,
        self::TYPE_NON_PROFIT
    ])]
    #[Groups(['customer:read', 'customer:write'])]
    private string $customerType = self::TYPE_RETAIL;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['customer:read', 'customer:write'])]
    private ?string $contactPerson = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[Groups(['customer:read', 'customer:write'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['customer:read', 'customer:write'])]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['customer:read', 'customer:write'])]
    private ?string $address = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['customer:read', 'customer:write'])]
    private ?string $creditLimit = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Choice([
        self::PAYMENT_CASH,
        self::PAYMENT_NET_7,
        self::PAYMENT_NET_30,
        self::PAYMENT_NET_60,
        self::PAYMENT_NET_90,
        self::PAYMENT_50_ADVANCE
    ])]
    #[Groups(['customer:read', 'customer:write'])]
    private ?string $paymentTerms = self::PAYMENT_NET_30;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['customer:read', 'customer:write'])]
    private ?string $taxId = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['customer:read', 'customer:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    #[Groups(['customer:read', 'customer:write'])]
    private array $attributes = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['customer:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    // Relations inversées
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: 'App\Domain\SalesOrders\SalesOrders')]
    private $salesOrders;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
        $this->salesOrders = new \Doctrine\Common\Collections\ArrayCollection();

        // Attributs par défaut
        $this->attributes = [
            'discount_rate' => 0.0,           // Remise générale en %
            'price_list' => 'default',        // Liste de prix spécifique
            'sales_rep_id' => null,           // Commercial attitré
            'currency' => 'EUR',              // Devise par défaut
            'language' => 'fr',               // Langue préférée
            'shipping_address' => null,       // Adresse de livraison différente
            'billing_address' => null,        // Adresse de facturation
            'notes' => null,                  // Notes internes
            'category' => null,               // Catégorie métier
            'source' => 'manual',             // Comment le client a été acquis
            'credit_rating' => 'good',        // Notation crédit
            'payment_method' => 'bank_transfer', // Méthode de paiement préférée
            'delivery_instructions' => null,  // Instructions de livraison
            'is_tax_exempt' => false,         // Exonération de TVA
            'tax_exempt_certificate' => null, // Certificat d'exonération
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

    public function getCustomerType(): string
    {
        return $this->customerType;
    }

    public function setCustomerType(string $customerType): self
    {
        $this->customerType = $customerType;

        // Ajuster les valeurs par défaut selon le type
        $this->applyTypeDefaults();

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

    public function getCreditLimit(): ?string
    {
        return $this->creditLimit;
    }

    public function setCreditLimit(?string $creditLimit): self
    {
        $this->creditLimit = $creditLimit;
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

    public function getSalesOrders()
    {
        return $this->salesOrders;
    }

    // ==================== MÉTHODES MÉTIER ESSENTIELLES ====================

    /**
     * Applique les valeurs par défaut selon le type de client
     */
    private function applyTypeDefaults(): void
    {
        switch ($this->customerType) {
            case self::TYPE_RETAIL:
                $this->paymentTerms = $this->paymentTerms ?? self::PAYMENT_CASH;
                $this->creditLimit = $this->creditLimit ?? '0';
                $this->setAttribute('discount_rate', 0.0);
                break;

            case self::TYPE_WHOLESALE:
                $this->paymentTerms = $this->paymentTerms ?? self::PAYMENT_NET_30;
                $this->creditLimit = $this->creditLimit ?? '5000';
                $this->setAttribute('discount_rate', 10.0); // 10% de remise pour les revendeurs
                $this->setAttribute('price_list', 'wholesale');
                break;

            case self::TYPE_CORPORATE:
                $this->paymentTerms = $this->paymentTerms ?? self::PAYMENT_NET_60;
                $this->creditLimit = $this->creditLimit ?? '50000';
                $this->setAttribute('discount_rate', 5.0);
                $this->setAttribute('price_list', 'corporate');
                break;

            case self::TYPE_GOVERNMENT:
                $this->paymentTerms = $this->paymentTerms ?? self::PAYMENT_NET_90;
                $this->creditLimit = $this->creditLimit ?? '100000';
                $this->setAttribute('is_tax_exempt', true);
                break;
        }
    }

    /**
     * Vérifie si le client peut passer une commande (crédit disponible)
     */
    public function canPlaceOrder(float $orderAmount): bool
    {
        if ($this->creditLimit === null || (float) $this->creditLimit === 0.0) {
            return true; // Pas de limite de crédit
        }

        $currentBalance = $this->getCurrentBalance();
        $availableCredit = (float) $this->creditLimit - $currentBalance;

        return $orderAmount <= $availableCredit;
    }

    /**
     * Calcule le solde actuel du client (total des factures impayées)
     */
    public function getCurrentBalance(): float
    {
        // Dans une implémentation réelle, on calculerait depuis les factures
        // Ceci est une simplification
        $balance = 0.0;

        foreach ($this->salesOrders as $order) {
            if ($order->getPaymentStatus() !== 'paid') {
                $balance += (float) $order->getTotalAmount();
            }
        }

        return $balance;
    }

    /**
     * Crédit disponible
     */
    public function getAvailableCredit(): ?float
    {
        if ($this->creditLimit === null) {
            return null;
        }

        return (float) $this->creditLimit - $this->getCurrentBalance();
    }

    /**
     * Taux d'utilisation du crédit en %
     */
    public function getCreditUtilization(): ?float
    {
        if ($this->creditLimit === null || (float) $this->creditLimit === 0.0) {
            return null;
        }

        $balance = $this->getCurrentBalance();

        return ($balance / (float) $this->creditLimit) * 100;
    }

    /**
     * Nombre de jours de paiement moyens
     */
    public function getAveragePaymentDays(): ?float
    {
        // Calculer à partir de l'historique des paiements
        // Ceci est une simplification
        $termsMapping = [
            self::PAYMENT_CASH => 0,
            self::PAYMENT_NET_7 => 7,
            self::PAYMENT_NET_30 => 30,
            self::PAYMENT_NET_60 => 60,
            self::PAYMENT_NET_90 => 90,
            self::PAYMENT_50_ADVANCE => 0,
        ];

        return $termsMapping[$this->paymentTerms ?? self::PAYMENT_NET_30] ?? 30;
    }

    /**
     * Vérifie si le client est en retard de paiement
     */
    public function isOverdue(): bool
    {
        foreach ($this->salesOrders as $order) {
            if ($order->isOverdue()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Montant total des retards de paiement
     */
    public function getOverdueAmount(): float
    {
        $total = 0.0;

        foreach ($this->salesOrders as $order) {
            if ($order->isOverdue()) {
                $total += (float) $order->getTotalAmount();
            }
        }

        return $total;
    }

    /**
     * Valeur client (total des achats)
     */
    public function getLifetimeValue(): float
    {
        $total = 0.0;

        foreach ($this->salesOrders as $order) {
            if ($order->getStatus() === 'completed') {
                $total += (float) $order->getTotalAmount();
            }
        }

        return $total;
    }

    /**
     * Fréquence d'achat moyenne (jours entre les commandes)
     */
    public function getPurchaseFrequency(): ?float
    {
        $completedOrders = array_filter(
            $this->salesOrders->toArray(),
            fn($order) => $order->getStatus() === 'completed'
        );

        if (count($completedOrders) < 2) {
            return null;
        }

        // Trier par date
        usort($completedOrders, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

        $firstDate = reset($completedOrders)->getCreatedAt();
        $lastDate = end($completedOrders)->getCreatedAt();

        $daysBetween = $lastDate->diff($firstDate)->days;

        return $daysBetween / (count($completedOrders) - 1);
    }

    /**
     * Dernière date d'achat
     */
    public function getLastPurchaseDate(): ?\DateTimeInterface
    {
        $lastOrder = null;

        foreach ($this->salesOrders as $order) {
            if ($order->getStatus() === 'completed') {
                if ($lastOrder === null || $order->getCreatedAt() > $lastOrder->getCreatedAt()) {
                    $lastOrder = $order;
                }
            }
        }

        return $lastOrder?->getCreatedAt();
    }

    /**
     * Jours depuis le dernier achat
     */
    public function getDaysSinceLastPurchase(): ?int
    {
        $lastPurchase = $this->getLastPurchaseDate();

        if ($lastPurchase === null) {
            return null;
        }

        return (new \DateTime())->diff($lastPurchase)->days;
    }

    /**
     * Catégorie RFM (Recency, Frequency, Monetary)
     */
    public function getRfmCategory(): string
    {
        $recency = $this->getDaysSinceLastPurchase() ?? 999;
        $frequency = $this->salesOrders->count();
        $monetary = $this->getLifetimeValue();

        // Logique simplifiée de catégorisation
        if ($recency <= 30 && $frequency >= 5 && $monetary >= 1000) {
            return 'Champion';
        } elseif ($recency <= 90 && $frequency >= 3) {
            return 'Loyal';
        } elseif ($recency <= 180) {
            return 'Potential';
        } elseif ($recency > 365) {
            return 'At Risk';
        } else {
            return 'Needs Attention';
        }
    }

    /**
     * Applique la remise client sur un montant
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
     * Vérifie si le client est exonéré de TVA
     */
    public function isTaxExempt(): bool
    {
        return $this->getAttribute('is_tax_exempt', false) === true;
    }

    /**
     * Génère un code client automatique
     */
    public static function generateCode(string $name, string $type = self::TYPE_RETAIL): string
    {
        $prefix = match($type) {
            self::TYPE_RETAIL => 'RTL',
            self::TYPE_WHOLESALE => 'WSL',
            self::TYPE_CORPORATE => 'CORP',
            self::TYPE_ONLINE => 'WEB',
            self::TYPE_GOVERNMENT => 'GOV',
            self::TYPE_NON_PROFIT => 'NPO',
            default => 'CUST'
        };

        // Extraire les premières lettres des mots significatifs
        $words = preg_split('/\s+/', strtoupper($name));
        $acronym = '';

        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, ['ET', 'DE', 'LE', 'LA', 'LES', 'DU', 'DES', 'AND', 'THE'])) {
                $acronym .= $word[0];
            }
        }

        $base = $prefix . '-' . substr($acronym, 0, 3);

        return $base; // Le numéro sera ajouté par le repository
    }

    public function getCustomerTypeLabel(): string
    {
        $labels = [
            self::TYPE_RETAIL => 'Détail',
            self::TYPE_WHOLESALE => 'Gros',
            self::TYPE_CORPORATE => 'Entreprise',
            self::TYPE_ONLINE => 'En ligne',
            self::TYPE_GOVERNMENT => 'Administration',
            self::TYPE_NON_PROFIT => 'Association',
        ];

        return $labels[$this->customerType] ?? $this->customerType;
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

    public function toArray(bool $includeSales = false): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'customer_type' => $this->customerType,
            'customer_type_label' => $this->getCustomerTypeLabel(),
            'contact_person' => $this->contactPerson,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'credit_limit' => $this->creditLimit,
            'available_credit' => $this->getAvailableCredit(),
            'credit_utilization' => $this->getCreditUtilization(),
            'payment_terms' => $this->paymentTerms,
            'payment_terms_label' => $this->getPaymentTermsLabel(),
            'average_payment_days' => $this->getAveragePaymentDays(),
            'tax_id' => $this->taxId,
            'is_active' => $this->isActive,
            'is_tax_exempt' => $this->isTaxExempt(),
            'current_balance' => $this->getCurrentBalance(),
            'is_overdue' => $this->isOverdue(),
            'overdue_amount' => $this->getOverdueAmount(),
            'lifetime_value' => $this->getLifetimeValue(),
            'last_purchase_date' => $this->getLastPurchaseDate()?->format('Y-m-d'),
            'days_since_last_purchase' => $this->getDaysSinceLastPurchase(),
            'purchase_frequency' => $this->getPurchaseFrequency(),
            'rfm_category' => $this->getRfmCategory(),
            'attributes' => $this->attributes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'is_deleted' => $this->isDeleted(),
        ];

        if ($includeSales) {
            $data['sales_orders'] = array_map(
                fn($order) => $order->toArray(),
                $this->salesOrders->slice(0, 10) // 10 dernières commandes
            );

            $data['sales_summary'] = [
                'total_orders' => $this->salesOrders->count(),
                'completed_orders' => count(array_filter(
                    $this->salesOrders->toArray(),
                    fn($order) => $order->getStatus() === 'completed'
                )),
                'pending_orders' => count(array_filter(
                    $this->salesOrders->toArray(),
                    fn($order) => $order->getStatus() === 'pending'
                )),
                'average_order_value' => $this->salesOrders->count() > 0
                    ? $this->getLifetimeValue() / $this->salesOrders->count()
                    : 0,
            ];
        }

        return $data;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->code, $this->name);
    }
}
