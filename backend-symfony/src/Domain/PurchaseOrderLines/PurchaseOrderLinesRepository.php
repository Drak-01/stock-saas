<?php

namespace App\Repository\PurchaseOrderLines;

use App\Domain\PurchaseOrderLines\PurchaseOrderLines;
use App\Domain\Products\Products;
use App\Domain\PurchaseOrders\PurchaseOrders;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseOrderLines>
 */
class PurchaseOrderLinesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseOrderLines::class);
    }

    public function save(PurchaseOrderLines $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PurchaseOrderLines $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByPurchaseOrder(PurchaseOrders $purchaseOrder): array
    {
        return $this->createQueryBuilder('pol')
            ->andWhere('pol.purchaseOrder = :purchaseOrder')
            ->andWhere('pol.deletedAt IS NULL')
            ->setParameter('purchaseOrder', $purchaseOrder)
            ->orderBy('pol.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByProduct(Products $product, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('pol')
            ->join('pol.purchaseOrder', 'po')
            ->andWhere('pol.product = :product')
            ->andWhere('pol.deletedAt IS NULL')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('product', $product)
            ->orderBy('po.orderDate', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findOpenLinesByProduct(Products $product): array
    {
        return $this->createQueryBuilder('pol')
            ->join('pol.purchaseOrder', 'po')
            ->andWhere('pol.product = :product')
            ->andWhere('pol.deletedAt IS NULL')
            ->andWhere('po.deletedAt IS NULL')
            ->andWhere('pol.quantityReceived < pol.quantityOrdered')
            ->andWhere('po.status IN (:statuses)')
            ->setParameter('product', $product)
            ->setParameter('statuses', ['ordered', 'partially_received'])
            ->orderBy('po.expectedDeliveryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalOrderedByProduct(Products $product, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): string
    {
        $queryBuilder = $this->createQueryBuilder('pol')
            ->select('SUM(pol.quantityOrdered)')
            ->join('pol.purchaseOrder', 'po')
            ->andWhere('pol.product = :product')
            ->andWhere('pol.deletedAt IS NULL')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('product', $product);

        if ($startDate) {
            $queryBuilder->andWhere('po.orderDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('po.orderDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        return $result ?: '0';
    }

    public function getTotalReceivedByProduct(Products $product, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): string
    {
        $queryBuilder = $this->createQueryBuilder('pol')
            ->select('SUM(pol.quantityReceived)')
            ->join('pol.purchaseOrder', 'po')
            ->andWhere('pol.product = :product')
            ->andWhere('pol.deletedAt IS NULL')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('product', $product);

        if ($startDate) {
            $queryBuilder->andWhere('pol.receivedDate >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('pol.receivedDate <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        return $result ?: '0';
    }

    public function getOpenQuantityByProduct(Products $product): string
    {
        $queryBuilder = $this->createQueryBuilder('pol')
            ->select('SUM(pol.quantityOrdered - pol.quantityReceived)')
            ->join('pol.purchaseOrder', 'po')
            ->andWhere('pol.product = :product')
            ->andWhere('pol.deletedAt IS NULL')
            ->andWhere('po.deletedAt IS NULL')
            ->andWhere('pol.quantityReceived < pol.quantityOrdered')
            ->andWhere('po.status IN (:statuses)')
            ->setParameter('product', $product)
            ->setParameter('statuses', ['ordered', 'partially_received']);

        $result = $queryBuilder->getQuery()->getSingleScalarResult();
        return $result ?: '0';
    }

    public function getProductPurchaseHistory(Products $product, int $limit = 10): array
    {
        return $this->createQueryBuilder('pol')
            ->join('pol.purchaseOrder', 'po')
            ->join('po.supplier', 's')
            ->select([
                'pol.id',
                'pol.uuid',
                'pol.quantityOrdered',
                'pol.quantityReceived',
                'pol.unitPrice',
                'pol.receivedDate',
                'po.poNumber',
                'po.orderDate',
                's.name as supplierName',
                's.code as supplierCode',
            ])
            ->andWhere('pol.product = :product')
            ->andWhere('pol.deletedAt IS NULL')
            ->andWhere('po.deletedAt IS NULL')
            ->setParameter('product', $product)
            ->orderBy('po.orderDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLineStatistics(PurchaseOrders $purchaseOrder): array
    {
        return $this->createQueryBuilder('pol')
            ->select([
                'COUNT(pol.id) as totalLines',
                'SUM(pol.quantityOrdered) as totalOrdered',
                'SUM(pol.quantityReceived) as totalReceived',
                'SUM(CASE WHEN pol.quantityReceived = 0 THEN 1 ELSE 0 END) as notReceivedCount',
                'SUM(CASE WHEN pol.quantityReceived > 0 AND pol.quantityReceived < pol.quantityOrdered THEN 1 ELSE 0 END) as partiallyReceivedCount',
                'SUM(CASE WHEN pol.quantityReceived >= pol.quantityOrdered THEN 1 ELSE 0 END) as fullyReceivedCount',
            ])
            ->andWhere('pol.purchaseOrder = :purchaseOrder')
            ->andWhere('pol.deletedAt IS NULL')
            ->setParameter('purchaseOrder', $purchaseOrder)
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

        if (!empty($options['status'])) {
            $queryBuilder->andWhere('po.status = :status')
                ->setParameter('status', $options['status']);
        }

        if (!empty($options['startDate'])) {
            $queryBuilder->andWhere('po.orderDate >= :startDate')
                ->setParameter('startDate', $options['startDate']);
        }

        if (!empty($options['endDate'])) {
            $queryBuilder->andWhere('po.orderDate <= :endDate')
                ->setParameter('endDate', $options['endDate']);
        }
    }
}
