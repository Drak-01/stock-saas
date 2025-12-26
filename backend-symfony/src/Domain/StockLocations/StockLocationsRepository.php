<?php

namespace App\Domain\StockLocations;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLocations>
 */
class StockLocationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockLocations::class);
    }

    public function save(StockLocations $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(StockLocations $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByProductAndWarehouse(string $productId, string $warehouseId): ?StockLocations
    {
        return $this->createQueryBuilder('sl')
            ->andWhere('sl.product = :productId')
            ->andWhere('sl.warehouse = :warehouseId')
            ->andWhere('sl.deletedAt IS NULL')
            ->setParameter('productId', $productId)
            ->setParameter('warehouseId', $warehouseId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByProduct(string $productId): array
    {
        return $this->createQueryBuilder('sl')
            ->andWhere('sl.product = :productId')
            ->andWhere('sl.deletedAt IS NULL')
            ->setParameter('productId', $productId)
            ->orderBy('sl.warehouse', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByWarehouse(string $warehouseId): array
    {
        return $this->createQueryBuilder('sl')
            ->andWhere('sl.warehouse = :warehouseId')
            ->andWhere('sl.deletedAt IS NULL')
            ->setParameter('warehouseId', $warehouseId)
            ->orderBy('sl.product', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLocationsBelowReorderPoint(): array
    {
        return $this->createQueryBuilder('sl')
            ->join('sl.product', 'p')
            ->andWhere('sl.deletedAt IS NULL')
            ->andWhere('p.reorderPoint IS NOT NULL')
            ->andWhere('(sl.quantityOnHand - sl.quantityReserved) <= p.reorderPoint')
            ->orderBy('(sl.quantityOnHand - sl.quantityReserved)', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLocationsNeedingPhysicalCount(int $maxDays = 90): array
    {
        $dateThreshold = (new \DateTime())->modify("-$maxDays days");

        return $this->createQueryBuilder('sl')
            ->andWhere('sl.deletedAt IS NULL')
            ->andWhere('sl.lastCountDate IS NULL OR sl.lastCountDate < :threshold')
            ->setParameter('threshold', $dateThreshold)
            ->orderBy('sl.lastCountDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalStockValueByWarehouse(): array
    {
        return $this->createQueryBuilder('sl')
            ->select('w.id as warehouse_id, w.name as warehouse_name, SUM(sl.quantityOnHand * COALESCE(sl.averageCost, 0)) as total_value')
            ->join('sl.warehouse', 'w')
            ->andWhere('sl.deletedAt IS NULL')
            ->groupBy('w.id, w.name')
            ->orderBy('total_value', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStockSummaryByProduct(): array
    {
        return $this->createQueryBuilder('sl')
            ->select('p.id, p.sku, p.name,
                     SUM(sl.quantityOnHand) as total_on_hand,
                     SUM(sl.quantityReserved) as total_reserved,
                     SUM(sl.quantityOrdered) as total_ordered')
            ->join('sl.product', 'p')
            ->andWhere('sl.deletedAt IS NULL')
            ->groupBy('p.id, p.sku, p.name')
            ->orderBy('total_on_hand', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAvailableStock(string $productId, float $requiredQuantity): array
    {
        return $this->createQueryBuilder('sl')
            ->andWhere('sl.product = :productId')
            ->andWhere('sl.deletedAt IS NULL')
            ->andWhere('(sl.quantityOnHand - sl.quantityReserved) > 0')
            ->setParameter('productId', $productId)
            ->orderBy('(sl.quantityOnHand - sl.quantityReserved)', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchByLocationReference(string $search): array
    {
        return $this->createQueryBuilder('sl')
            ->andWhere('sl.deletedAt IS NULL')
            ->andWhere('sl.locationReference LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('sl.warehouse', 'ASC')
            ->addOrderBy('sl.locationReference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getOccupancyRateByWarehouse(): array
    {
        return $this->createQueryBuilder('sl')
            ->select('w.id, w.name,
                     COUNT(sl.id) as total_locations,
                     SUM(CASE WHEN sl.quantityOnHand > 0 THEN 1 ELSE 0 END) as occupied_locations,
                     (SUM(CASE WHEN sl.quantityOnHand > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(sl.id)) as occupancy_rate')
            ->join('sl.warehouse', 'w')
            ->andWhere('sl.deletedAt IS NULL')
            ->groupBy('w.id, w.name')
            ->orderBy('occupancy_rate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
