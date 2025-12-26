<?php

namespace App\Domain\PurchaseOrders\Service;

use App\Domain\PurchaseOrders\PurchaseOrders;
use App\Domain\PurchaseOrderLines\PurchaseOrderLines;
use App\Domain\PurchaseOrders\PurchaseOrdersRepository;
use App\Domain\StockMovements\Service\StockMovementsService;
use App\Domain\Suppliers\Suppliers;
use App\Domain\Products\Products;
use App\Domain\TenantUsers\TenantUsers;
use App\Repository\PurchaseOrderLines\PurchaseOrderLinesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PurchaseOrdersService
{
    public function __construct(
        private PurchaseOrdersRepository $purchaseOrdersRepository,
        private PurchaseOrderLinesRepository $purchaseOrderLinesRepository,
        private StockMovementsService $stockMovementsService,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function createPurchaseOrder(
        Suppliers $supplier,
        array $linesData,
        ?\DateTimeInterface $orderDate = null,
        ?\DateTimeInterface $expectedDeliveryDate = null,
        ?string $notes = null
    ): PurchaseOrders {
        $purchaseOrder = new PurchaseOrders();
        $purchaseOrder->setSupplier($supplier);

        if ($orderDate) {
            $purchaseOrder->setOrderDate(\DateTimeImmutable::createFromInterface($orderDate));
        }

        if ($expectedDeliveryDate) {
            $purchaseOrder->setExpectedDeliveryDate($expectedDeliveryDate);
        }

        if ($notes) {
            $purchaseOrder->setNotes($notes);
        }

        // Ajouter les lignes
        foreach ($linesData as $lineData) {
            $line = $this->createOrderLine($lineData);
            $purchaseOrder->addLine($line);
        }

        $this->purchaseOrdersRepository->save($purchaseOrder, true);

        return $purchaseOrder;
    }

    private function createOrderLine(array $data): PurchaseOrderLines
    {
        $line = new PurchaseOrderLines();
        $line->setProduct($data['product']);
        $line->setQuantityOrdered($data['quantity']);
        $line->setUnitPrice($data['unitPrice']);

        if (isset($data['taxRate'])) {
            $line->setTaxRate($data['taxRate']);
        }

        if (isset($data['warehouse'])) {
            $line->setWarehouse($data['warehouse']);
        }

        if (isset($data['expectedDate'])) {
            $line->setExpectedDate($data['expectedDate']);
        }

        return $line;
    }

    public function updatePurchaseOrder(PurchaseOrders $purchaseOrder, array $data): PurchaseOrders
    {
        if (!$purchaseOrder->isDraft()) {
            throw new \RuntimeException('Seules les commandes en brouillon peuvent être modifiées');
        }

        if (isset($data['expectedDeliveryDate'])) {
            $purchaseOrder->setExpectedDeliveryDate($data['expectedDeliveryDate']);
        }

        if (isset($data['notes'])) {
            $purchaseOrder->setNotes($data['notes']);
        }

        // Mise à jour des lignes
        if (isset($data['lines'])) {
            $this->updateOrderLines($purchaseOrder, $data['lines']);
        }

        $purchaseOrder->updateTotals();
        $this->entityManager->flush();

        return $purchaseOrder;
    }

    private function updateOrderLines(PurchaseOrders $purchaseOrder, array $linesData): void
    {
        // Supprimer les lignes existantes
        foreach ($purchaseOrder->getLines() as $line) {
            $this->entityManager->remove($line);
        }
        $purchaseOrder->getLines()->clear();

        // Ajouter les nouvelles lignes
        foreach ($linesData as $lineData) {
            $line = $this->createOrderLine($lineData);
            $purchaseOrder->addLine($line);
        }
    }

    public function submitForApproval(PurchaseOrders $purchaseOrder): PurchaseOrders
    {
        if (!$purchaseOrder->isDraft()) {
            throw new \RuntimeException('Seules les commandes en brouillon peuvent être soumises pour approbation');
        }

        if ($purchaseOrder->getLines()->count() === 0) {
            throw new \RuntimeException('Impossible de soumettre une commande sans lignes');
        }

        $purchaseOrder->setStatus('pending_approval');
        $this->entityManager->flush();

        return $purchaseOrder;
    }

    public function approvePurchaseOrder(PurchaseOrders $purchaseOrder, TenantUsers $approver): PurchaseOrders
    {
        if (!$purchaseOrder->canBeApproved()) {
            throw new \RuntimeException('Cette commande ne peut pas être approuvée');
        }

        $purchaseOrder->approve($approver);
        $this->entityManager->flush();

        return $purchaseOrder;
    }

    public function markAsOrdered(PurchaseOrders $purchaseOrder): PurchaseOrders
    {
        if (!$purchaseOrder->canBeOrdered()) {
            throw new \RuntimeException('Cette commande ne peut pas être passée');
        }

        $purchaseOrder->markAsOrdered();
        $this->entityManager->flush();

        return $purchaseOrder;
    }

    public function receivePurchaseOrderLine(
        PurchaseOrderLines $line,
        string $quantity,
        ?\DateTimeInterface $receiveDate = null,
        ?string $notes = null
    ): void {
        if (!$line->getPurchaseOrder()->isReceivable()) {
            throw new \RuntimeException('Cette commande n\'est pas en état de réception');
        }

        if (!$line->canReceiveMore()) {
            throw new \RuntimeException('Cette ligne ne peut plus recevoir de quantité');
        }

        // Recevoir la quantité
        $line->receiveQuantity($quantity, $receiveDate);

        // Si un entrepôt est spécifié, créer un mouvement de stock
        if ($line->getWarehouse()) {
            $this->stockMovementsService->createIncomingMovement(
                $line->getProduct(),
                $line->getWarehouse(),
                $quantity,
                null, // Utiliser le type par défaut PURCHASE_RECEIPT
                $line->getUnitPrice(),
                $line->getPurchaseOrder()->getUuid(),
                'purchase_order',
                $notes ?? sprintf('Réception commande %s', $line->getPurchaseOrder()->getPoNumber())
            );
        }

        $this->entityManager->flush();
    }

    public function receiveFullPurchaseOrder(
        PurchaseOrders $purchaseOrder,
        ?\DateTimeInterface $receiveDate = null,
        ?string $notes = null
    ): void {
        if (!$purchaseOrder->isReceivable()) {
            throw new \RuntimeException('Cette commande n\'est pas en état de réception');
        }

        foreach ($purchaseOrder->getLines() as $line) {
            if ($line->canReceiveMore()) {
                $quantityToReceive = $line->getRemainingToReceive();
                $this->receivePurchaseOrderLine($line, $quantityToReceive, $receiveDate, $notes);
            }
        }
    }

    public function cancelPurchaseOrder(PurchaseOrders $purchaseOrder, string $reason = null): PurchaseOrders
    {
        $purchaseOrder->cancel($reason);
        $this->entityManager->flush();

        return $purchaseOrder;
    }

    public function closePurchaseOrder(PurchaseOrders $purchaseOrder): PurchaseOrders
    {
        $purchaseOrder->close();
        $this->entityManager->flush();

        return $purchaseOrder;
    }

    public function deletePurchaseOrder(PurchaseOrders $purchaseOrder): void
    {
        if (!$purchaseOrder->isDeletable()) {
            throw new \RuntimeException('Cette commande ne peut pas être supprimée');
        }

        $this->purchaseOrdersRepository->softDelete($purchaseOrder);
    }

    public function getPurchaseOrderSummary(PurchaseOrders $purchaseOrder): array
    {
        $lineStats = $this->purchaseOrderLinesRepository->getLineStatistics($purchaseOrder);

        return [
            'purchaseOrder' => $purchaseOrder->toArray(),
            'statistics' => [
                'totalLines' => $lineStats['totalLines'] ?? 0,
                'totalOrdered' => $lineStats['totalOrdered'] ?? '0',
                'totalReceived' => $lineStats['totalReceived'] ?? '0',
                'notReceivedCount' => $lineStats['notReceivedCount'] ?? 0,
                'partiallyReceivedCount' => $lineStats['partiallyReceivedCount'] ?? 0,
                'fullyReceivedCount' => $lineStats['fullyReceivedCount'] ?? 0,
            ],
        ];
    }

    public function getSupplierPurchaseHistory(Suppliers $supplier, array $filters = []): array
    {
        return $this->purchaseOrdersRepository->findBySupplier($supplier, $filters);
    }

    public function getProductPurchaseHistory(Products $product, int $limit = 10): array
    {
        return $this->purchaseOrderLinesRepository->getProductPurchaseHistory($product, $limit);
    }

    public function getOpenPurchaseOrders(): array
    {
        return $this->purchaseOrdersRepository->findReceivableOrders();
    }

    public function getOverduePurchaseOrders(): array
    {
        return $this->purchaseOrdersRepository->findOverdueOrders();
    }

    public function getPurchaseOrderStatistics(\DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        return $this->purchaseOrdersRepository->getStatistics($startDate, $endDate);
    }

    public function validatePurchaseOrder(PurchaseOrders $purchaseOrder): array
    {
        $errors = [];

        if ($purchaseOrder->getLines()->count() === 0) {
            $errors[] = 'La commande doit contenir au moins une ligne';
        }

        foreach ($purchaseOrder->getLines() as $line) {
            if (bccomp($line->getQuantityOrdered(), '0', 6) <= 0) {
                $errors[] = sprintf('La quantité commandée pour le produit %s doit être positive',
                    $line->getProduct()->getName());
            }

            if (bccomp($line->getUnitPrice(), '0', 4) < 0) {
                $errors[] = sprintf('Le prix unitaire pour le produit %s ne peut pas être négatif',
                    $line->getProduct()->getName());
            }
        }

        return $errors;
    }
}
