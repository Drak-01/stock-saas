<?php

namespace App\Domain\Customers;

use App\Domain\Customers\Customers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customers>
 */
class CustomersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customers::class);
    }

    public function save(Customers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Customers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCode(string $code): ?Customers
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.code = :code')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveCustomers(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $customerType): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.customerType = :customerType')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.isActive = true')
            ->setParameter('customerType', $customerType)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchCustomers(string $search): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.isActive = true')
            ->andWhere('c.code LIKE :search OR c.name LIKE :search OR c.email LIKE :search OR c.contactPerson LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCustomersWithHighCreditUtilization(float $threshold = 80): array
    {
        // Cette requête nécessiterait une jointure avec les factures
        // Ceci est une version simplifiée
        return $this->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.isActive = true')
            ->andWhere('c.creditLimit IS NOT NULL')
            ->andWhere('c.creditLimit > 0')
            ->getQuery()
            ->getResult();
    }

    public function findOverdueCustomers(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.salesOrders', 'so')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.isActive = true')
            ->andWhere('so.paymentStatus = :unpaid')
            ->andWhere('so.dueDate < :today')
            ->setParameter('unpaid', 'unpaid')
            ->setParameter('today', new \DateTime())
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();
    }

    public function findInactiveCustomers(int $daysInactive = 365): array
    {
        $dateThreshold = (new \DateTime())->modify("-$daysInactive days");

        return $this->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.isActive = true')
            ->andWhere('NOT EXISTS (
                SELECT so FROM App\Domain\SalesOrders\SalesOrders so
                WHERE so.customer = c.id
                AND so.createdAt >= :threshold
            )')
            ->setParameter('threshold', $dateThreshold)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTopCustomersByRevenue(int $limit = 10, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.id, c.code, c.name, SUM(so.totalAmount) as total_revenue')
            ->join('c.salesOrders', 'so')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.isActive = true')
            ->andWhere('so.status = :completed')
            ->setParameter('completed', 'completed')
            ->groupBy('c.id, c.code, c.name')
            ->orderBy('total_revenue', 'DESC')
            ->setMaxResults($limit);

        if ($startDate !== null && $endDate !== null) {
            $qb->andWhere('so.orderDate BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }

    public function generateNextCode(string $prefix = 'CUST'): string
    {
        $lastCustomer = $this->createQueryBuilder('c')
            ->select('c.code')
            ->andWhere('c.code LIKE :prefix')
            ->setParameter('prefix', $prefix . '-%')
            ->orderBy('c.code', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastCustomer) {
            $lastCode = $lastCustomer['code'];
            $lastNumber = (int) substr($lastCode, strlen($prefix) + 1);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return $prefix . '-' . $nextNumber;
    }

    public function getCustomerStatistics(): array
    {
        return [
            'total_customers' => $this->count(['deletedAt' => null, 'isActive' => true]),
            'by_type' => $this->createQueryBuilder('c')
                ->select('c.customerType, COUNT(c.id) as count')
                ->andWhere('c.deletedAt IS NULL')
                ->andWhere('c.isActive = true')
                ->groupBy('c.customerType')
                ->getQuery()
                ->getResult(),
            'new_this_month' => $this->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->andWhere('c.deletedAt IS NULL')
                ->andWhere('c.isActive = true')
                ->andWhere('c.createdAt >= :firstOfMonth')
                ->setParameter('firstOfMonth', new \DateTime('first day of this month'))
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }
}
