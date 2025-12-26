<?php

namespace App\Domain\StockMovements\Service;

use App\Domain\StockLocations\StockLocationsRepository;
use App\Domain\StockMovements\StockMovements;
use App\Domain\Products\Products;
use App\Domain\StockMovements\StockMovementsRepository;
use App\Domain\Warehouses\Warehouses;
use App\Domain\MovementTypes\MovementTypes;
use App\Domain\TenantUsers\TenantUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class StockMovementsService
{
    public function __construct(
        private StockMovementsRepository $repository,
        private StockLocationsRepository $stockLocationsRepository,
        //private MovementTypesService $movementTypesService,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function createIncomingMovement(
        Products $product,
        Warehouses $warehouse,
        string $quantity,
        MovementTypes $movementType = null,
        ?string $unitCost = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $notes = null
    ): StockMovements {
        if (!$movementType) {
            $movementType = $this->movementTypesService->validateMovementType('PURCHASE_RECEIPT', 'in');
        } elseif (!$movementType->isIncoming()) {
            throw new \InvalidArgumentException('Le type de mouvement doit être de type "entrée"');
        }

        $movement = new StockMovements();
        $movement->setMovementType($movementType);
        $movement->setProduct($product);
        $movement->setToWarehouse($warehouse);
        $movement->setQuantity($quantity);
        $movement->setUnitCost($unitCost);
        $movement->setReferenceId($referenceId);
        $movement->setReferenceType($referenceType);
        $movement->setNotes($notes);

        if ($this->security->getUser() instanceof TenantUsers) {
            $movement->setUser($this->security->getUser());
        }

        $this->repository->save($movement, true);

        // Mettre à jour le stock
        $this->updateStockFromMovement($movement);

        return $movement;
    }

    public function createOutgoingMovement(
        Products $product,
        Warehouses $warehouse,
        string $quantity,
        MovementTypes $movementType = null,
        ?string $unitCost = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $notes = null
    ): StockMovements {
        if (!$movementType) {
            $movementType = $this->movementTypesService->validateMovementType('SALES_SHIPMENT', 'out');
        } elseif (!$movementType->isOutgoing()) {
            throw new \InvalidArgumentException('Le type de mouvement doit être de type "sortie"');
        }

        // Vérifier le stock disponible
        $availableStock = $this->stockLocationsRepository->getAvailableStock($product, $warehouse);
        if (bccomp($availableStock, $quantity, 6) < 0) {
            throw new \RuntimeException(sprintf(
                'Stock insuffisant. Disponible: %s, Demandé: %s',
                $availableStock,
                $quantity
            ));
        }

        $movement = new StockMovements();
        $movement->setMovementType($movementType);
        $movement->setProduct($product);
        $movement->setFromWarehouse($warehouse);
        $movement->setQuantity($quantity);
        $movement->setUnitCost($unitCost);
        $movement->setReferenceId($referenceId);
        $movement->setReferenceType($referenceType);
        $movement->setNotes($notes);

        if ($this->security->getUser() instanceof TenantUsers) {
            $movement->setUser($this->security->getUser());
        }

        $this->repository->save($movement, true);

        // Mettre à jour le stock
        $this->updateStockFromMovement($movement);

        return $movement;
    }

    public function createTransferMovement(
        Products $product,
        Warehouses $fromWarehouse,
        Warehouses $toWarehouse,
        string $quantity,
        MovementTypes $movementType = null,
        ?string $unitCost = null,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $notes = null
    ): StockMovements {
        if (!$movementType) {
            $movementType = $this->movementTypesService->validateMovementType('TRANSFER_OUT', 'out');
        } elseif (!$movementType->isTransfer() && !$movementType->isOutgoing()) {
            throw new \InvalidArgumentException('Le type de mouvement doit être de type "transfert" ou "sortie"');
        }

        // Vérifier le stock disponible dans l'entrepôt source
        $availableStock = $this->stockLocationsRepository->getAvailableStock($product, $fromWarehouse);
        if (bccomp($availableStock, $quantity, 6) < 0) {
            throw new \RuntimeException(sprintf(
                'Stock insuffisant dans l\'entrepôt source. Disponible: %s, Demandé: %s',
                $availableStock,
                $quantity
            ));
        }

        // Créer le mouvement de sortie
        $outMovement = new StockMovements();
        $outMovement->setMovementType($movementType);
        $outMovement->setProduct($product);
        $outMovement->setFromWarehouse($fromWarehouse);
        $outMovement->setQuantity($quantity);
        $outMovement->setUnitCost($unitCost);
        $outMovement->setReferenceId($referenceId);
        $outMovement->setReferenceType($referenceType);
        $outMovement->setNotes($notes);

        if ($this->security->getUser() instanceof TenantUsers) {
            $outMovement->setUser($this->security->getUser());
        }

        $this->repository->save($outMovement, false);

        // Créer le mouvement d'entrée correspondant
        $inMovementType = $this->movementTypesService->validateMovementType('TRANSFER_IN', 'in');

        $inMovement = new StockMovements();
        $inMovement->setMovementType($inMovementType);
        $inMovement->setProduct($product);
        $inMovement->setToWarehouse($toWarehouse);
        $inMovement->setQuantity($quantity);
        $inMovement->setUnitCost($unitCost);
        $inMovement->setReferenceId($referenceId);
        $inMovement->setReferenceType($referenceType);
        $inMovement->setNotes($notes);
        $inMovement->setUser($outMovement->getUser());

        $this->repository->save($inMovement, false);

        $this->entityManager->flush();

        // Mettre à jour le stock
        $this->updateStockFromMovement($outMovement);
        $this->updateStockFromMovement($inMovement);

        return $outMovement;
    }

    public function createAdjustmentMovement(
        Products $product,
        Warehouses $warehouse,
        string $quantity,
        string $newQuantity,
        MovementTypes $movementType = null,
        ?string $unitCost = null,
        ?string $notes = null
    ): StockMovements {
        if (!$movementType) {
            $movementType = $this->movementTypesService->validateMovementType('INVENTORY_ADJUSTMENT', 'adjustment');
        } elseif (!$movementType->isAdjustment()) {
            throw new \InvalidArgumentException('Le type de mouvement doit être de type "ajustement"');
        }

        $difference = bcsub($newQuantity, $quantity, 6);

        if (bccomp($difference, '0', 6) === 0) {
            throw new \RuntimeException('Aucun ajustement nécessaire');
        }

        if (bccomp($difference, '0', 6) > 0) {
            // Ajustement positif (ajout de stock)
            $effectType = $this->movementTypesService->validateMovementType('INVENTORY_ADJUSTMENT', 'in');
            $movement = $this->createIncomingMovement(
                $product,
                $warehouse,
                $difference,
                $effectType,
                $unitCost,
                null,
                'inventory_adjustment',
                $notes
            );
        } else {
            // Ajustement négatif (retrait de stock)
            $effectType = $this->movementTypesService->validateMovementType('INVENTORY_ADJUSTMENT', 'out');
            $movement = $this->createOutgoingMovement(
                $product,
                $warehouse,
                abs($difference),
                $effectType,
                $unitCost,
                null,
                'inventory_adjustment',
                $notes
            );
        }

        return $movement;
    }

    private function updateStockFromMovement(StockMovements $movement): void
    {
        $product = $movement->getProduct();
        $warehouse = $movement->getWarehouse();
        $signedQuantity = $movement->getSignedQuantity();

        if (!$warehouse) {
            return;
        }

        $stockLocation = $this->stockLocationsRepository->findByWarehouseAndProduct($warehouse, $product);

        if (!$stockLocation) {
            // Créer une nouvelle localisation de stock
            $stockLocation = $this->stockLocationsRepository->createStockLocation($warehouse, $product);
        }

        // Mettre à jour la quantité
        $newQuantity = bcadd($stockLocation->getQuantityOnHand(), $signedQuantity, 6);
        $stockLocation->setQuantityOnHand($newQuantity);

        // Mettre à jour le coût moyen si applicable
        if ($movement->getUnitCost() !== null && $movement->isIncoming()) {
            $this->updateAverageCost($stockLocation, $movement);
        }

        $this->entityManager->flush();
    }

    private function updateAverageCost($stockLocation, StockMovements $movement): void
    {
        $currentQuantity = $stockLocation->getQuantityOnHand();
        $currentCost = $stockLocation->getAverageCost() ?? '0';
        $movementQuantity = $movement->getQuantity();
        $movementCost = $movement->getUnitCost();

        if (bccomp($currentQuantity, '0', 6) <= 0) {
            // Premier mouvement ou stock nul
            $stockLocation->setAverageCost($movementCost);
        } else {
            // Calcul du coût moyen pondéré
            $currentTotal = bcmul($currentQuantity, $currentCost, 4);
            $movementTotal = bcmul($movementQuantity, $movementCost, 4);
            $totalQuantity = bcadd($currentQuantity, $movementQuantity, 6);
            $totalValue = bcadd($currentTotal, $movementTotal, 4);

            $averageCost = bcdiv($totalValue, $totalQuantity, 4);
            $stockLocation->setAverageCost($averageCost);
        }
    }

    public function getProductStockHistory(Products $product, array $filters = []): array
    {
        return $this->repository->findByProduct($product, $filters);
    }

    public function getWarehouseStockHistory(Warehouses $warehouse, array $filters = []): array
    {
        return $this->repository->findByWarehouse($warehouse, $filters);
    }

    public function getMovementStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->repository->getMovementStatistics($startDate, $endDate);
    }

    public function reverseMovement(StockMovements $movement, string $notes = null): StockMovements
    {
        if ($movement->isTransfer()) {
            throw new \RuntimeException('Les transferts doivent être annulés via l\'opération de transfert inverse');
        }

        // Trouver le type de mouvement inverse
        $reverseEffect = $movement->isIncoming() ? 'out' : 'in';
        $reverseType = $this->movementTypesService->validateMovementType(
            $movement->isIncoming() ? 'REVERSAL_OUT' : 'REVERSAL_IN',
            $reverseEffect
        );

        $reverseMovement = new StockMovements();
        $reverseMovement->setMovementType($reverseType);
        $reverseMovement->setProduct($movement->getProduct());
        $reverseMovement->setQuantity($movement->getQuantity());
        $reverseMovement->setUnitCost($movement->getUnitCost());
        $reverseMovement->setReferenceId($movement->getUuid());
        $reverseMovement->setReferenceType('stock_movement_reversal');
        $reverseMovement->setNotes($notes ?? sprintf('Annulation du mouvement %s', $movement->getUuid()));

        if ($movement->isIncoming()) {
            $reverseMovement->setFromWarehouse($movement->getToWarehouse());
        } else {
            $reverseMovement->setToWarehouse($movement->getFromWarehouse());
        }

        if ($this->security->getUser() instanceof TenantUsers) {
            $reverseMovement->setUser($this->security->getUser());
        }

        $this->repository->save($reverseMovement, true);

        // Mettre à jour le stock
        $this->updateStockFromMovement($reverseMovement);

        return $reverseMovement;
    }

    public function validateMovement(StockMovements $movement): array
    {
        $errors = [];

        if (!$movement->hasValidReference() && $movement->requiresReference()) {
            $errors[] = 'Une référence est requise pour ce type de mouvement';
        }

        if (bccomp($movement->getQuantity(), '0', 6) <= 0) {
            $errors[] = 'La quantité doit être supérieure à zéro';
        }

        if ($movement->isTransfer() && (!$movement->getFromWarehouse() || !$movement->getToWarehouse())) {
            $errors[] = 'Un transfert nécessite un entrepôt source et un entrepôt destination';
        }

        if ($movement->isIncoming() && !$movement->getToWarehouse()) {
            $errors[] = 'Une entrée nécessite un entrepôt destination';
        }

        if ($movement->isOutgoing() && !$movement->getFromWarehouse()) {
            $errors[] = 'Une sortie nécessite un entrepôt source';
        }

        if ($movement->isOutgoing()) {
            $availableStock = $this->stockLocationsRepository->getAvailableStock(
                $movement->getProduct(),
                $movement->getFromWarehouse()
            );

            if (bccomp($availableStock, $movement->getQuantity(), 6) < 0) {
                $errors[] = sprintf(
                    'Stock insuffisant. Disponible: %s, Demandé: %s',
                    $availableStock,
                    $movement->getQuantity()
                );
            }
        }

        return $errors;
    }
}
