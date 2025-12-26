<?php

namespace App\Domain\GlobalUsers;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GlobalUsers>
 */
class GlobalUsersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GlobalUsers::class);
    }

    public function save(GlobalUsers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GlobalUsers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByEmailAndCompany(string $email, string $companyId): ?GlobalUsers
    {
        return $this->createQueryBuilder('gu')
            ->andWhere('gu.email = :email')
            ->andWhere('gu.company = :companyId')
            ->andWhere('gu.deletedAt IS NULL')
            ->setParameter('email', mb_strtolower($email))
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveByEmail(string $email): ?GlobalUsers
    {
        return $this->createQueryBuilder('gu')
            ->andWhere('gu.email = :email')
            ->andWhere('gu.isActive = true')
            ->andWhere('gu.deletedAt IS NULL')
            ->setParameter('email', mb_strtolower($email))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActiveByCompany(string $companyId): int
    {
        return $this->createQueryBuilder('gu')
            ->select('COUNT(gu.id)')
            ->andWhere('gu.company = :companyId')
            ->andWhere('gu.isActive = true')
            ->andWhere('gu.deletedAt IS NULL')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUsersWithRecentLogin(int $days = 30): array
    {
        $date = (new \DateTime())->modify("-$days days");

        return $this->createQueryBuilder('gu')
            ->andWhere('gu.lastLogin >= :date')
            ->andWhere('gu.deletedAt IS NULL')
            ->setParameter('date', $date)
            ->orderBy('gu.lastLogin', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
