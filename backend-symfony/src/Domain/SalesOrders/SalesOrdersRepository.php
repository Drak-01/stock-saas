<?php

namespace App\Domain\SalesOrders;

use App\Domain\Customers\Customers;
use App\Domain\TenantUsers\TenantUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalesOrders>
 */
class SalesOrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesOrders::class);
    }

    public function save(SalesOrders $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SalesOrders $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function softDelete(SalesOrders $entity, bool $flush = true): void
    {
        $entity->setDeletedAt(new \DateTime());
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySoNumber(string $soNumber): ?SalesOrders
    {
        return $this->createQueryBuilder('so')
            ->andWhere('so.soNumber = :soNumber')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('soNumber', $soNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCustomer(Customers $customer, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('so')
            ->andWhere('so.customer = :customer')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('customer', $customer)
            ->orderBy('so.orderDate', 'DESC')
            ->addOrderBy('so.createdAt', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByStatus(string $status, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('so')
            ->andWhere('so.status = :status')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('status', $status)
            ->orderBy('so.orderDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByCreatedBy(TenantUsers $user, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('so')
            ->andWhere('so.createdBy = :user')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('so.orderDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findFulfillableOrders(): array
    {
        return $this->createQueryBuilder('so')
            ->andWhere('so.status IN (:statuses)')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('statuses', ['confirmed', 'reserved', 'partially_fulfilled'])
            ->orderBy('so.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOverdueOrders(): array
    {
        return $this->createQueryBuilder('so')
            ->andWhere('so.dueDate < :today')
            ->andWhere('so.status IN (:statuses)')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('today', new \DateTime())
            ->setParameter('statuses', ['confirmed', 'reserved', 'partially_fulfilled'])
            ->orderBy('so.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDraft(): array
    {
        return $this->findByStatus('draft');
    }

    public function findConfirmed(): array
    {
        return $this->findByStatus('confirmed');
    }

    public function findFulfilled(): array
    {
        return $this->findByStatus('fulfilled');
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('so')
            ->andWhere('so.status NOT IN (:statuses)')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('statuses', ['cancelled', 'closed'])
            ->orderBy('so.orderDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $searchTerm, int $limit = 20): array
    {
        return $this->createQueryBuilder('so')
            ->leftJoin('so.customer', 'c')
            ->andWhere('so.deletedAt IS NULL')
            ->andWhere('so.soNumber LIKE :search OR c.name LIKE :search OR c.code LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('so.orderDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function paginate(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $queryBuilder = $this->createQueryBuilder('so')
            ->andWhere('so.deletedAt IS NULL')
            ->orderBy('so.orderDate', 'DESC')
            ->addOrderBy('so.createdAt', 'DESC');

        // Appliquer les filtres
        if (!empty($filters['customerId'])) {
            $queryBuilder->andWhere('so.customer = :customerId')
                ->setParameter('customerId', $filters['customerId']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $queryBuilder->andWhere('so.status IN (:status)')
                    ->setParameter('status', $filters['status']);
            } else {
                $queryBuilder->andWhere('so.status = :status')
                    ->setParameter('status', $filters['status']);
            }
        }

        if (!empty($filters['createdById'])) {
            $queryBuilder->andWhere('so.createdBy = :createdById')
                ->setParameter('createdById', $filters['createdById']);
        }

        if (!empty($filters['startDate'])) {
            $queryBuilder->andWhere('so.orderDate >= :startDate')
                ->setParameter('startDate', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $queryBuilder->andWhere('so.orderDate <= :endDate')
                ->setParameter('endDate', $filters['endDate']);
        }

        if (!empty($filters['dueStartDate'])) {
            $queryBuilder->andWhere('so.dueDate >= :dueStartDate')
                ->setParameter('dueStartDate', $filters['dueStartDate']);
        }

        if (!empty($filters['dueEndDate'])) {
            $queryBuilder->andWhere('so.dueDate <= :dueEndDate')
                ->setParameter('dueEndDate', $filters['dueEndDate']);
        }

        if (!empty($filters['search'])) {
            $queryBuilder->leftJoin('so.customer', 'c')
                ->andWhere('so.soNumber LIKE :search OR c.name LIKE :search OR c.code LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['minAmount'])) {
            $queryBuilder->andWhere('so.totalAmount >= :minAmount')
                ->setParameter('minAmount', $filters['minAmount']);
        }

        if (!empty($filters['maxAmount'])) {
            $queryBuilder->andWhere('so.totalAmount <= :maxAmount')
                ->setParameter('maxAmount', $filters['maxAmount']);
        }

        // Compter le total
        $countQuery = clone $queryBuilder;
        $total = $countQuery->select('COUNT(so.id)')
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
        $queryBuilder = $this->createQueryBuilder('so')
            ->select([
                'COUNT(so.id) as totalOrders',
                'SUM(so.totalAmount) as totalAmount',
                'AVG(so.totalAmount) as averageOrderValue',
                'SUM(CASE WHEN so.status = \'draft\' THEN 1 ELSE 0 END) as draftCount',
                'SUM(CASE WHEN so.status = \'confirmed\' THEN 1 ELSE 0 END) as confirmedCount',
                'SUM(CASE WHEN so.status = \'reserved\' THEN 1 ELSE 0 END) as reservedCount',
                'SUM(CASE WHEN so.status = \'partially_fulfilled\' THEN 1 ELSE 0 END) as partiallyFulfilledCount',
                'SUM(CASE WHEN so.status = \'fulfilled\' THEN 1 ELSE 0 END) as fulfilledCount',
                'SUM(CASE WHEN so.status = \'shipped\' THEN 1 ELSE 0 END) as shippedCount',
                'SUM(CASE WHEN so.status = \'delivered\' THEN 1 ELSE 0 END) as deliveredCount',
                'SUM(CASE WHEN so.status = \'invoiced\' THEN 1 ELSE 0 END) as invoicedCount',
                'SUM(CASE WHEN so.status = \'cancelled\' THEN 1 ELSE 0 END) as cancelledCount',
            ])
            ->andWhere('so.deletedAt IS NULL');

        if ($startDate) {
            $queryBuilder->andWhere('so.orderDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('so.orderDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getSingleResult();
    }

    public function getMonthlyStatistics(int $year): array
    {
        $queryBuilder = $this->createQueryBuilder('so')
            ->select([
                'MONTH(so.orderDate) as month',
                'COUNT(so.id) as orderCount',
                'SUM(so.totalAmount) as totalAmount',
                'SUM(CASE WHEN so.status = \'fulfilled\' THEN 1 ELSE 0 END) as fulfilledCount',
                'SUM(CASE WHEN so.status = \'delivered\' THEN 1 ELSE 0 END) as deliveredCount',
            ])
            ->andWhere('YEAR(so.orderDate) = :year')
            ->andWhere('so.deletedAt IS NULL')
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
                'fulfilledCount' => 0,
                'deliveredCount' => 0,
            ];
        }

        foreach ($results as $result) {
            $month = (int) $result['month'];
            $monthlyData[$month] = [
                'month' => $month,
                'orderCount' => (int) $result['orderCount'],
                'totalAmount' => $result['totalAmount'] ?? '0',
                'fulfilledCount' => (int) $result['fulfilledCount'],
                'deliveredCount' => (int) $result['deliveredCount'],
            ];
        }

        return array_values($monthlyData);
    }

    public function getCustomerStatistics(Customers $customer): array
    {
        return $this->createQueryBuilder('so')
            ->select([
                'COUNT(so.id) as totalOrders',
                'SUM(so.totalAmount) as totalAmount',
                'AVG(so.totalAmount) as averageOrderValue',
                'MIN(so.orderDate) as firstOrderDate',
                'MAX(so.orderDate) as lastOrderDate',
                'SUM(CASE WHEN so.status = \'delivered\' THEN 1 ELSE 0 END) as completedOrders',
                'SUM(CASE WHEN so.status IN (\'confirmed\', \'reserved\', \'partially_fulfilled\', \'fulfilled\', \'shipped\') THEN 1 ELSE 0 END) as pendingOrders',
            ])
            ->andWhere('so.customer = :customer')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleResult();
    }

    public function getTopCustomers(int $limit = 10, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $queryBuilder = $this->createQueryBuilder('so')
            ->select([
                'c.id as customerId',
                'c.name as customerName',
                'c.code as customerCode',
                'COUNT(so.id) as orderCount',
                'SUM(so.totalAmount) as totalAmount',
            ])
            ->join('so.customer', 'c')
            ->andWhere('so.deletedAt IS NULL')
            ->andWhere('so.status != :cancelled')
            ->setParameter('cancelled', 'cancelled')
            ->groupBy('c.id', 'c.name', 'c.code')
            ->orderBy('totalAmount', 'DESC')
            ->setMaxResults($limit);

        if ($startDate) {
            $queryBuilder->andWhere('so.orderDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('so.orderDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getResult();
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
            $queryBuilder->andWhere('so.orderDate >= :startDate')
                ->setParameter('startDate', $options['startDate']);
        }

        if (!empty($options['endDate'])) {
            $queryBuilder->andWhere('so.orderDate <= :endDate')
                ->setParameter('endDate', $options['endDate']);
        }

        if (!empty($options['orderBy'])) {
            foreach ($options['orderBy'] as $field => $direction) {
                $queryBuilder->addOrderBy('so.' . $field, $direction);
            }
        }
    }
}
