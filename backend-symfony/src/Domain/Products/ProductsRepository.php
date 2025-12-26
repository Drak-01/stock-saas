<?php

namespace App\Domain\Products;

use App\Domain\Products\Products;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Products>
 */
class ProductsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Products::class);
    }

    public function save(Products $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Products $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySku(string $sku): ?Products
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.sku = :sku')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('sku', strtoupper($sku))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByBarcode(string $barcode): ?Products
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.barcode = :barcode')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('barcode', $barcode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(string $categoryId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :categoryId')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $productType): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.productType = :productType')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->setParameter('productType', $productType)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findProductsBelowReorderPoint(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->andWhere('p.reorderPoint IS NOT NULL')
            ->andWhere('EXISTS (
                SELECT sl FROM App\Domain\StockLocations\StockLocations sl
                WHERE sl.product = p.id
                AND (sl.quantityOnHand - sl.quantityReserved) <= p.reorderPoint
            )')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOutOfStockProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->andWhere('NOT EXISTS (
                SELECT sl FROM App\Domain\StockLocations\StockLocations sl
                WHERE sl.product = p.id
                AND sl.quantityOnHand > 0
            )')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchProducts(string $search): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->andWhere('p.sku LIKE :search OR p.name LIKE :search OR p.barcode LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getProductsWithLowStock(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.sku, p.name, p.reorderPoint,
                     SUM(sl.quantityOnHand - sl.quantityReserved) as availableStock')
            ->leftJoin('p.stockLocations', 'sl')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->andWhere('p.reorderPoint IS NOT NULL')
            ->groupBy('p.id')
            ->having('availableStock <= p.reorderPoint')
            ->orderBy('availableStock', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getBestSellingProducts(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.sku, p.name, SUM(sol.quantityFulfilled) as totalSold')
            ->leftJoin('App\Domain\SalesOrderLines\SalesOrderLines', 'sol', 'WITH', 'sol.product = p.id')
            ->leftJoin('sol.salesOrder', 'so')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->andWhere('so.orderDate BETWEEN :startDate AND :endDate')
            ->andWhere('so.status = :completed')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('completed', 'completed')
            ->groupBy('p.id')
            ->orderBy('totalSold', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    public function getProductsBySupplier(string $supplierId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->andWhere("JSON_EXTRACT(p.attributes, '$.supplier_id') = :supplierId")
            ->setParameter('supplierId', $supplierId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
