<?php

namespace App\Domain\SalesOrderLines;

use App\Domain\Products\Products;
use App\Domain\SalesOrders\SalesOrders;
use App\Domain\Warehouses\Warehouses;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalesOrderLines>
 */
class SalesOrderLinesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesOrderLines::class);
    }

    public function save(SalesOrderLines $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SalesOrderLines $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySalesOrder(SalesOrders $salesOrder): array
    {
        return $this->createQueryBuilder('sol')
            ->andWhere('sol.salesOrder = :salesOrder')
            ->andWhere('sol.deletedAt IS NULL')
            ->setParameter('salesOrder', $salesOrder)
            ->orderBy('sol.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByProduct(Products $product, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('sol')
            ->join('sol.salesOrder', 'so')
            ->andWhere('sol.product = :product')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('product', $product)
            ->orderBy('so.orderDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findOpenLinesByProduct(Products $product): array
    {
        return $this->createQueryBuilder('sol')
            ->join('sol.salesOrder', 'so')
            ->andWhere('sol.product = :product')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->andWhere('sol.quantityFulfilled < sol.quantityOrdered')
            ->andWhere('so.status IN (:statuses)')
            ->setParameter('product', $product)
            ->setParameter('statuses', ['confirmed', 'reserved', 'partially_fulfilled'])
            ->orderBy('so.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findReservedLinesByWarehouse(Warehouses $warehouse): array
    {
        return $this->createQueryBuilder('sol')
            ->join('sol.salesOrder', 'so')
            ->andWhere('sol.warehouse = :warehouse')
            ->andWhere('sol.reserved = :reserved')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->andWhere('sol.quantityFulfilled < sol.quantityOrdered')
            ->andWhere('so.status IN (:statuses)')
            ->setParameter('warehouse', $warehouse)
            ->setParameter('reserved', true)
            ->setParameter('statuses', ['confirmed', 'reserved', 'partially_fulfilled'])
            ->orderBy('so.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalOrderedByProduct(Products $product, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): string
    {
        $queryBuilder = $this->createQueryBuilder('sol')
            ->select('SUM(sol.quantityOrdered)')
            ->join('sol.salesOrder', 'so')
            ->andWhere('sol.product = :product')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('product', $product);

        if ($startDate) {
            $queryBuilder->andWhere('so.orderDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('so.orderDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        return $result ?: '0';
    }

    public function getTotalFulfilledByProduct(Products $product, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): string
    {
        $queryBuilder = $this->createQueryBuilder('sol')
            ->select('SUM(sol.quantityFulfilled)')
            ->join('sol.salesOrder', 'so')
            ->andWhere('sol.product = :product')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('product', $product);

        if ($startDate) {
            $queryBuilder->andWhere('so.orderDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('so.orderDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        return $result ?: '0';
    }

    public function getOpenQuantityByProduct(Products $product): string
    {
        $queryBuilder = $this->createQueryBuilder('sol')
            ->select('SUM(sol.quantityOrdered - sol.quantityFulfilled)')
            ->join('sol.salesOrder', 'so')
            ->andWhere('sol.product = :product')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->andWhere('sol.quantityFulfilled < sol.quantityOrdered')
            ->andWhere('so.status IN (:statuses)')
            ->setParameter('product', $product)
            ->setParameter('statuses', ['confirmed', 'reserved', 'partially_fulfilled']);

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        return $result ?: '0';
    }

    public function getReservedQuantityByProductAndWarehouse(Products $product, Warehouses $warehouse): string
    {
        $queryBuilder = $this->createQueryBuilder('sol')
            ->select('SUM(sol.quantityOrdered - sol.quantityFulfilled)')
            ->join('sol.salesOrder', 'so')
            ->andWhere('sol.product = :product')
            ->andWhere('sol.warehouse = :warehouse')
            ->andWhere('sol.reserved = :reserved')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->andWhere('sol.quantityFulfilled < sol.quantityOrdered')
            ->andWhere('so.status IN (:statuses)')
            ->setParameter('product', $product)
            ->setParameter('warehouse', $warehouse)
            ->setParameter('reserved', true)
            ->setParameter('statuses', ['confirmed', 'reserved', 'partially_fulfilled']);

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        return $result ?: '0';
    }

    public function getProductSalesHistory(Products $product, int $limit = 10): array
    {
        return $this->createQueryBuilder('sol')
            ->join('sol.salesOrder', 'so')
            ->join('so.customer', 'c')
            ->select([
                'sol.id',
                'sol.uuid',
                'sol.quantityOrdered',
                'sol.quantityFulfilled',
                'sol.unitPrice',
                'so.soNumber',
                'so.orderDate',
                'so.status',
                'c.name as customerName',
                'c.code as customerCode',
            ])
            ->andWhere('sol.product = :product')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->setParameter('product', $product)
            ->orderBy('so.orderDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLineStatistics(SalesOrders $salesOrder): array
    {
        return $this->createQueryBuilder('sol')
            ->select([
                'COUNT(sol.id) as totalLines',
                'SUM(sol.quantityOrdered) as totalOrdered',
                'SUM(sol.quantityFulfilled) as totalFulfilled',
                'SUM(CASE WHEN sol.quantityFulfilled = 0 THEN 1 ELSE 0 END) as notFulfilledCount',
                'SUM(CASE WHEN sol.quantityFulfilled > 0 AND sol.quantityFulfilled < sol.quantityOrdered THEN 1 ELSE 0 END) as partiallyFulfilledCount',
                'SUM(CASE WHEN sol.quantityFulfilled >= sol.quantityOrdered THEN 1 ELSE 0 END) as fullyFulfilledCount',
                'SUM(CASE WHEN sol.reserved = true THEN 1 ELSE 0 END) as reservedCount',
            ])
            ->andWhere('sol.salesOrder = :salesOrder')
            ->andWhere('sol.deletedAt IS NULL')
            ->setParameter('salesOrder', $salesOrder)
            ->getQuery()
            ->getSingleResult();
    }

    public function getBestSellingProducts(int $limit = 10, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $queryBuilder = $this->createQueryBuilder('sol')
            ->select([
                'p.id as productId',
                'p.sku as productSku',
                'p.name as productName',
                'COUNT(sol.id) as orderCount',
                'SUM(sol.quantityOrdered) as totalQuantity',
                'SUM(sol.quantityOrdered * sol.unitPrice) as totalRevenue',
            ])
            ->join('sol.product', 'p')
            ->join('sol.salesOrder', 'so')
            ->andWhere('sol.deletedAt IS NULL')
            ->andWhere('so.deletedAt IS NULL')
            ->andWhere('so.status != :cancelled')
            ->setParameter('cancelled', 'cancelled')
            ->groupBy('p.id', 'p.sku', 'p.name')
            ->orderBy('totalQuantity', 'DESC')
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

        if (!empty($options['status'])) {
            $queryBuilder->andWhere('so.status = :status')
                ->setParameter('status', $options['status']);
        }

        if (!empty($options['warehouseId'])) {
            $queryBuilder->andWhere('sol.warehouse = :warehouseId')
                ->setParameter('warehouseId', $options['warehouseId']);
        }

        if (!empty($options['startDate'])) {
            $queryBuilder->andWhere('so.orderDate >= :startDate')
                ->setParameter('startDate', $options['startDate']);
        }

        if (!empty($options['endDate'])) {
            $queryBuilder->andWhere('so.orderDate <= :endDate')
                ->setParameter('endDate', $options['endDate']);
        }
    }
}
