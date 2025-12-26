<?php

namespace App\Domain\SalesOrders\Service;

use App\Domain\SalesOrderLines\SalesOrderLinesRepository;
use App\Domain\SalesOrders\SalesOrders;
use App\Domain\SalesOrderLines\SalesOrderLines;
use App\Domain\Customers\Customers;
use App\Domain\Products\Products;
use App\Domain\SalesOrders\SalesOrdersRepository;
use App\Domain\StockMovements\Service\StockMovementsService;
use App\Domain\Warehouses\Warehouses;
use App\Domain\TenantUsers\TenantUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class SalesOrdersService
{
    public function __construct(
        private SalesOrdersRepository $salesOrdersRepository,
        private SalesOrderLinesRepository $salesOrderLinesRepository,
        private StockMovementsService $stockMovementsService,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function createSalesOrder(
        Customers $customer,
        array $linesData,
        ?\DateTimeInterface $orderDate = null,
        ?\DateTimeInterface $dueDate = null,
        ?string $shippingAddress = null,
        ?string $notes = null
    ): SalesOrders {
        $salesOrder = new SalesOrders();
        $salesOrder->setCustomer($customer);

        if ($orderDate) {
            $salesOrder->setOrderDate(\DateTimeImmutable::createFromInterface($orderDate));
        }

        if ($dueDate) {
            $salesOrder->setDueDate($dueDate);
        }

        if ($shippingAddress) {
            $salesOrder->setShippingAddress($shippingAddress);
        }

        if ($notes) {
            $salesOrder->setNotes($notes);
        }

        // Définir l'utilisateur créateur
        if ($this->security->getUser() instanceof TenantUsers) {
            $salesOrder->setCreatedBy($this->security->getUser());
        } else {
            throw new \RuntimeException('Un utilisateur doit être connecté pour créer une commande');
        }

        // Ajouter les lignes
        foreach ($linesData as $lineData) {
            $line = $this->createOrderLine($lineData);
            $salesOrder->addLine($line);
        }

        // Vérifier la limite de crédit
        if (!$salesOrder->checkCreditLimit()) {
            throw new \RuntimeException('La limite de crédit du client est dépassée');
        }

        $this->salesOrdersRepository->save($salesOrder, true);

        return $salesOrder;
    }

    private function createOrderLine(array $data): SalesOrderLines
    {
        $line = new SalesOrderLines();
        $line->setProduct($data['product']);
        $line->setQuantityOrdered($data['quantity']);
        $line->setUnitPrice($data['unitPrice']);

        if (isset($data['taxRate'])) {
            $line->setTaxRate($data['taxRate']);
        }

        if (isset($data['warehouse'])) {
            $line->setWarehouse($data['warehouse']);
        }

        return $line;
    }

    public function updateSalesOrder(SalesOrders $salesOrder, array $data): SalesOrders
    {
        if (!$salesOrder->isDraft()) {
            throw new \RuntimeException('Seules les commandes en brouillon peuvent être modifiées');
        }

        if (isset($data['dueDate'])) {
            $salesOrder->setDueDate($data['dueDate']);
        }

        if (isset($data['shippingAddress'])) {
            $salesOrder->setShippingAddress($data['shippingAddress']);
        }

        if (isset($data['notes'])) {
            $salesOrder->setNotes($data['notes']);
        }

        // Mise à jour des lignes
        if (isset($data['lines'])) {
            $this->updateOrderLines($salesOrder, $data['lines']);
        }

        $salesOrder->updateTotals();

        // Re-vérifier la limite de crédit
        if (!$salesOrder->checkCreditLimit()) {
            throw new \RuntimeException('La limite de crédit du client est dépassée');
        }

        $this->entityManager->flush();

        return $salesOrder;
    }

    private function updateOrderLines(SalesOrders $salesOrder, array $linesData): void
    {
        // Supprimer les lignes existantes
        foreach ($salesOrder->getLines() as $line) {
            $this->entityManager->remove($line);
        }
        $salesOrder->getLines()->clear();

        // Ajouter les nouvelles lignes
        foreach ($linesData as $lineData) {
            $line = $this->createOrderLine($lineData);
            $salesOrder->addLine($line);
        }
    }

    public function confirmOrder(SalesOrders $salesOrder): SalesOrders
    {
        if (!$salesOrder->canBeConfirmed()) {
            throw new \RuntimeException('Cette commande ne peut pas être confirmée');
        }

        $salesOrder->confirm();
        $this->entityManager->flush();

        return $salesOrder;
    }

    public function reserveOrder(SalesOrders $salesOrder): SalesOrders
    {
        if (!$salesOrder->canBeReserved()) {
            throw new \RuntimeException('Cette commande ne peut pas être réservée');
        }

        // Réserver le stock pour chaque ligne
        foreach ($salesOrder->getLines() as $line) {
            if ($line->getWarehouse()) {
                $line->reserveStock();
            }
        }

        $salesOrder->reserve();
        $this->entityManager->flush();

        return $salesOrder;
    }

    public function fulfillOrderLine(
        SalesOrderLines $line,
        string $quantity,
        ?\DateTimeInterface $fulfillDate = null,
        ?string $notes = null
    ): void {
        if (!$line->getSalesOrder()->isFulfillable()) {
            throw new \RuntimeException('Cette commande n\'est pas en état de préparation');
        }

        if (!$line->canFulfillMore()) {
            throw new \RuntimeException('Cette ligne ne peut plus être préparée');
        }

        // Vérifier le stock disponible si nécessaire
        if (!$line->isReserved() && $line->getWarehouse()) {
            $availableStock = $this->getAvailableStockForProduct($line->getProduct(), $line->getWarehouse());
            if (bccomp($availableStock, $quantity, 6) < 0) {
                throw new \RuntimeException(sprintf(
                    'Stock insuffisant. Disponible: %s, Demandé: %s',
                    $availableStock,
                    $quantity
                ));
            }
        }

        // Préparer la quantité
        $line->fulfillQuantity($quantity, $fulfillDate);

        // Si un entrepôt est spécifié, créer un mouvement de stock
        if ($line->getWarehouse()) {
            $this->stockMovementsService->createOutgoingMovement(
                $line->getProduct(),
                $line->getWarehouse(),
                $quantity,
                null, // Utiliser le type par défaut SALES_SHIPMENT
                $line->getUnitPrice(),
                $line->getSalesOrder()->getUuid(),
                'sales_order',
                $notes ?? sprintf('Préparation commande %s', $line->getSalesOrder()->getSoNumber())
            );
        }

        $this->entityManager->flush();
    }

    private function getAvailableStockForProduct(Products $product, Warehouses $warehouse): string
    {
        // TODO: Implémenter la récupération du stock disponible
        // Utiliser StockLocationsRepository::getAvailableStock()
        return '999999'; // Temporaire
    }

    public function fulfillFullOrder(
        SalesOrders $salesOrder,
        ?\DateTimeInterface $fulfillDate = null,
        ?string $notes = null
    ): void {
        if (!$salesOrder->isFulfillable()) {
            throw new \RuntimeException('Cette commande n\'est pas en état de préparation');
        }

        foreach ($salesOrder->getLines() as $line) {
            if ($line->canFulfillMore()) {
                $quantityToFulfill = $line->getRemainingToFulfill();
                $this->fulfillOrderLine($line, $quantityToFulfill, $fulfillDate, $notes);
            }
        }
    }

    public function shipOrder(SalesOrders $salesOrder, ?string $trackingNumber = null, ?string $notes = null): SalesOrders
    {
        if (!$salesOrder->canBeShipped()) {
            throw new \RuntimeException('Cette commande ne peut pas être expédiée');
        }

        $salesOrder->setStatus('shipped');
        $salesOrder->setUpdatedAt(new \DateTime());

        if ($notes) {
            $salesOrder->setNotes($salesOrder->getNotes() . "\n\nExpédiée le " . date('d/m/Y'));
            if ($trackingNumber) {
                $salesOrder->setNotes($salesOrder->getNotes() . " - Numéro de suivi: " . $trackingNumber);
            }
        }

        $this->entityManager->flush();

        return $salesOrder;
    }

    public function markAsDelivered(SalesOrders $salesOrder, ?string $notes = null): SalesOrders
    {
        if (!$salesOrder->isShipped() && !$salesOrder->isFulfilled()) {
            throw new \RuntimeException('Seules les commandes expédiées ou préparées peuvent être marquées comme livrées');
        }

        $salesOrder->setStatus('delivered');
        $salesOrder->setUpdatedAt(new \DateTime());

        if ($notes) {
            $salesOrder->setNotes($salesOrder->getNotes() . "\n\nLivrée le " . date('d/m/Y'));
        }

        $this->entityManager->flush();

        return $salesOrder;
    }

    public function cancelOrder(SalesOrders $salesOrder, string $reason = null): SalesOrders
    {
        $salesOrder->cancel($reason);

        // Libérer les réservations de stock
        foreach ($salesOrder->getLines() as $line) {
            if ($line->isReserved()) {
                $line->releaseReservation();
            }
        }

        $this->entityManager->flush();

        return $salesOrder;
    }

    public function closeOrder(SalesOrders $salesOrder): SalesOrders
    {
        $salesOrder->close();
        $this->entityManager->flush();

        return $salesOrder;
    }

    public function deleteSalesOrder(SalesOrders $salesOrder): void
    {
        if (!$salesOrder->isDeletable()) {
            throw new \RuntimeException('Cette commande ne peut pas être supprimée');
        }

        $this->salesOrdersRepository->softDelete($salesOrder);
    }

    public function getSalesOrderSummary(SalesOrders $salesOrder): array
    {
        $lineStats = $this->salesOrderLinesRepository->getLineStatistics($salesOrder);

        return [
            'salesOrder' => $salesOrder->toArray(),
            'statistics' => [
                'totalLines' => $lineStats['totalLines'] ?? 0,
                'totalOrdered' => $lineStats['totalOrdered'] ?? '0',
                'totalFulfilled' => $lineStats['totalFulfilled'] ?? '0',
                'notFulfilledCount' => $lineStats['notFulfilledCount'] ?? 0,
                'partiallyFulfilledCount' => $lineStats['partiallyFulfilledCount'] ?? 0,
                'fullyFulfilledCount' => $lineStats['fullyFulfilledCount'] ?? 0,
                'reservedCount' => $lineStats['reservedCount'] ?? 0,
            ],
        ];
    }

    public function getCustomerSalesHistory(Customers $customer, array $filters = []): array
    {
        return $this->salesOrdersRepository->findByCustomer($customer, $filters);
    }

    public function getProductSalesHistory(Products $product, int $limit = 10): array
    {
        return $this->salesOrderLinesRepository->getProductSalesHistory($product, $limit);
    }

    public function getOpenSalesOrders(): array
    {
        return $this->salesOrdersRepository->findFulfillableOrders();
    }

    public function getOverdueSalesOrders(): array
    {
        return $this->salesOrdersRepository->findOverdueOrders();
    }

    public function getSalesOrderStatistics(\DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        return $this->salesOrdersRepository->getStatistics($startDate, $endDate);
    }

    public function getBestSellingProducts(int $limit = 10, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        return $this->salesOrderLinesRepository->getBestSellingProducts($limit, $startDate, $endDate);
    }

    public function getTopCustomers(int $limit = 10, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        return $this->salesOrdersRepository->getTopCustomers($limit, $startDate, $endDate);
    }

    public function validateSalesOrder(SalesOrders $salesOrder): array
    {
        $errors = [];

        if ($salesOrder->getLines()->count() === 0) {
            $errors[] = 'La commande doit contenir au moins une ligne';
        }

        foreach ($salesOrder->getLines() as $line) {
            if (bccomp($line->getQuantityOrdered(), '0', 6) <= 0) {
                $errors[] = sprintf('La quantité commandée pour le produit %s doit être positive',
                    $line->getProduct()->getName());
            }

            if (bccomp($line->getUnitPrice(), '0', 4) < 0) {
                $errors[] = sprintf('Le prix unitaire pour le produit %s ne peut pas être négatif',
                    $line->getProduct()->getName());
            }
        }

        if (!$salesOrder->checkCreditLimit()) {
            $errors[] = 'La limite de crédit du client est dépassée';
        }

        return $errors;
    }
}
