<?php

namespace App\Domain\StockMovements;

use App\Domain\StockMovements\StockMovements;
use App\Domain\Products\Products;
use App\Domain\Warehouses\Warehouses;
use App\Domain\MovementTypes\MovementTypes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<StockMovements>
 */
class StockMovementsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovements::class);
    }

    public function save(StockMovements $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(StockMovements $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByProduct(Products $product, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('sm')
            ->andWhere('sm.product = :product')
            ->setParameter('product', $product)
            ->orderBy('sm.movementDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByWarehouse(Warehouses $warehouse, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('sm')
            ->andWhere('sm.fromWarehouse = :warehouse OR sm.toWarehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('sm.movementDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByMovementType(MovementTypes $movementType, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('sm')
            ->andWhere('sm.movementType = :movementType')
            ->setParameter('movementType', $movementType)
            ->orderBy('sm.movementDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByReference(string $referenceType, string $referenceId): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.referenceType = :referenceType')
            ->andWhere('sm.referenceId = :referenceId')
            ->setParameter('referenceType', $referenceType)
            ->setParameter('referenceId', $referenceId)
            ->orderBy('sm.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('sm')
            ->andWhere('sm.movementDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('sm.movementDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function getProductMovementSummary(Products $product, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $queryBuilder = $this->createQueryBuilder('sm')
            ->select([
                'COUNT(sm.id) as movementCount',
                'SUM(CASE WHEN mt.effect = \'in\' THEN sm.quantity ELSE 0 END) as totalIn',
                'SUM(CASE WHEN mt.effect = \'out\' THEN sm.quantity ELSE 0 END) as totalOut',
                'AVG(sm.unitCost) as averageCost',
                'MIN(sm.movementDate) as firstMovement',
                'MAX(sm.movementDate) as lastMovement',
            ])
            ->join('sm.movementType', 'mt')
            ->andWhere('sm.product = :product')
            ->setParameter('product', $product);

        if ($startDate) {
            $queryBuilder->andWhere('sm.movementDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('sm.movementDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getSingleResult();
    }

    public function getWarehouseMovementSummary(Warehouses $warehouse, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $queryBuilder = $this->createQueryBuilder('sm')
            ->select([
                'COUNT(sm.id) as movementCount',
                'SUM(CASE WHEN mt.effect = \'in\' AND sm.toWarehouse = :warehouse THEN sm.quantity ELSE 0 END) as totalIn',
                'SUM(CASE WHEN mt.effect = \'out\' AND sm.fromWarehouse = :warehouse THEN sm.quantity ELSE 0 END) as totalOut',
                'COUNT(DISTINCT sm.product) as uniqueProducts',
            ])
            ->join('sm.movementType', 'mt')
            ->andWhere('sm.fromWarehouse = :warehouse OR sm.toWarehouse = :warehouse')
            ->setParameter('warehouse', $warehouse);

        if ($startDate) {
            $queryBuilder->andWhere('sm.movementDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('sm.movementDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getSingleResult();
    }

    public function getRecentMovements(int $limit = 50): array
    {
        return $this->createQueryBuilder('sm')
            ->orderBy('sm.movementDate', 'DESC')
            ->addOrderBy('sm.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function paginate(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $queryBuilder = $this->createQueryBuilder('sm')
            ->orderBy('sm.movementDate', 'DESC')
            ->addOrderBy('sm.createdAt', 'DESC');

        // Appliquer les filtres
        if (!empty($filters['productId'])) {
            $queryBuilder->andWhere('sm.product = :productId')
                ->setParameter('productId', $filters['productId']);
        }

        if (!empty($filters['warehouseId'])) {
            $queryBuilder->andWhere('sm.fromWarehouse = :warehouseId OR sm.toWarehouse = :warehouseId')
                ->setParameter('warehouseId', $filters['warehouseId']);
        }

        if (!empty($filters['movementTypeId'])) {
            $queryBuilder->andWhere('sm.movementType = :movementTypeId')
                ->setParameter('movementTypeId', $filters['movementTypeId']);
        }

        if (!empty($filters['movementEffect'])) {
            $queryBuilder->join('sm.movementType', 'mt')
                ->andWhere('mt.effect = :effect')
                ->setParameter('effect', $filters['movementEffect']);
        }

        if (!empty($filters['startDate'])) {
            $queryBuilder->andWhere('sm.movementDate >= :startDate')
                ->setParameter('startDate', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $queryBuilder->andWhere('sm.movementDate <= :endDate')
                ->setParameter('endDate', $filters['endDate']);
        }

        if (!empty($filters['referenceType'])) {
            $queryBuilder->andWhere('sm.referenceType = :referenceType')
                ->setParameter('referenceType', $filters['referenceType']);
        }

        if (!empty($filters['referenceId'])) {
            $queryBuilder->andWhere('sm.referenceId = :referenceId')
                ->setParameter('referenceId', $filters['referenceId']);
        }

        if (!empty($filters['userId'])) {
            $queryBuilder->andWhere('sm.user = :userId')
                ->setParameter('userId', $filters['userId']);
        }

        if (!empty($filters['search'])) {
            $queryBuilder->join('sm.product', 'p')
                ->andWhere('p.sku LIKE :search OR p.name LIKE :search OR sm.notes LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Compter le total
        $countQuery = clone $queryBuilder;
        $total = $countQuery->select('COUNT(sm.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Pagination
        $results = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $results,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    public function getProductQuantityByDate(Products $product, \DateTimeInterface $date, Warehouses $warehouse = null): string
    {
        // Calculer le stock jusqu'à une date donnée
        $queryBuilder = $this->createQueryBuilder('sm')
            ->select([
                'SUM(CASE WHEN mt.effect = \'in\' AND (:warehouse IS NULL OR sm.toWarehouse = :warehouse) THEN sm.quantity ELSE 0 END) - ' .
                'SUM(CASE WHEN mt.effect = \'out\' AND (:warehouse IS NULL OR sm.fromWarehouse = :warehouse) THEN sm.quantity ELSE 0 END) as stock'
            ])
            ->join('sm.movementType', 'mt')
            ->andWhere('sm.product = :product')
            ->andWhere('sm.movementDate <= :date')
            ->setParameter('product', $product)
            ->setParameter('date', $date);

        if ($warehouse) {
            $queryBuilder->setParameter('warehouse', $warehouse);
        } else {
            $queryBuilder->setParameter('warehouse', null);
        }

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        return $result ?: '0';
    }

    public function getMovementStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $queryBuilder = $this->createQueryBuilder('sm')
            ->select([
                'mt.code as movementTypeCode',
                'mt.name as movementTypeName',
                'COUNT(sm.id) as count',
                'SUM(sm.quantity) as totalQuantity',
                'AVG(sm.unitCost) as averageCost',
                'SUM(sm.quantity * COALESCE(sm.unitCost, 0)) as totalValue'
            ])
            ->join('sm.movementType', 'mt')
            ->andWhere('sm.movementDate BETWEEN :startDate AND :endDate')
            ->groupBy('mt.id', 'mt.code', 'mt.name')
            ->orderBy('totalQuantity', 'DESC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        return $queryBuilder->getQuery()->getResult();
    }

    private function applyOptions(QueryBuilder $queryBuilder, array $options): void
    {
        if (!empty($options['limit'])) {
            $queryBuilder->setMaxResults($options['limit']);
        }

        if (!empty($options['offset'])) {
            $queryBuilder->setFirstResult($options['offset']);
        }

        if (!empty($options['orderBy'])) {
            foreach ($options['orderBy'] as $field => $direction) {
                $queryBuilder->addOrderBy('sm.' . $field, $direction);
            }
        }

        if (!empty($options['startDate'])) {
            $queryBuilder->andWhere('sm.movementDate >= :startDate')
                ->setParameter('startDate', $options['startDate']);
        }

        if (!empty($options['endDate'])) {
            $queryBuilder->andWhere('sm.movementDate <= :endDate')
                ->setParameter('endDate', $options['endDate']);
        }

        if (!empty($options['movementEffect'])) {
            $queryBuilder->join('sm.movementType', 'mt')
                ->andWhere('mt.effect = :effect')
                ->setParameter('effect', $options['movementEffect']);
        }
    }
}
