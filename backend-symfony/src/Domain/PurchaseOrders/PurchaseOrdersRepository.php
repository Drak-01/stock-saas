<?php

namespace App\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrders;
use App\Domain\Suppliers\Suppliers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseOrders>
 */
class PurchaseOrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseOrders::class);
    }

    public function save(PurchaseOrders $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PurchaseOrders $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function softDelete(PurchaseOrders $entity, bool $flush = true): void
    {
        $entity->setDeletedAt(new \DateTime());
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByPoNumber(string $poNumber): ?PurchaseOrders
    {
        return $this->createQueryBuilder('po')
            ->andWhere('po.poNumber = :poNumber')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('poNumber', $poNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findBySupplier(Suppliers $supplier, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.supplier = :supplier')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('supplier', $supplier)
            ->orderBy('po.orderDate', 'DESC')
            ->addOrderBy('po.createdAt', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByStatus(string $status, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.status = :status')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('status', $status)
            ->orderBy('po.orderDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findReceivableOrders(): array
    {
        return $this->createQueryBuilder('po')
            ->andWhere('po.status IN (:statuses)')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('statuses', ['ordered', 'partially_received'])
            ->orderBy('po.expectedDeliveryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOverdueOrders(): array
    {
        return $this->createQueryBuilder('po')
            ->andWhere('po.expectedDeliveryDate < :today')
            ->andWhere('po.status IN (:statuses)')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('today', new \DateTime())
            ->setParameter('statuses', ['ordered', 'partially_received'])
            ->orderBy('po.expectedDeliveryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingApproval(): array
    {
        return $this->findByStatus('pending_approval');
    }

    public function findApproved(): array
    {
        return $this->findByStatus('approved');
    }

    public function findOrdered(): array
    {
        return $this->findByStatus('ordered');
    }

    public function findReceived(): array
    {
        return $this->findByStatus('received');
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('po')
            ->andWhere('po.status NOT IN (:statuses)')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('statuses', ['cancelled', 'closed'])
            ->orderBy('po.orderDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $searchTerm, int $limit = 20): array
    {
        return $this->createQueryBuilder('po')
            ->leftJoin('po.supplier', 's')
            ->andWhere('po.deletedAt IS NULL')
            ->andWhere('po.poNumber LIKE :search OR s.name LIKE :search OR s.code LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('po.orderDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function paginate(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->andWhere('po.deletedAt IS NULL')
            ->orderBy('po.orderDate', 'DESC')
            ->addOrderBy('po.createdAt', 'DESC');

        // Appliquer les filtres
        if (!empty($filters['supplierId'])) {
            $queryBuilder->andWhere('po.supplier = :supplierId')
                ->setParameter('supplierId', $filters['supplierId']);
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

        if (!empty($filters['startDate'])) {
            $queryBuilder->andWhere('po.orderDate >= :startDate')
                ->setParameter('startDate', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $queryBuilder->andWhere('po.orderDate <= :endDate')
                ->setParameter('endDate', $filters['endDate']);
        }

        if (!empty($filters['expectedStartDate'])) {
            $queryBuilder->andWhere('po.expectedDeliveryDate >= :expectedStartDate')
                ->setParameter('expectedStartDate', $filters['expectedStartDate']);
        }

        if (!empty($filters['expectedEndDate'])) {
            $queryBuilder->andWhere('po.expectedDeliveryDate <= :expectedEndDate')
                ->setParameter('expectedEndDate', $filters['expectedEndDate']);
        }

        if (!empty($filters['search'])) {
            $queryBuilder->leftJoin('po.supplier', 's')
                ->andWhere('po.poNumber LIKE :search OR s.name LIKE :search OR s.code LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['minAmount'])) {
            $queryBuilder->andWhere('po.totalAmount >= :minAmount')
                ->setParameter('minAmount', $filters['minAmount']);
        }

        if (!empty($filters['maxAmount'])) {
            $queryBuilder->andWhere('po.totalAmount <= :maxAmount')
                ->setParameter('maxAmount', $filters['maxAmount']);
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
                'SUM(po.totalAmount) as totalAmount',
                'AVG(po.totalAmount) as averageOrderValue',
                'SUM(CASE WHEN po.status = \'draft\' THEN 1 ELSE 0 END) as draftCount',
                'SUM(CASE WHEN po.status = \'pending_approval\' THEN 1 ELSE 0 END) as pendingApprovalCount',
                'SUM(CASE WHEN po.status = \'approved\' THEN 1 ELSE 0 END) as approvedCount',
                'SUM(CASE WHEN po.status = \'ordered\' THEN 1 ELSE 0 END) as orderedCount',
                'SUM(CASE WHEN po.status = \'partially_received\' THEN 1 ELSE 0 END) as partiallyReceivedCount',
                'SUM(CASE WHEN po.status = \'received\' THEN 1 ELSE 0 END) as receivedCount',
                'SUM(CASE WHEN po.status = \'cancelled\' THEN 1 ELSE 0 END) as cancelledCount',
            ])
            ->andWhere('po.deletedAt IS NULL');

        if ($startDate) {
            $queryBuilder->andWhere('po.orderDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('po.orderDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getSingleResult();
    }

    public function getMonthlyStatistics(int $year): array
    {
        $queryBuilder = $this->createQueryBuilder('po')
            ->select([
                'MONTH(po.orderDate) as month',
                'COUNT(po.id) as orderCount',
                'SUM(po.totalAmount) as totalAmount',
                'SUM(CASE WHEN po.status = \'received\' THEN 1 ELSE 0 END) as receivedCount',
            ])
            ->andWhere('YEAR(po.orderDate) = :year')
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
                'totalAmount' => '0',
                'receivedCount' => 0,
            ];
        }

        foreach ($results as $result) {
            $month = (int) $result['month'];
            $monthlyData[$month] = [
                'month' => $month,
                'orderCount' => (int) $result['orderCount'],
                'totalAmount' => $result['totalAmount'] ?? '0',
                'receivedCount' => (int) $result['receivedCount'],
            ];
        }

        return array_values($monthlyData);
    }

    public function getSupplierStatistics(Suppliers $supplier): array
    {
        return $this->createQueryBuilder('po')
            ->select([
                'COUNT(po.id) as totalOrders',
                'SUM(po.totalAmount) as totalAmount',
                'AVG(po.totalAmount) as averageOrderValue',
                'MIN(po.orderDate) as firstOrderDate',
                'MAX(po.orderDate) as lastOrderDate',
                'SUM(CASE WHEN po.status = \'received\' THEN 1 ELSE 0 END) as completedOrders',
                'SUM(CASE WHEN po.status IN (\'ordered\', \'partially_received\') THEN 1 ELSE 0 END) as pendingOrders',
            ])
            ->andWhere('po.supplier = :supplier')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('supplier', $supplier)
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
            $queryBuilder->andWhere('po.orderDate >= :startDate')
                ->setParameter('startDate', $options['startDate']);
        }

        if (!empty($options['endDate'])) {
            $queryBuilder->andWhere('po.orderDate <= :endDate')
                ->setParameter('endDate', $options['endDate']);
        }

        if (!empty($options['orderBy'])) {
            foreach ($options['orderBy'] as $field => $direction) {
                $queryBuilder->addOrderBy('po.' . $field, $direction);
            }
        }
    }
}
