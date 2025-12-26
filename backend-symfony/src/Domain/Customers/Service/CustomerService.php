<?php

namespace App\Domain\Customers\Service;

use App\Domain\Customers\Customers;
use App\Domain\Customers\CustomersRepository;
use Doctrine\ORM\EntityManagerInterface;

class CustomerService
{
    private CustomersRepository $customersRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        CustomersRepository $customersRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->customersRepository = $customersRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Crée un nouveau client avec validation
     */
    public function createCustomer(array $data): Customers
    {
        $customer = new Customers();

        // Générer un code automatique si non fourni
        if (!isset($data['code']) || empty($data['code'])) {
            $data['code'] = $this->generateCustomerCode($data['name'], $data['customer_type'] ?? Customers::TYPE_RETAIL);
        }

        $customer->setCode($data['code']);
        $customer->setName($data['name']);
        $customer->setCustomerType($data['customer_type'] ?? Customers::TYPE_RETAIL);

        if (isset($data['contact_person'])) {
            $customer->setContactPerson($data['contact_person']);
        }

        if (isset($data['email'])) {
            $customer->setEmail($data['email']);
        }

        if (isset($data['phone'])) {
            $customer->setPhone($data['phone']);
        }

        if (isset($data['address'])) {
            $customer->setAddress($data['address']);
        }

        if (isset($data['credit_limit'])) {
            $customer->setCreditLimit($data['credit_limit']);
        }

        if (isset($data['payment_terms'])) {
            $customer->setPaymentTerms($data['payment_terms']);
        }

        if (isset($data['tax_id'])) {
            $customer->setTaxId($data['tax_id']);
        }

        // Attributs
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $customer->setAttributes($data['attributes']);
        }

        // Appliquer les remises par défaut selon le type
        $customer->setCustomerType($customer->getCustomerType());

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    /**
     * Génère un code client unique
     */
    private function generateCustomerCode(string $name, string $type): string
    {
        $baseCode = Customers::generateCode($name, $type);
        return $this->customersRepository->generateNextCode(
            substr($baseCode, 0, strpos($baseCode, '-') ?: strlen($baseCode))
        );
    }

    /**
     * Vérifie si un client peut passer une commande
     */
    public function canPlaceOrder(Customers $customer, float $orderAmount): array
    {
        $result = [
            'can_place_order' => true,
            'reasons' => [],
            'available_credit' => null,
            'suggested_action' => null,
        ];

        // 1. Vérifier si le client est actif
        if (!$customer->isActive()) {
            $result['can_place_order'] = false;
            $result['reasons'][] = 'Customer is inactive';
            $result['suggested_action'] = 'Activate customer account';
            return $result;
        }

        // 2. Vérifier les retards de paiement
        if ($customer->isOverdue()) {
            $overdueAmount = $customer->getOverdueAmount();
            $result['can_place_order'] = false;
            $result['reasons'][] = sprintf('Customer has overdue payments: %s', number_format($overdueAmount, 2));
            $result['suggested_action'] = 'Request payment for overdue invoices';
            return $result;
        }

        // 3. Vérifier la limite de crédit
        $availableCredit = $customer->getAvailableCredit();
        $result['available_credit'] = $availableCredit;

        if ($availableCredit !== null && $orderAmount > $availableCredit) {
            $result['can_place_order'] = false;
            $result['reasons'][] = sprintf(
                'Order amount (%s) exceeds available credit (%s)',
                number_format($orderAmount, 2),
                number_format($availableCredit, 2)
            );
            $result['suggested_action'] = 'Increase credit limit or request advance payment';
        }

        // 4. Vérifier l'inactivité prolongée
        $daysSinceLastPurchase = $customer->getDaysSinceLastPurchase();
        if ($daysSinceLastPurchase !== null && $daysSinceLastPurchase > 365) {
            $result['reasons'][] = sprintf('Customer inactive for %s days', $daysSinceLastPurchase);
            $result['suggested_action'] = 'Contact customer to confirm interest';
        }

        return $result;
    }

    /**
     * Met à jour la notation de crédit d'un client
     */
    public function updateCreditRating(Customers $customer): void
    {
        $rating = $this->calculateCreditRating($customer);
        $customer->setAttribute('credit_rating', $rating);

        // Ajuster automatiquement la limite de crédit si nécessaire
        $this->adjustCreditLimit($customer, $rating);

        $this->entityManager->flush();
    }

    /**
     * Calcule la notation de crédit
     */
    private function calculateCreditRating(Customers $customer): string
    {
        $score = 100;

        // Facteurs positifs
        $lifetimeValue = $customer->getLifetimeValue();
        if ($lifetimeValue > 10000) $score += 20;
        elseif ($lifetimeValue > 5000) $score += 10;

        $purchaseFrequency = $customer->getPurchaseFrequency();
        if ($purchaseFrequency !== null && $purchaseFrequency < 30) {
            $score += 15; // Achats fréquents
        }

        // Facteurs négatifs
        if ($customer->isOverdue()) {
            $score -= 30;
        }

        $utilization = $customer->getCreditUtilization();
        if ($utilization !== null) {
            if ($utilization > 90) $score -= 25;
            elseif ($utilization > 75) $score -= 15;
            elseif ($utilization > 50) $score -= 5;
        }

        $daysSinceLastPurchase = $customer->getDaysSinceLastPurchase();
        if ($daysSinceLastPurchase !== null && $daysSinceLastPurchase > 180) {
            $score -= 20; // Inactif depuis longtemps
        }

        // Catégorisation
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        if ($score >= 40) return 'poor';
        return 'bad';
    }

    /**
     * Ajuste automatiquement la limite de crédit
     */
    private function adjustCreditLimit(Customers $customer, string $rating): void
    {
        $currentLimit = $customer->getCreditLimit();
        if ($currentLimit === null) {
            return; // Pas de limite définie
        }

        $current = (float) $currentLimit;
        $lifetimeValue = $customer->getLifetimeValue();

        // Règles d'ajustement
        switch ($rating) {
            case 'excellent':
                if ($current < $lifetimeValue * 0.1) {
                    $newLimit = $lifetimeValue * 0.1;
                    $customer->setCreditLimit((string) round($newLimit, 2));
                }
                break;

            case 'good':
                if ($current < $lifetimeValue * 0.05) {
                    $newLimit = $lifetimeValue * 0.05;
                    $customer->setCreditLimit((string) round($newLimit, 2));
                }
                break;

            case 'poor':
            case 'bad':
                if ($current > $lifetimeValue * 0.02) {
                    $newLimit = $lifetimeValue * 0.02;
                    $customer->setCreditLimit((string) round($newLimit, 2));
                }
                break;
        }
    }

    /**
     * Trouve les clients à risque (churn prediction)
     */
    public function findAtRiskCustomers(): array
    {
        $customers = $this->customersRepository->findActiveCustomers();
        $atRisk = [];

        foreach ($customers as $customer) {
            $riskScore = $this->calculateChurnRisk($customer);

            if ($riskScore >= 70) { // Haut risque
                $atRisk[] = [
                    'customer' => $customer,
                    'risk_score' => $riskScore,
                    'reasons' => $this->getChurnRiskReasons($customer),
                    'suggested_actions' => $this->getRetentionActions($customer),
                ];
            }
        }

        // Trier par score de risque
        usort($atRisk, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        return $atRisk;
    }

    /**
     * Calcule le risque de perte du client
     */
    private function calculateChurnRisk(Customers $customer): float
    {
        $risk = 0;

        // 1. Inactivité
        $daysSinceLastPurchase = $customer->getDaysSinceLastPurchase();
        if ($daysSinceLastPurchase !== null) {
            if ($daysSinceLastPurchase > 365) $risk += 40;
            elseif ($daysSinceLastPurchase > 180) $risk += 30;
            elseif ($daysSinceLastPurchase > 90) $risk += 20;
        }

        // 2. Fréquence d'achat en baisse
        $frequency = $customer->getPurchaseFrequency();
        if ($frequency !== null && $frequency > 90) {
            $risk += 25; // Achats espacés
        }

        // 3. Problèmes de paiement
        if ($customer->isOverdue()) {
            $risk += 30;
        }

        // 4. Valeur décroissante
        $recentOrders = array_filter(
            $customer->getSalesOrders()->toArray(),
            fn($order) => $order->getCreatedAt() >= new \DateTime('-90 days')
        );

        $recentValue = array_sum(array_map(
            fn($order) => (float) $order->getTotalAmount(),
            $recentOrders
        ));

        $previousValue = array_sum(array_map(
            fn($order) => (float) $order->getTotalAmount(),
            array_filter(
                $customer->getSalesOrders()->toArray(),
                fn($order) => $order->getCreatedAt() >= new \DateTime('-180 days')
                    && $order->getCreatedAt() < new \DateTime('-90 days')
            )
        ));

        if ($previousValue > 0 && $recentValue < ($previousValue * 0.5)) {
            $risk += 25; // Baisse de 50% du chiffre d'affaires
        }

        return min($risk, 100);
    }

    /**
     * Actions de rétention suggérées
     */
    private function getRetentionActions(Customers $customer): array
    {
        $actions = [];

        if ($customer->getDaysSinceLastPurchase() > 90) {
            $actions[] = 'Send re-engagement email with special offer';
        }

        if ($customer->isOverdue()) {
            $actions[] = 'Arrange payment plan for overdue invoices';
        }

        if ($customer->getPurchaseFrequency() > 60) {
            $actions[] = 'Schedule sales call to understand changing needs';
        }

        $rfm = $customer->getRfmCategory();
        if ($rfm === 'At Risk') {
            $actions[] = 'Assign to dedicated account manager';
            $actions[] = 'Offer loyalty discount on next order';
        }

        return $actions;
    }

    /**
     * Analyse ABC des clients (Pareto)
     */
    public function performABCAnalysis(): array
    {
        $customers = $this->customersRepository->findActiveCustomers();
        $totalRevenue = 0;

        // Calculer le chiffre d'affaires par client
        $customerRevenues = [];
        foreach ($customers as $customer) {
            $revenue = $customer->getLifetimeValue();
            $totalRevenue += $revenue;
            $customerRevenues[] = [
                'customer' => $customer,
                'revenue' => $revenue,
                'percentage' => 0,
            ];
        }

        // Trier par CA décroissant
        usort($customerRevenues, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

        // Calculer les pourcentages cumulés
        $cumulativePercentage = 0;
        foreach ($customerRevenues as &$data) {
            $percentage = $totalRevenue > 0 ? ($data['revenue'] / $totalRevenue) * 100 : 0;
            $data['percentage'] = $percentage;
            $cumulativePercentage += $percentage;
            $data['cumulative_percentage'] = $cumulativePercentage;

            // Classification ABC
            if ($cumulativePercentage <= 80) {
                $data['category'] = 'A';
            } elseif ($cumulativePercentage <= 95) {
                $data['category'] = 'B';
            } else {
                $data['category'] = 'C';
            }
        }

        return [
            'total_customers' => count($customers),
            'total_revenue' => $totalRevenue,
            'analysis' => $customerRevenues,
            'summary' => [
                'A_customers' => count(array_filter($customerRevenues, fn($c) => $c['category'] === 'A')),
                'B_customers' => count(array_filter($customerRevenues, fn($c) => $c['category'] === 'B')),
                'C_customers' => count(array_filter($customerRevenues, fn($c) => $c['category'] === 'C')),
            ],
        ];
    }

    /**
     * Calcule les KPI clients
     */
    public function calculateCustomerKPIs(): array
    {
        $customers = $this->customersRepository->findActiveCustomers();
        $totalCustomers = count($customers);

        if ($totalCustomers === 0) {
            return [];
        }

        $totalRevenue = 0;
        $totalOrders = 0;
        $overdueCustomers = 0;
        $inactiveCustomers = 0;

        foreach ($customers as $customer) {
            $totalRevenue += $customer->getLifetimeValue();
            $totalOrders += $customer->getSalesOrders()->count();

            if ($customer->isOverdue()) {
                $overdueCustomers++;
            }

            if ($customer->getDaysSinceLastPurchase() > 365) {
                $inactiveCustomers++;
            }
        }

        return [
            'total_customers' => $totalCustomers,
            'total_revenue' => $totalRevenue,
            'average_revenue_per_customer' => $totalRevenue / $totalCustomers,
            'average_orders_per_customer' => $totalOrders / $totalCustomers,
            'overdue_percentage' => ($overdueCustomers / $totalCustomers) * 100,
            'inactive_percentage' => ($inactiveCustomers / $totalCustomers) * 100,
            'customer_acquisition_cost' => null, // À calculer séparément
            'customer_lifetime_value' => $totalRevenue / $totalCustomers,
            'churn_rate' => $this->calculateChurnRate(),
        ];
    }

    /**
     * Taux de désabonnement
     */
    private function calculateChurnRate(): float
    {
        $oneYearAgo = new \DateTime('-1 year');

        $activeYearAgo = $this->customersRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.createdAt <= :date')
            ->setParameter('date', $oneYearAgo)
            ->getQuery()
            ->getSingleScalarResult();

        $lostCustomers = $this->customersRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.isActive = false')
            ->andWhere('c.updatedAt >= :date')
            ->setParameter('date', $oneYearAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return $activeYearAgo > 0 ? ($lostCustomers / $activeYearAgo) * 100 : 0;
    }
}
