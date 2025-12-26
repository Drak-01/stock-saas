<?php

namespace App\Domain\ProductionOrders\Service;

use App\Domain\AuditLogs\Service\AuditLogService;
use App\Domain\BillsOfMaterial\BillsOfMaterial;
use App\Domain\BillsOfMaterial\Service\BillsOfMaterialService;
use App\Domain\ProductionOrders\ProductionOrders;
use App\Domain\ProductionOrders\ProductionOrdersRepository;
use App\Domain\Products\Products;
use App\Domain\StockMovements\Service\StockMovementsService;
use App\Domain\TenantUsers\TenantUsers;
use App\Domain\Warehouses\Warehouses;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ProductionOrdersService
{
    public function __construct(
        private ProductionOrdersRepository $productionOrdersRepository,
        private BillsOfMaterialService $bomService,
        private StockMovementsService $stockMovementsService,
        private AuditLogService $auditLogService,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function createProductionOrder(
        BillsOfMaterial $bom,
        string $quantityToProduce,
        ?Warehouses $sourceWarehouse = null,
        ?Warehouses $destinationWarehouse = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $completionDate = null,
        ?string $notes = null
    ): ProductionOrders {
        // Vérifier que la NFE est active
        if (!$bom->isActive()) {
            throw new \RuntimeException('La NFE doit être active pour créer un ordre de production');
        }

        // Vérifier la quantité
        if (bccomp($quantityToProduce, '0', 6) <= 0) {
            throw new \InvalidArgumentException('La quantité à produire doit être positive');
        }

        // Créer l'ordre de production
        $productionOrder = new ProductionOrders();
        $productionOrder->setBom($bom);
        $productionOrder->setQuantityToProduce($quantityToProduce);
        $productionOrder->setSourceWarehouse($sourceWarehouse);
        $productionOrder->setDestinationWarehouse($destinationWarehouse);

        if ($startDate) {
            $productionOrder->setStartDate($startDate);
        }

        if ($completionDate) {
            $productionOrder->setCompletionDate($completionDate);
        }

        if ($notes) {
            $productionOrder->setNotes($notes);
        }

        // Définir l'utilisateur créateur
        if ($this->security->getUser() instanceof TenantUsers) {
            $productionOrder->setCreatedBy($this->security->getUser());
        } else {
            throw new \RuntimeException('Un utilisateur doit être connecté pour créer un ordre de production');
        }

        $this->productionOrdersRepository->save($productionOrder, true);

        // Journaliser l'action
        $this->auditLogService->logProductionOrderCreate($productionOrder);

        return $productionOrder;
    }

    public function updateProductionOrder(ProductionOrders $productionOrder, array $data): ProductionOrders
    {
        if (!$productionOrder->isPlanned() && !$productionOrder->isReserved()) {
            throw new \RuntimeException('Seuls les ordres planifiés ou réservés peuvent être modifiés');
        }

        $oldValues = $productionOrder->toArray();

        if (isset($data['quantityToProduce'])) {
            $productionOrder->setQuantityToProduce($data['quantityToProduce']);
        }

        if (isset($data['sourceWarehouse'])) {
            $productionOrder->setSourceWarehouse($data['sourceWarehouse']);
        }

        if (isset($data['destinationWarehouse'])) {
            $productionOrder->setDestinationWarehouse($data['destinationWarehouse']);
        }

        if (isset($data['startDate'])) {
            $productionOrder->setStartDate($data['startDate']);
        }

        if (isset($data['completionDate'])) {
            $productionOrder->setCompletionDate($data['completionDate']);
        }

        if (isset($data['notes'])) {
            $productionOrder->setNotes($data['notes']);
        }

        $this->entityManager->flush();

        // Journaliser les modifications
        $newValues = $productionOrder->toArray();
        $this->auditLogService->logProductionOrderUpdate($productionOrder, $oldValues, $newValues);

        return $productionOrder;
    }

    public function reserveComponents(ProductionOrders $productionOrder): ProductionOrders
    {
        if (!$productionOrder->canBeReserved()) {
            throw new \RuntimeException('Cet ordre de production ne peut pas être réservé');
        }

        if (!$productionOrder->getSourceWarehouse()) {
            throw new \RuntimeException('Un entrepôt source est requis pour réserver les composants');
        }

        // Vérifier la disponibilité des composants
        $requiredComponents = $productionOrder->getRequiredComponents();
        $availability = $this->checkComponentsAvailability($productionOrder);

        if (!$availability['canProduce']) {
            throw new \RuntimeException('Composants insuffisants: ' . implode(', ', array_column($availability['shortages'], 'component.name')));
        }

        // Réserver les composants (créer des mouvements de stock réservés)
        foreach ($requiredComponents as $componentUuid => $quantity) {
            $component = $this->findComponentByUuid($componentUuid);
            if ($component) {
                // TODO: Implémenter la réservation de stock
                // $this->stockMovementsService->reserveStock($component, $productionOrder->getSourceWarehouse(), $quantity, $productionOrder);
            }
        }

        $productionOrder->setStatus('reserved');
        $this->entityManager->flush();

        // Journaliser l'action
        $this->auditLogService->logProductionOrderReserve($productionOrder);

        return $productionOrder;
    }

    public function startProduction(ProductionOrders $productionOrder): ProductionOrders
    {
        if (!$productionOrder->canBeStarted()) {
            throw new \RuntimeException('Cet ordre de production ne peut pas être démarré');
        }

        $productionOrder->startProduction();
        $this->entityManager->flush();

        // Journaliser l'action
        $this->auditLogService->logProductionOrderStart($productionOrder);

        return $productionOrder;
    }

    public function recordProduction(ProductionOrders $productionOrder, string $quantity): ProductionOrders
    {
        $productionOrder->recordProduction($quantity);
        $this->entityManager->flush();

        // Journaliser l'action
        $this->auditLogService->logProductionRecord($productionOrder, $quantity);

        // Si la production est maintenant terminée, consommer les composants et créer le produit fini
        if ($productionOrder->isCompleted()) {
            $this->completeProductionProcess($productionOrder);
        }

        return $productionOrder;
    }

    public function completeProduction(ProductionOrders $productionOrder, string $quantityProduced = null): ProductionOrders
    {
        if (!$productionOrder->canBeCompleted()) {
            throw new \RuntimeException('Cet ordre de production ne peut pas être complété');
        }

        $productionOrder->completeProduction($quantityProduced);
        $this->entityManager->flush();

        // Consommer les composants et créer le produit fini
        $this->completeProductionProcess($productionOrder);

        // Journaliser l'action
        $this->auditLogService->logProductionOrderComplete($productionOrder);

        return $productionOrder;
    }

    private function completeProductionProcess(ProductionOrders $productionOrder): void
    {
        // Consommer les composants
        $this->consumeComponents($productionOrder);

        // Créer le produit fini
        $this->createFinishedProduct($productionOrder);
    }

    private function consumeComponents(ProductionOrders $productionOrder): void
    {
        if (!$productionOrder->getSourceWarehouse()) {
            return; // Pas d'entrepôt source, donc pas de composants à consommer
        }

        $requiredComponents = $productionOrder->getRequiredComponents();
        $actualQuantity = $productionOrder->getQuantityProduced();
        $bomQuantity = $productionOrder->getBom()->getQuantityProduced();

        // Calculer le ratio de production
        $ratio = bcdiv($actualQuantity, $bomQuantity, 6);

        foreach ($requiredComponents as $componentUuid => $bomQuantityRequired) {
            $component = $this->findComponentByUuid($componentUuid);
            if ($component) {
                // Calculer la quantité réelle consommée
                $actualConsumed = bcmul($bomQuantityRequired, $ratio, 6);

                // Créer un mouvement de sortie pour consommer le composant
                $this->stockMovementsService->createOutgoingMovement(
                    $component,
                    $productionOrder->getSourceWarehouse(),
                    $actualConsumed,
                    null, // Type par défaut
                    $component->getCostPrice(),
                    $productionOrder->getUuid(),
                    'production_order',
                    sprintf('Consommation pour production %s', $productionOrder->getPoNumber())
                );
            }
        }
    }

    private function createFinishedProduct(ProductionOrders $productionOrder): void
    {
        if (!$productionOrder->getDestinationWarehouse()) {
            return; // Pas d'entrepôt destination
        }

        $finishedProduct = $productionOrder->getBom()->getFinishedProduct();
        $quantityProduced = $productionOrder->getQuantityProduced();

        // Calculer le coût de production
        $unitCost = $productionOrder->getBom()->getUnitCost();
        $totalCost = $unitCost ? bcmul($unitCost, $quantityProduced, 4) : null;

        // Créer un mouvement d'entrée pour le produit fini
        $this->stockMovementsService->createIncomingMovement(
            $finishedProduct,
            $productionOrder->getDestinationWarehouse(),
            $quantityProduced,
            null, // Type par défaut
            $totalCost,
            $productionOrder->getUuid(),
            'production_order',
            sprintf('Production %s', $productionOrder->getPoNumber())
        );
    }

    public function cancelProductionOrder(ProductionOrders $productionOrder, string $reason = null): ProductionOrders
    {
        $productionOrder->cancel($reason);
        $this->entityManager->flush();

        // Libérer les réservations de composants si existantes
        if ($productionOrder->isReserved()) {
            $this->releaseComponentReservations($productionOrder);
        }

        // Journaliser l'action
        $this->auditLogService->logProductionOrderCancel($productionOrder, $reason);

        return $productionOrder;
    }

    private function releaseComponentReservations(ProductionOrders $productionOrder): void
    {
        // TODO: Implémenter la libération des réservations de composants
    }

    public function closeProductionOrder(ProductionOrders $productionOrder): ProductionOrders
    {
        $productionOrder->close();
        $this->entityManager->flush();

        // Journaliser l'action
        $this->auditLogService->logProductionOrderClose($productionOrder);

        return $productionOrder;
    }

    public function checkComponentsAvailability(ProductionOrders $productionOrder): array
    {
        if (!$productionOrder->getSourceWarehouse()) {
            return [
                'canProduce' => true, // Pas de vérification sans entrepôt source
                'shortages' => [],
                'totalShortages' => 0,
            ];
        }

        // Récupérer les niveaux de stock actuels
        $stockLevels = $this->getCurrentStockLevels($productionOrder->getSourceWarehouse());

        return $this->bomService->checkComponentsAvailability(
            $productionOrder->getBom(),
            $productionOrder->getQuantityToProduce(),
            $stockLevels
        );
    }

    private function getCurrentStockLevels(Warehouses $warehouse): array
    {
        // TODO: Implémenter la récupération des niveaux de stock actuels
        // Retourner un tableau [componentUuid => quantityAvailable]
        return [];
    }

    private function findComponentByUuid(string $componentUuid): ?Products
    {
        // TODO: Implémenter la recherche de produit par UUID
        return null;
    }

    public function getProductionOrderSummary(ProductionOrders $productionOrder): array
    {
        $availability = $this->checkComponentsAvailability($productionOrder);

        return [
            'productionOrder' => $productionOrder->toDetailedArray(),
            'componentsAvailability' => $availability,
            'estimatedCost' => $productionOrder->getEstimatedCost(),
            'estimatedCompletionDate' => $productionOrder->getEstimatedCompletionDate(),
            'productionDuration' => $productionOrder->getProductionDuration(),
        ];
    }

    public function getProductionStatistics(\DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        return $this->productionOrdersRepository->getStatistics($startDate, $endDate);
    }

    public function getEfficiencyMetrics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->productionOrdersRepository->getEfficiencyMetrics($startDate, $endDate);
    }

    public function getActiveProductionOrders(): array
    {
        return $this->productionOrdersRepository->findActive();
    }

    public function getOverdueProductionOrders(): array
    {
        return $this->productionOrdersRepository->findOverdue();
    }

    public function getDelayedProductionOrders(): array
    {
        return $this->productionOrdersRepository->findDelayed();
    }

    public function validateProductionOrder(ProductionOrders $productionOrder): array
    {
        $errors = [];

        if (bccomp($productionOrder->getQuantityToProduce(), '0', 6) <= 0) {
            $errors[] = 'La quantité à produire doit être positive';
        }

        if (!$productionOrder->getBom()->isActive()) {
            $errors[] = 'La NFE doit être active';
        }

        // Vérifier la disponibilité des composants
        $availability = $this->checkComponentsAvailability($productionOrder);
        if (!$availability['canProduce']) {
            foreach ($availability['shortages'] as $shortage) {
                $errors[] = sprintf(
                    'Stock insuffisant pour %s: requis %s, disponible %s',
                    $shortage['component']['name'],
                    $shortage['requiredQuantity'],
                    $shortage['availableStock']
                );
            }
        }

        // Vérifier les dates
        if ($productionOrder->getStartDate() && $productionOrder->getCompletionDate()) {
            if ($productionOrder->getCompletionDate() < $productionOrder->getStartDate()) {
                $errors[] = 'La date de fin ne peut pas être antérieure à la date de début';
            }
        }

        return $errors;
    }
}
