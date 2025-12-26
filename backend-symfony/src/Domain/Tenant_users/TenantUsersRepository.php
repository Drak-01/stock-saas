<?php

namespace App\Domain\Tenant_users;

use App\Domain\TenantUsers\TenantUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TenantUsers>
 */
class TenantUsersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantUsers::class);
    }

    public function save(TenantUsers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TenantUsers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByGlobalUserId(string $globalUserId): ?TenantUsers
    {
        return $this->createQueryBuilder('tu')
            ->andWhere('tu.globalUserId = :globalUserId')
            ->andWhere('tu.deletedAt IS NULL')
            ->setParameter('globalUserId', $globalUserId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('tu')
            ->andWhere('tu.role = :role')
            ->andWhere('tu.deletedAt IS NULL')
            ->setParameter('role', $role)
            ->orderBy('tu.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('tu')
            ->andWhere('tu.deletedAt IS NULL')
            ->orderBy('tu.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchUsers(string $searchTerm): array
    {
        return $this->createQueryBuilder('tu')
            ->andWhere('tu.deletedAt IS NULL')
            ->andWhere('tu.fullName LIKE :search OR tu.department LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('tu.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getUsersByDepartment(): array
    {
        return $this->createQueryBuilder('tu')
            ->select('tu.department, COUNT(tu.id) as user_count')
            ->andWhere('tu.deletedAt IS NULL')
            ->andWhere('tu.department IS NOT NULL')
            ->groupBy('tu.department')
            ->orderBy('user_count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
