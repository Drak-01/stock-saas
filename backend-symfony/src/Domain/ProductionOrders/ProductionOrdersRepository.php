<?php

namespace App\Domain\ProductionOrders;

use App\Domain\BillsOfMaterial\BillsOfMaterial;
use App\Domain\Warehouses\Warehouses;
use App\Domain\TenantUsers\TenantUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductionOrders>
 */
class ProductionOrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductionOrders::class);
    }

    public function save(ProductionOrders $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductionOrders $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function softDelete(ProductionOrders $entity, bool $flush = true): void
    {
        $entity->setDeletedAt(new \DateTime());
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByPoNumber(string $poNumber): ?ProductionOrders
    {
        return $this->createQueryBuilder('po')
            ->andWhere('po.poNumber = :poNumber')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('poNumber', $poNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByBom(BillsOfMaterial $bom, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.bom = :bom')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('bom', $bom)
            ->orderBy('po.createdAt', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByStatus(string $status, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.status = :status')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('status', $status)
            ->orderBy('po.startDate', 'ASC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByWarehouse(Warehouses $warehouse, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.sourceWarehouse = :warehouse OR po.destinationWarehouse = :warehouse')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('po.createdAt', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByCreatedBy(TenantUsers $user, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.createdBy = :user')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('po.createdAt', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('po')
            ->andWhere('po.status NOT IN (:statuses)')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('statuses', ['cancelled', 'closed', 'completed'])
            ->orderBy('po.startDate', 'ASC')
            ->addOrderBy('po.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findInProgress(): array
    {
        return $this->findByStatus('in_progress');
    }

    public function findPlanned(): array
    {
        return $this->findByStatus('planned');
    }

    public function findCompleted(): array
    {
        return $this->findByStatus('completed');
    }

    public function findOverdue(): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.startDate < :today')
            ->andWhere('po.status IN (:statuses)')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('today', new \DateTime())
            ->setParameter('statuses', ['planned', 'reserved'])
            ->orderBy('po.startDate', 'ASC');

        return $queryBuilder->getQuery()->getResult();
    }

    public function findDelayed(): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.completionDate < :today')
            ->andWhere('po.status IN (:statuses)')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('today', new \DateTime())
            ->setParameter('statuses', ['in_progress', 'partially_completed'])
            ->orderBy('po.completionDate', 'ASC');

        return $queryBuilder->getQuery()->getResult();
    }

    public function search(string $searchTerm, int $limit = 20): array
    {
        return $this->createQueryBuilder('po')
            ->leftJoin('po.bom', 'bom')
            ->leftJoin('bom.finishedProduct', 'p')
            ->andWhere('po.deletedAt IS NULL')
            ->andWhere('po.poNumber LIKE :search OR p.name LIKE :search OR p.sku LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('po.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function paginate(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.deletedAt IS NULL')
            ->orderBy('po.createdAt', 'DESC');

        // Appliquer les filtres
        if (!empty($filters['bomId'])) {
            $queryBuilder->andWhere('po.bom = :bomId')
                ->setParameter('bomId', $filters['bomId']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $queryBuilder->andWhere('po.status IN (:status)')
                    ->setParameter('status', $filters['status']);
            } else {
                $queryBuilder->andWhere('po.status = :status')
                    ->setParameter('status', $filters['status']);
            }
        }

        if (!empty($filters['createdById'])) {
            $queryBuilder->andWhere('po.createdBy = :createdById')
                ->setParameter('createdById', $filters['createdById']);
        }

        if (!empty($filters['warehouseId'])) {
            $queryBuilder->andWhere('po.sourceWarehouse = :warehouseId OR po.destinationWarehouse = :warehouseId')
                ->setParameter('warehouseId', $filters['warehouseId']);
        }

        if (!empty($filters['startDate'])) {
            $queryBuilder->andWhere('po.startDate >= :startDate')
                ->setParameter('startDate', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $queryBuilder->andWhere('po.startDate <= :endDate')
                ->setParameter('endDate', $filters['endDate']);
        }

        if (!empty($filters['completionStartDate'])) {
            $queryBuilder->andWhere('po.completionDate >= :completionStartDate')
                ->setParameter('completionStartDate', $filters['completionStartDate']);
        }

        if (!empty($filters['completionEndDate'])) {
            $queryBuilder->andWhere('po.completionDate <= :completionEndDate')
                ->setParameter('completionEndDate', $filters['completionEndDate']);
        }

        if (!empty($filters['search'])) {
            $queryBuilder->leftJoin('po.bom', 'bom')
                ->leftJoin('bom.finishedProduct', 'p')
                ->andWhere('po.poNumber LIKE :search OR p.name LIKE :search OR p.sku LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (isset($filters['minQuantity'])) {
            $queryBuilder->andWhere('po.quantityToProduce >= :minQuantity')
                ->setParameter('minQuantity', $filters['minQuantity']);
        }

        if (isset($filters['maxQuantity'])) {
            $queryBuilder->andWhere('po.quantityToProduce <= :maxQuantity')
                ->setParameter('maxQuantity', $filters['maxQuantity']);
        }

        // Compter le total
        $countQuery = clone $queryBuilder;
        $total = $countQuery->select('COUNT(po.id)')
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

    public function getStatistics(\DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->select([
                'COUNT(po.id) as totalOrders',
                'SUM(po.quantityToProduce) as totalToProduce',
                'SUM(po.quantityProduced) as totalProduced',
                'SUM(CASE WHEN po.status = \'planned\' THEN 1 ELSE 0 END) as plannedCount',
                'SUM(CASE WHEN po.status = \'reserved\' THEN 1 ELSE 0 END) as reservedCount',
                'SUM(CASE WHEN po.status = \'in_progress\' THEN 1 ELSE 0 END) as inProgressCount',
                'SUM(CASE WHEN po.status = \'partially_completed\' THEN 1 ELSE 0 END) as partiallyCompletedCount',
                'SUM(CASE WHEN po.status = \'completed\' THEN 1 ELSE 0 END) as completedCount',
                'SUM(CASE WHEN po.status = \'cancelled\' THEN 1 ELSE 0 END) as cancelledCount',
                'SUM(CASE WHEN po.status = \'closed\' THEN 1 ELSE 0 END) as closedCount',
                'AVG(po.quantityToProduce) as averageQuantity',
            ])
            ->andWhere('po.deletedAt IS NULL');

        if ($startDate) {
            $queryBuilder->andWhere('po.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('po.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getSingleResult();
    }

    public function getMonthlyStatistics(int $year): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->select([
                'MONTH(po.createdAt) as month',
                'COUNT(po.id) as orderCount',
                'SUM(po.quantityToProduce) as totalToProduce',
                'SUM(po.quantityProduced) as totalProduced',
                'SUM(CASE WHEN po.status = \'completed\' THEN 1 ELSE 0 END) as completedCount',
            ])
            ->andWhere('YEAR(po.createdAt) = :year')
            ->andWhere('po.deletedAt IS NULL')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->setParameter('year', $year);

        $results = $queryBuilder->getQuery()->getResult();

        // Formater les résultats pour avoir tous les mois
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = [
                'month' => $month,
                'orderCount' => 0,
                'totalToProduce' => '0',
                'totalProduced' => '0',
                'completedCount' => 0,
            ];
        }

        foreach ($results as $result) {
            $month = (int) $result['month'];
            $monthlyData[$month] = [
                'month' => $month,
                'orderCount' => (int) $result['orderCount'],
                'totalToProduce' => $result['totalToProduce'] ?? '0',
                'totalProduced' => $result['totalProduced'] ?? '0',
                'completedCount' => (int) $result['completedCount'],
            ];
        }

        return array_values($monthlyData);
    }

    public function getBomStatistics(BillsOfMaterial $bom): array
    {
        return $this->createQueryBuilder('po')
            ->select([
                'COUNT(po.id) as totalOrders',
                'SUM(po.quantityToProduce) as totalToProduce',
                'SUM(po.quantityProduced) as totalProduced',
                'MIN(po.createdAt) as firstOrder',
                'MAX(po.createdAt) as lastOrder',
                'SUM(CASE WHEN po.status = \'completed\' THEN 1 ELSE 0 END) as completedCount',
                'AVG(po.quantityToProduce) as averageQuantity',
            ])
            ->andWhere('po.bom = :bom')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('bom', $bom)
            ->getQuery()
            ->getSingleResult();
    }

    public function getProductProductionStatistics(string $productUuid, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->select([
                'COUNT(po.id) as totalOrders',
                'SUM(po.quantityToProduce) as totalToProduce',
                'SUM(po.quantityProduced) as totalProduced',
                'SUM(CASE WHEN po.status = \'completed\' THEN 1 ELSE 0 END) as completedCount',
            ])
            ->join('po.bom', 'bom')
            ->join('bom.finishedProduct', 'p')
            ->andWhere('p.uuid = :productUuid')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('productUuid', $productUuid);

        if ($startDate) {
            $queryBuilder->andWhere('po.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('po.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getSingleResult();
    }

    public function getEfficiencyMetrics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('po')
            ->select([
                'COUNT(po.id) as totalOrders',
                'SUM(po.quantityToProduce) as plannedQuantity',
                'SUM(po.quantityProduced) as actualQuantity',
                'AVG(DATEDIFF(po.completionDate, po.startDate)) as averageDuration',
                'SUM(CASE WHEN po.completionDate <= po.startDate THEN 1 ELSE 0 END) as onTimeCount',
                'SUM(CASE WHEN po.completionDate > po.startDate THEN 1 ELSE 0 END) as delayedCount',
            ])
            ->andWhere('po.status = :completed')
            ->andWhere('po.startDate BETWEEN :startDate AND :endDate')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('completed', 'completed')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleResult();
    }

    private function applyOptions($queryBuilder, array $options): void
    {
        if (!empty($options['limit'])) {
            $queryBuilder->setMaxResults($options['limit']);
        }

        if (!empty($options['offset'])) {
            $queryBuilder->setFirstResult($options['offset']);
        }

        if (!empty($options['startDate'])) {
            $queryBuilder->andWhere('po.startDate >= :startDate')
                ->setParameter('startDate', $options['startDate']);
        }

        if (!empty($options['endDate'])) {
            $queryBuilder->andWhere('po.startDate <= :endDate')
                ->setParameter('endDate', $options['endDate']);
        }

        if (!empty($options['orderBy'])) {
            foreach ($options['orderBy'] as $field => $direction) {
                $queryBuilder->addOrderBy('po.' . $field, $direction);
            }
        }
    }
}
