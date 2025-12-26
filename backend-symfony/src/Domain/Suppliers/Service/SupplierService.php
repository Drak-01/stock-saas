<?php

namespace App\Domain\Suppliers\Service;

use App\Domain\Products\ProductsRepository;
use App\Domain\Suppliers\Suppliers;
use App\Domain\Products\Products;
use App\Domain\Suppliers\SuppliersRepository;
use Doctrine\ORM\EntityManagerInterface;

class SupplierService
{
    private SuppliersRepository $suppliersRepository;
    private ProductsRepository $productsRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        SuppliersRepository $suppliersRepository,
        ProductsRepository $productsRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->suppliersRepository = $suppliersRepository;
        $this->productsRepository = $productsRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Crée un nouveau fournisseur avec validation
     */
    public function createSupplier(array $data): Suppliers
    {
        $supplier = new Suppliers();

        // Générer un code automatique si non fourni
        if (!isset($data['code']) || empty($data['code'])) {
            $category = $data['attributes']['category'] ?? Suppliers::CATEGORY_RAW_MATERIAL;
            $data['code'] = $this->generateSupplierCode($data['name'], $category);
        }

        $supplier->setCode($data['code']);
        $supplier->setName($data['name']);

        if (isset($data['contact_person'])) {
            $supplier->setContactPerson($data['contact_person']);
        }

        if (isset($data['email'])) {
            $supplier->setEmail($data['email']);
        }

        if (isset($data['phone'])) {
            $supplier->setPhone($data['phone']);
        }

        if (isset($data['address'])) {
            $supplier->setAddress($data['address']);
        }

        if (isset($data['payment_terms'])) {
            $supplier->setPaymentTerms($data['payment_terms']);
        }

        if (isset($data['tax_id'])) {
            $supplier->setTaxId($data['tax_id']);
        }

        // Attributs
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $supplier->setAttributes($data['attributes']);
        }

        // Catégorie par défaut si non spécifiée
        if (!isset($data['attributes']['category'])) {
            $supplier->setCategory(Suppliers::CATEGORY_RAW_MATERIAL);
        }

        $this->entityManager->persist($supplier);
        $this->entityManager->flush();

        return $supplier;
    }

    /**
     * Génère un code fournisseur unique
     */
    private function generateSupplierCode(string $name, string $category): string
    {
        $baseCode = Suppliers::generateCode($name, $category);
        return $this->suppliersRepository->generateNextCode(
            substr($baseCode, 0, strpos($baseCode, '-') ?: strlen($baseCode))
        );
    }

    /**
     * Évalue la performance d'un fournisseur
     */
    public function evaluateSupplierPerformance(Suppliers $supplier): array
    {
        $performance = [
            'supplier_id' => $supplier->getId(),
            'supplier_name' => $supplier->getName(),
            'overall_score' => $supplier->calculateOverallRating(),
            'metrics' => [],
            'recommendations' => [],
        ];

        // Métriques de qualité
        $qualityRate = $supplier->getQualityAcceptanceRate();
        $performance['metrics']['quality'] = [
            'rate' => $qualityRate,
            'status' => $qualityRate >= 95 ? 'excellent' : ($qualityRate >= 90 ? 'good' : ($qualityRate >= 85 ? 'fair' : 'poor')),
        ];

        if ($qualityRate < 90) {
            $performance['recommendations'][] = 'Implement stricter quality inspections';
        }

        // Métriques de livraison
        $onTimeRate = $supplier->getOnTimeDeliveryRate();
        $performance['metrics']['delivery'] = [
            'rate' => $onTimeRate,
            'status' => $onTimeRate >= 95 ? 'excellent' : ($onTimeRate >= 90 ? 'good' : ($onTimeRate >= 85 ? 'fair' : 'poor')),
        ];

        if ($onTimeRate < 90) {
            $performance['recommendations'][] = 'Negotiate better lead times or penalties for late delivery';
        }

        // Métriques financières
        $cashFlowImpact = $supplier->getCashFlowImpact();
        $performance['metrics']['financial'] = [
            'impact' => $cashFlowImpact,
            'status' => str_contains($cashFlowImpact, 'favorable') ? 'excellent' :
                (str_contains($cashFlowImpact, 'Standard') ? 'good' : 'poor'),
        ];

        if (str_contains($cashFlowImpact, 'impactée') || str_contains($cashFlowImpact, 'risque')) {
            $performance['recommendations'][] = 'Review payment terms to improve cash flow';
        }

        // Risque
        $riskLevel = $supplier->getRiskLevel();
        $performance['metrics']['risk'] = [
            'level' => $riskLevel,
            'status' => $riskLevel === 'low' ? 'excellent' : ($riskLevel === 'medium' ? 'good' : 'poor'),
        ];

        if ($riskLevel === 'high') {
            $performance['recommendations'][] = 'Find alternative suppliers to mitigate risk';
        }

        // Contrat
        if ($supplier->isContractExpired()) {
            $performance['recommendations'][] = 'Renegotiate or renew contract immediately';
        } elseif (($days = $supplier->getDaysUntilContractExpiry()) !== null && $days < 60) {
            $performance['recommendations'][] = "Contract expires in {$days} days - start renewal process";
        }

        // Classification finale
        $overallScore = $performance['overall_score'];
        if ($overallScore >= 4.0) {
            $performance['classification'] = 'Strategic Partner';
            $performance['action'] = 'Increase business volume, negotiate long-term contracts';
        } elseif ($overallScore >= 3.5) {
            $performance['classification'] = 'Preferred Supplier';
            $performance['action'] = 'Maintain relationship, consider volume discounts';
        } elseif ($overallScore >= 3.0) {
            $performance['classification'] = 'Standard Supplier';
            $performance['action'] = 'Monitor performance, have backup suppliers';
        } else {
            $performance['classification'] = 'At Risk Supplier';
            $performance['action'] = 'Find alternatives, reduce dependency';
        }

        return $performance;
    }

    /**
     * Trouve les meilleurs fournisseurs pour un produit
     */
    public function findBestSuppliersForProduct(Products $product, array $criteria = []): array
    {
        $suppliers = $this->suppliersRepository->findActiveSuppliers();
        $rankedSuppliers = [];

        foreach ($suppliers as $supplier) {
            $score = $this->calculateSupplierProductScore($supplier, $product, $criteria);

            if ($score > 0) {
                $rankedSuppliers[] = [
                    'supplier' => $supplier,
                    'score' => $score,
                    'details' => $this->getSupplierProductDetails($supplier, $product),
                ];
            }
        }

        // Trier par score décroissant
        usort($rankedSuppliers, fn($a, $b) => $b['score'] <=> $a['score']);

        return $rankedSuppliers;
    }

    /**
     * Calcule un score pour un fournisseur sur un produit spécifique
     */
    private function calculateSupplierProductScore(Suppliers $supplier, Products $product, array $criteria): float
    {
        $score = 0;

        // 1. Fournisseur déjà approuvé pour ce produit
        $productSuppliers = $product->getAttribute('supplier_id');
        if ($productSuppliers && in_array($supplier->getId(), (array) $productSuppliers)) {
            $score += 30;
        }

        // 2. Performance globale du fournisseur
        $overallRating = $supplier->calculateOverallRating();
        $score += $overallRating * 10; // 10-50 points

        // 3. Délai de livraison
        $leadTime = $supplier->getLeadTimeDays();
        $desiredLeadTime = $criteria['lead_time'] ?? 7;

        if ($leadTime <= $desiredLeadTime) {
            $score += 20;
        } elseif ($leadTime <= $desiredLeadTime * 1.5) {
            $score += 10;
        }

        // 4. Termes de paiement
        $paymentTerms = $supplier->getPaymentTerms();
        if (in_array($paymentTerms, [Suppliers::PAYMENT_NET_60, Suppliers::PAYMENT_NET_90])) {
            $score += 15; // Termes favorables
        } elseif ($paymentTerms === Suppliers::PAYMENT_CASH || $paymentTerms === Suppliers::PAYMENT_100_ADVANCE) {
            $score -= 10; // Termes défavorables
        }

        // 5. Fournisseur préféré
        if ($supplier->isPreferred()) {
            $score += 20;
        }

        // 6. Risque
        $riskLevel = $supplier->getRiskLevel();
        if ($riskLevel === 'low') {
            $score += 15;
        } elseif ($riskLevel === 'high') {
            $score -= 20;
        }

        return max(0, $score);
    }

    /**
     * Analyse de dépendance aux fournisseurs
     */
    public function analyzeSupplierDependency(): array
    {
        $suppliers = $this->suppliersRepository->findActiveSuppliers();
        $analysis = [];
        $totalSpend = 0;

        foreach ($suppliers as $supplier) {
            $spend = $supplier->getTotalPurchases();
            $totalSpend += $spend;

            $analysis[] = [
                'supplier' => $supplier,
                'spend' => $spend,
                'percentage' => 0,
                'risk_level' => $supplier->getRiskLevel(),
                'is_single_source' => $this->isSingleSourceSupplier($supplier),
            ];
        }

        // Calculer les pourcentages
        foreach ($analysis as &$item) {
            $item['percentage'] = $totalSpend > 0 ? ($item['spend'] / $totalSpend) * 100 : 0;
        }

        // Trier par pourcentage décroissant
        usort($analysis, fn($a, $b) => $b['percentage'] <=> $a['percentage']);

        // Identifier les risques
        $risks = [];
        foreach ($analysis as $item) {
            if ($item['percentage'] > 20 && $item['risk_level'] === 'high') {
                $risks[] = sprintf(
                    'High dependency (%s%%) on high-risk supplier: %s',
                    round($item['percentage'], 1),
                    $item['supplier']->getName()
                );
            }

            if ($item['is_single_source'] && $item['percentage'] > 10) {
                $risks[] = sprintf(
                    'Single source dependency (%s%%) on supplier: %s',
                    round($item['percentage'], 1),
                    $item['supplier']->getName()
                );
            }
        }

        return [
            'total_suppliers' => count($suppliers),
            'total_spend' => $totalSpend,
            'analysis' => $analysis,
            'risks' => $risks,
            'recommendations' => $this->generateDependencyRecommendations($analysis),
        ];
    }

    /**
     * Vérifie si un fournisseur est la seule source pour ses produits
     */
    private function isSingleSourceSupplier(Suppliers $supplier): bool
    {
        $products = $supplier->getProducts();

        foreach ($products as $product) {
            $supplierIds = $product->getAttribute('supplier_id', []);
            if (is_string($supplierIds)) {
                $supplierIds = [$supplierIds];
            }

            // Si le produit n'a qu'un seul fournisseur et c'est celui-ci
            if (count($supplierIds) === 1 && $supplierIds[0] === $supplier->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recommandations pour réduire la dépendance
     */
    private function generateDependencyRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Règle de Pareto : si un fournisseur représente >20% des achats
        $highDependency = array_filter($analysis, fn($item) => $item['percentage'] > 20);

        foreach ($highDependency as $item) {
            $recommendations[] = sprintf(
                'Reduce dependency on %s (%.1f%% of spend). Find alternative suppliers.',
                $item['supplier']->getName(),
                $item['percentage']
            );
        }

        // Fournisseurs uniques
        $singleSource = array_filter($analysis, fn($item) => $item['is_single_source']);

        foreach ($singleSource as $item) {
            if ($item['percentage'] > 5) {
                $recommendations[] = sprintf(
                    'Develop second source for products supplied by %s',
                    $item['supplier']->getName()
                );
            }
        }

        // Fournisseurs à haut risque
        $highRisk = array_filter($analysis, fn($item) => $item['risk_level'] === 'high' && $item['percentage'] > 10);

        foreach ($highRisk as $item) {
            $recommendations[] = sprintf(
                'Mitigate risk from high-risk supplier %s (%.1f%% of spend)',
                $item['supplier']->getName(),
                $item['percentage']
            );
        }

        return array_unique($recommendations);
    }

    /**
     * Gestion des renouvellements de contrat
     */
    public function getContractRenewalSchedule(): array
    {
        $suppliers = $this->suppliersRepository->findActiveSuppliers();
        $schedule = [];

        foreach ($suppliers as $supplier) {
            $expiryDate = $supplier->getAttribute('contract_expiry');

            if ($expiryDate) {
                $expiry = \DateTime::createFromFormat('Y-m-d', $expiryDate);
                $daysUntil = $supplier->getDaysUntilContractExpiry();

                if ($daysUntil !== null) {
                    $status = match(true) {
                        $daysUntil < 0 => 'expired',
                        $daysUntil <= 30 => 'urgent',
                        $daysUntil <= 90 => 'upcoming',
                        $daysUntil <= 180 => 'future',
                        default => 'distant'
                    };

                    $schedule[] = [
                        'supplier' => $supplier,
                        'expiry_date' => $expiryDate,
                        'days_until' => $daysUntil,
                        'status' => $status,
                        'action_required' => $status === 'expired' || $status === 'urgent',
                    ];
                }
            }
        }

        // Trier par date d'expiration
        usort($schedule, fn($a, $b) => $a['days_until'] <=> $b['days_until']);

        return $schedule;
    }

    /**
     * Trouve des fournisseurs alternatifs
     */
    public function findAlternativeSuppliers(Suppliers $primarySupplier, array $criteria = []): array
    {
        $category = $primarySupplier->getCategory();
        $alternatives = $this->suppliersRepository->findByCategory($category);

        // Exclure le fournisseur primaire
        $alternatives = array_filter($alternatives, fn($s) => $s->getId() !== $primarySupplier->getId());

        // Trier par performance
        $ranked = [];
        foreach ($alternatives as $supplier) {
            $score = $this->calculateAlternativeScore($supplier, $primarySupplier, $criteria);
            $ranked[] = [
                'supplier' => $supplier,
                'score' => $score,
                'comparison' => $this->compareSuppliers($supplier, $primarySupplier),
            ];
        }

        usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);

        return $ranked;
    }

    /**
     * Calcule un score pour un fournisseur alternatif
     */
    private function calculateAlternativeScore(Suppliers $alternative, Suppliers $primary, array $criteria): float
    {
        $score = 0;

        // 1. Performance meilleure que le fournisseur primaire
        $altRating = $alternative->calculateOverallRating();
        $primaryRating = $primary->calculateOverallRating();

        if ($altRating > $primaryRating) {
            $score += ($altRating - $primaryRating) * 20;
        }

        // 2. Termes de paiement plus favorables
        $altTerms = $alternative->getPaymentTerms();
        $primaryTerms = $primary->getPaymentTerms();

        $termsRanking = [
            Suppliers::PAYMENT_NET_90 => 5,
            Suppliers::PAYMENT_NET_60 => 4,
            Suppliers::PAYMENT_NET_30 => 3,
            Suppliers::PAYMENT_NET_7 => 2,
            Suppliers::PAYMENT_CASH => 1,
            Suppliers::PAYMENT_50_ADVANCE => 0,
            Suppliers::PAYMENT_100_ADVANCE => -1,
        ];

        $altRank = $termsRanking[$altTerms] ?? 2;
        $primaryRank = $termsRanking[$primaryTerms] ?? 2;

        if ($altRank > $primaryRank) {
            $score += ($altRank - $primaryRank) * 10;
        }

        // 3. Délai de livraison
        $altLeadTime = $alternative->getLeadTimeDays();
        $primaryLeadTime = $primary->getLeadTimeDays();

        if ($altLeadTime < $primaryLeadTime) {
            $score += (($primaryLeadTime - $altLeadTime) / $primaryLeadTime) * 30;
        }

        // 4. Niveau de risque inférieur
        $riskRanking = ['low' => 3, 'medium' => 2, 'high' => 1];
        $altRisk = $riskRanking[$alternative->getRiskLevel()] ?? 2;
        $primaryRisk = $riskRanking[$primary->getRiskLevel()] ?? 2;

        if ($altRisk > $primaryRisk) {
            $score += 15;
        }

        return $score;
    }

    /**
     * Met à jour les notations des fournisseurs basé sur les performances récentes
     */
    public function updateSupplierRatings(): void
    {
        $suppliers = $this->suppliersRepository->findActiveSuppliers();

        foreach ($suppliers as $supplier) {
            // Recalculer les notations basées sur les commandes récentes (90 jours)
            $recentOrders = array_filter(
                $supplier->getPurchaseOrders()->toArray(),
                fn($order) => $order->getOrderDate() >= new \DateTime('-90 days')
            );

            if (!empty($recentOrders)) {
                $this->updateRatingsFromRecentOrders($supplier, $recentOrders);
            }

            $this->entityManager->persist($supplier);
        }

        $this->entityManager->flush();
    }

    private function updateRatingsFromRecentOrders(Suppliers $supplier, array $recentOrders): void
    {
        // Implémentation simplifiée
        // Dans la réalité, on analyserait chaque commande pour mettre à jour les notations
        $supplier->calculateOverallRating();
    }
}
