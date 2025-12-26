<?php

namespace App\Domain\Suppliers;

use App\Domain\Suppliers\Suppliers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Suppliers>
 */
class SuppliersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Suppliers::class);
    }

    public function save(Suppliers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Suppliers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCode(string $code): ?Suppliers
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.code = :code')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveSuppliers(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = true')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = true')
            ->andWhere("JSON_EXTRACT(s.attributes, '$.category') = :category")
            ->setParameter('category', $category)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchSuppliers(string $search): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = true')
            ->andWhere('s.code LIKE :search OR s.name LIKE :search OR s.email LIKE :search OR s.contactPerson LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPreferredSuppliers(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = true')
            ->andWhere("JSON_EXTRACT(s.attributes, '$.is_preferred') = true")
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findSuppliersWithExpiringContracts(int $daysBefore = 30): array
    {
        $dateThreshold = (new \DateTime())->modify("+{$daysBefore} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = true')
            ->andWhere("JSON_EXTRACT(s.attributes, '$.contract_expiry') IS NOT NULL")
            ->andWhere("JSON_EXTRACT(s.attributes, '$.contract_expiry') <= :threshold")
            ->setParameter('threshold', $dateThreshold->format('Y-m-d'))
            ->orderBy("JSON_EXTRACT(s.attributes, '$.contract_expiry')", 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTopSuppliersBySpend(int $limit = 10, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.id, s.code, s.name, SUM(po.totalAmount) as total_spend')
            ->join('s.purchaseOrders', 'po')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = true')
            ->andWhere('po.status = :completed')
            ->setParameter('completed', 'completed')
            ->groupBy('s.id, s.code, s.name')
            ->orderBy('total_spend', 'DESC')
            ->setMaxResults($limit);

        if ($startDate !== null && $endDate !== null) {
            $qb->andWhere('po.orderDate BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }

    public function generateNextCode(string $prefix = 'SUP'): string
    {
        $lastSupplier = $this->createQueryBuilder('s')
            ->select('s.code')
            ->andWhere('s.code LIKE :prefix')
            ->setParameter('prefix', $prefix . '-%')
            ->orderBy('s.code', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastSupplier) {
            $lastCode = $lastSupplier['code'];
            $lastNumber = (int) substr($lastCode, strlen($prefix) + 1);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return $prefix . '-' . $nextNumber;
    }

    public function getSupplierStatistics(): array
    {
        return [
            'total_suppliers' => $this->count(['deletedAt' => null, 'isActive' => true]),
            'by_category' => $this->createQueryBuilder('s')
                ->select("JSON_EXTRACT(s.attributes, '$.category') as category, COUNT(s.id) as count")
                ->andWhere('s.deletedAt IS NULL')
                ->andWhere('s.isActive = true')
                ->groupBy('category')
                ->getQuery()
                ->getResult(),
            'preferred_suppliers' => $this->count([
                    'deletedAt' => null,
                    'isActive' => true,
                ]) + $this->createQueryBuilder('s')
                    ->select('COUNT(s.id)')
                    ->andWhere('s.deletedAt IS NULL')
                    ->andWhere('s.isActive = true')
                    ->andWhere("JSON_EXTRACT(s.attributes, '$.is_preferred') = true")
                    ->getQuery()
                    ->getSingleScalarResult(),
            'new_this_month' => $this->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->andWhere('s.deletedAt IS NULL')
                ->andWhere('s.isActive = true')
                ->andWhere('s.createdAt >= :firstOfMonth')
                ->setParameter('firstOfMonth', new \DateTime('first day of this month'))
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    public function findSuppliersByRiskLevel(string $riskLevel): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = true')
            ->andWhere("JSON_EXTRACT(s.attributes, '$.risk_level') = :riskLevel")
            ->setParameter('riskLevel', $riskLevel)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findSuppliersNeedingReview(): array
    {
        $sixMonthsAgo = (new \DateTime())->modify('-6 months');

        return $this->createQueryBuilder('s')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.isActive = true')
            ->andWhere('s.updatedAt < :threshold')
            ->setParameter('threshold', $sixMonthsAgo)
            ->orderBy('s.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
