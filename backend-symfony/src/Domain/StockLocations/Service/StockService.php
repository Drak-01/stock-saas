<?php

namespace App\Domain\StockLocations\Service;

use App\Domain\StockLocations\StockLocations;
use App\Domain\Products\Products;
use App\Domain\StockLocations\StockLocationsRepository;
use App\Domain\Warehouses\Warehouses;
use Doctrine\ORM\EntityManagerInterface;

class StockService
{
    private StockLocationsRepository $stockLocationsRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        StockLocationsRepository $stockLocationsRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->stockLocationsRepository = $stockLocationsRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Trouve le meilleur entrepôt pour prélever du stock
     */
    public function findBestWarehouseForPickup(
        Products $product,
        float $requiredQuantity,
        ?string $preferredWarehouseId = null
    ): ?StockLocations {
        // 1. Si entrepôt préféré spécifié et a assez de stock
        if ($preferredWarehouseId !== null) {
            $preferredLocation = $this->stockLocationsRepository->findByProductAndWarehouse(
                $product->getId(),
                $preferredWarehouseId
            );

            if ($preferredLocation && $preferredLocation->getAvailableQuantity() >= $requiredQuantity) {
                return $preferredLocation;
            }
        }

        // 2. Chercher dans tous les entrepôts avec stock disponible
        $availableLocations = $this->stockLocationsRepository->findAvailableStock(
            $product->getId(),
            $requiredQuantity
        );

        if (empty($availableLocations)) {
            return null;
        }

        // 3. Stratégie de sélection :
        //    a. Entrepôt avec le plus de stock disponible
        //    b. Entrepôt le plus proche du client (non implémenté ici)
        //    c. Entrepôt avec le coût moyen le plus bas

        usort($availableLocations, function($a, $b) use ($requiredQuantity) {
            // Priorité : peut satisfaire la commande entièrement
            $aCanFullySupply = $a->getAvailableQuantity() >= $requiredQuantity;
            $bCanFullySupply = $b->getAvailableQuantity() >= $requiredQuantity;

            if ($aCanFullySupply !== $bCanFullySupply) {
                return $bCanFullySupply <=> $aCanFullySupply;
            }

            // Ensuite : quantité disponible (plus est mieux)
            return $b->getAvailableQuantity() <=> $a->getAvailableQuantity();
        });

        return $availableLocations[0];
    }

    /**
     * Répartit une quantité sur plusieurs entrepôts
     */
    public function allocateStockFromMultipleWarehouses(
        Products $product,
        float $requiredQuantity
    ): array {
        $allocations = [];
        $remainingQuantity = $requiredQuantity;

        $availableLocations = $this->stockLocationsRepository->findAvailableStock(
            $product->getId(),
            0 // Tous les emplacements avec du stock disponible
        );

        foreach ($availableLocations as $location) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $available = $location->getAvailableQuantity();
            $toAllocate = min($available, $remainingQuantity);

            if ($toAllocate > 0) {
                $allocations[] = [
                    'location' => $location,
                    'quantity' => $toAllocate,
                ];

                $remainingQuantity -= $toAllocate;
            }
        }

        if ($remainingQuantity > 0) {
            throw new \RuntimeException(sprintf(
                'Insufficient stock. Required: %s, Available: %s',
                $requiredQuantity,
                $requiredQuantity - $remainingQuantity
            ));
        }

        return $allocations;
    }

    /**
     * Transfère du stock entre entrepôts
     */
    public function transferStock(
        Products $product,
        Warehouses $fromWarehouse,
        Warehouses $toWarehouse,
        float $quantity,
        ?float $transferCost = null
    ): void {
        // 1. Vérifier le stock source
        $sourceLocation = $this->stockLocationsRepository->findByProductAndWarehouse(
            $product->getId(),
            $fromWarehouse->getId()
        );

        if (!$sourceLocation || $sourceLocation->getAvailableQuantity() < $quantity) {
            throw new \RuntimeException('Insufficient stock in source warehouse');
        }

        // 2. Prélever du stock source
        $sourceLocation->decreaseOnHand($quantity);

        // 3. Ajouter au stock destination
        $destinationLocation = $this->stockLocationsRepository->findByProductAndWarehouse(
            $product->getId(),
            $toWarehouse->getId()
        );

        if (!$destinationLocation) {
            $destinationLocation = new StockLocations();
            $destinationLocation->setProduct($product);
            $destinationLocation->setWarehouse($toWarehouse);
            $destinationLocation->setQuantityOnHand('0');
            $this->entityManager->persist($destinationLocation);
        }

        // Utiliser le coût moyen du source ou un coût de transfert spécifié
        $unitCost = $transferCost ?? (float) ($sourceLocation->getAverageCost() ?? 0);
        $destinationLocation->increaseOnHand($quantity, $unitCost);

        // 4. Enregistrer le mouvement de transfert
        $this->recordTransferMovement($product, $fromWarehouse, $toWarehouse, $quantity, $unitCost);

        $this->entityManager->flush();
    }

    /**
     * Fait un inventaire physique et ajuste les écarts
     */
    public function performPhysicalInventory(
        StockLocations $location,
        float $countedQuantity,
        \DateTimeInterface $countDate,
        string $reason = 'physical_count'
    ): array {
        $previousQuantity = (float) $location->getQuantityOnHand();
        $difference = $countedQuantity - $previousQuantity;

        $location->recordPhysicalCount($countedQuantity, $countDate);

        // Enregistrer l'ajustement
        if ($difference !== 0.0) {
            $this->recordAdjustmentMovement(
                $location->getProduct(),
                $location->getWarehouse(),
                $difference,
                $reason,
                'Inventory count adjustment'
            );
        }

        $this->entityManager->flush();

        return [
            'previous_quantity' => $previousQuantity,
            'counted_quantity' => $countedQuantity,
            'difference' => $difference,
            'adjustment_needed' => $difference !== 0.0,
            'location' => $location->toArray(),
        ];
    }

    /**
     * Calcule les indicateurs de performance du stock
     */
    public function calculateStockMetrics(Products $product): array
    {
        $locations = $this->stockLocationsRepository->findByProduct($product->getId());

        $totalOnHand = 0;
        $totalReserved = 0;
        $totalOrdered = 0;
        $totalValue = 0;
        $warehouseDetails = [];

        foreach ($locations as $location) {
            $onHand = (float) $location->getQuantityOnHand();
            $reserved = (float) $location->getQuantityReserved();
            $ordered = (float) $location->getQuantityOrdered();
            $value = $location->getStockValue() ?? 0;

            $totalOnHand += $onHand;
            $totalReserved += $reserved;
            $totalOrdered += $ordered;
            $totalValue += $value;

            $warehouseDetails[] = [
                'warehouse' => $location->getWarehouse()->getName(),
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'ordered' => $ordered,
                'available' => $location->getAvailableQuantity(),
                'value' => $value,
                'average_cost' => $location->getAverageCost(),
                'is_below_reorder' => $location->isBelowReorderPoint(),
                'last_count_date' => $location->getLastCountDate()?->format('Y-m-d'),
                'needs_count' => $location->needsPhysicalCount(),
            ];
        }

        $available = $totalOnHand - $totalReserved;
        $future = $totalOnHand + $totalOrdered;

        // Taux de rotation (simplifié)
        $turnoverRate = $totalOnHand > 0 ? ($totalReserved / $totalOnHand) * 100 : 0;

        // Jours de stock (estimation)
        $averageDailySales = $this->estimateAverageDailySales($product);
        $daysOfSupply = $averageDailySales > 0 ? $available / $averageDailySales : null;

        return [
            'product_id' => $product->getId(),
            'product_sku' => $product->getSku(),
            'product_name' => $product->getName(),
            'total_on_hand' => $totalOnHand,
            'total_reserved' => $totalReserved,
            'total_ordered' => $totalOrdered,
            'total_available' => $available,
            'total_future' => $future,
            'total_value' => $totalValue,
            'turnover_rate' => round($turnoverRate, 2),
            'days_of_supply' => $daysOfSupply,
            'is_below_reorder_point' => $available <= (float) ($product->getReorderPoint() ?? 0),
            'is_out_of_stock' => $available <= 0,
            'is_overstocked' => $totalOnHand > (float) ($product->getMaxStockLevel() ?? PHP_FLOAT_MAX),
            'warehouse_breakdown' => $warehouseDetails,
            'suggested_action' => $this->getSuggestedAction($product, $available, $totalOrdered),
        ];
    }

    /**
     * Optimise la répartition du stock entre entrepôts
     */
    public function optimizeStockAllocation(Products $product): array
    {
        $locations = $this->stockLocationsRepository->findByProduct($product->getId());

        if (count($locations) < 2) {
            return ['optimization_needed' => false];
        }

        $totalStock = array_sum(array_map(
            fn($loc) => (float) $loc->getQuantityOnHand(),
            $locations
        ));

        $averagePerWarehouse = $totalStock / count($locations);
        $transfers = [];

        foreach ($locations as $location) {
            $currentStock = (float) $location->getQuantityOnHand();
            $deviation = $currentStock - $averagePerWarehouse;

            if (abs($deviation) > $averagePerWarehouse * 0.2) { // 20% de tolérance
                $warehousesWithSpace = array_filter($locations, function($otherLoc) use ($location, $averagePerWarehouse) {
                    return $otherLoc->getId() !== $location->getId() &&
                        (float) $otherLoc->getQuantityOnHand() < $averagePerWarehouse;
                });

                if (!empty($warehousesWithSpace) && $deviation > 0) {
                    $transferQty = min($deviation, $averagePerWarehouse - min(array_map(
                            fn($loc) => (float) $loc->getQuantityOnHand(),
                            $warehousesWithSpace
                        )));

                    if ($transferQty > 0) {
                        $transfers[] = [
                            'from_warehouse' => $location->getWarehouse()->getName(),
                            'to_warehouse' => reset($warehousesWithSpace)->getWarehouse()->getName(),
                            'quantity' => $transferQty,
                            'reason' => 'Stock optimization',
                        ];
                    }
                }
            }
        }

        return [
            'optimization_needed' => !empty($transfers),
            'total_stock' => $totalStock,
            'average_per_warehouse' => $averagePerWarehouse,
            'suggested_transfers' => $transfers,
        ];
    }

    private function estimateAverageDailySales(Products $product): float
    {
        // Implémentation simplifiée
        // Dans la réalité, on analyserait l'historique des ventes
        $thirtyDaysAgo = new \DateTime('-30 days');

        $totalSales = $this->entityManager->createQueryBuilder()
            ->select('SUM(sol.quantityFulfilled)')
            ->from('App\Domain\SalesOrderLines\SalesOrderLines', 'sol')
            ->join('sol.salesOrder', 'so')
            ->where('sol.product = :productId')
            ->andWhere('so.orderDate >= :date')
            ->setParameter('productId', $product->getId())
            ->setParameter('date', $thirtyDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return $totalSales ? $totalSales / 30 : 0;
    }

    private function getSuggestedAction(Products $product, float $availableStock, float $orderedStock): string
    {
        $reorderPoint = (float) ($product->getReorderPoint() ?? 0);
        $maxStock = (float) ($product->getMaxStockLevel() ?? PHP_FLOAT_MAX);

        if ($availableStock <= 0) {
            return 'URGENT: Out of stock - reorder immediately';
        }

        if ($availableStock <= $reorderPoint * 0.5) {
            return 'CRITICAL: Very low stock - expedite reorder';
        }

        if ($availableStock <= $reorderPoint) {
            return 'WARNING: Below reorder point - place order';
        }

        if ($availableStock + $orderedStock >= $maxStock * 0.9) {
            return 'INFO: Near maximum capacity - stop ordering';
        }

        if ($availableStock >= $maxStock) {
            return 'WARNING: Overstocked - consider promotions';
        }

        return 'OK: Stock level normal';
    }

    private function recordTransferMovement(
        Products $product,
        Warehouses $fromWarehouse,
        Warehouses $toWarehouse,
        float $quantity,
        float $unitCost
    ): void {
        // Implémentation réelle enregistrerait dans stock_movements
        // Ceci est un placeholder
    }

    private function recordAdjustmentMovement(
        Products $product,
        Warehouses $warehouse,
        float $quantity,
        string $reason,
        string $notes
    ): void {
        // Implémentation réelle enregistrerait dans stock_movements
        // Ceci est un placeholder
    }
}
