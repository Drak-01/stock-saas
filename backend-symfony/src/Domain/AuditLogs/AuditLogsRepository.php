<?php

namespace App\Domain\AuditLogs;

use App\Domain\TenantUsers\TenantUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLogs>
 */
class AuditLogsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLogs::class);
    }

    public function save(AuditLogs $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditLogs $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(TenantUsers $user, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('al')
            ->andWhere('al.user = :user')
            ->setParameter('user', $user)
            ->orderBy('al.createdAt', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByEntity(string $entityType, string $entityId): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.entityType = :entityType')
            ->andWhere('al.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByAction(string $action, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('al')
            ->andWhere('al.action = :action')
            ->setParameter('action', $action)
            ->orderBy('al.createdAt', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('al')
            ->andWhere('al.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('al.createdAt', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('al')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findUserActivity(TenantUsers $user, int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('al')
            ->andWhere('al.user = :user')
            ->andWhere('al.createdAt >= :startDate')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $searchTerm, int $limit = 50): array
    {
        return $this->createQueryBuilder('al')
            ->leftJoin('al.user', 'u')
            ->andWhere('al.action LIKE :search OR al.entityType LIKE :search OR u.email LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function paginate(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $queryBuilder = $this->createQueryBuilder('al')
            ->orderBy('al.createdAt', 'DESC');

        // Appliquer les filtres
        if (!empty($filters['userId'])) {
            $queryBuilder->andWhere('al.user = :userId')
                ->setParameter('userId', $filters['userId']);
        }

        if (!empty($filters['action'])) {
            $queryBuilder->andWhere('al.action = :action')
                ->setParameter('action', $filters['action']);
        }

        if (!empty($filters['entityType'])) {
            $queryBuilder->andWhere('al.entityType = :entityType')
                ->setParameter('entityType', $filters['entityType']);
        }

        if (!empty($filters['entityId'])) {
            $queryBuilder->andWhere('al.entityId = :entityId')
                ->setParameter('entityId', $filters['entityId']);
        }

        if (!empty($filters['startDate'])) {
            $queryBuilder->andWhere('al.createdAt >= :startDate')
                ->setParameter('startDate', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $queryBuilder->andWhere('al.createdAt <= :endDate')
                ->setParameter('endDate', $filters['endDate']);
        }

        if (!empty($filters['search'])) {
            $queryBuilder->leftJoin('al.user', 'u')
                ->andWhere('al.action LIKE :search OR al.entityType LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['ipAddress'])) {
            $queryBuilder->andWhere('al.ipAddress = :ipAddress')
                ->setParameter('ipAddress', $filters['ipAddress']);
        }

        // Compter le total
        $countQuery = clone $queryBuilder;
        $total = $countQuery->select('COUNT(al.id)')
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
        $queryBuilder = $this->createQueryBuilder('al')
            ->select([
                'COUNT(al.id) as totalLogs',
                'COUNT(DISTINCT al.user) as uniqueUsers',
                'COUNT(DISTINCT al.entityType) as entityTypes',
                'SUM(CASE WHEN al.action = \'create\' THEN 1 ELSE 0 END) as createCount',
                'SUM(CASE WHEN al.action = \'update\' THEN 1 ELSE 0 END) as updateCount',
                'SUM(CASE WHEN al.action = \'delete\' THEN 1 ELSE 0 END) as deleteCount',
                'SUM(CASE WHEN al.action = \'login\' THEN 1 ELSE 0 END) as loginCount',
                'SUM(CASE WHEN al.action = \'logout\' THEN 1 ELSE 0 END) as logoutCount',
                'MIN(al.createdAt) as firstLog',
                'MAX(al.createdAt) as lastLog',
            ]);

        if ($startDate) {
            $queryBuilder->andWhere('al.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('al.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getSingleResult();
    }

    public function getActivityByHour(\DateTimeInterface $date): array
    {
        $queryBuilder = $this->createQueryBuilder('al')
            ->select([
                'HOUR(al.createdAt) as hour',
                'COUNT(al.id) as logCount',
                'COUNT(DISTINCT al.user) as userCount',
            ])
            ->andWhere('DATE(al.createdAt) = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->groupBy('hour')
            ->orderBy('hour', 'ASC');

        $results = $queryBuilder->getQuery()->getResult();

        // Formater les résultats pour avoir toutes les heures
        $hourlyData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyData[$hour] = [
                'hour' => $hour,
                'logCount' => 0,
                'userCount' => 0,
            ];
        }

        foreach ($results as $result) {
            $hour = (int) $result['hour'];
            $hourlyData[$hour] = [
                'hour' => $hour,
                'logCount' => (int) $result['logCount'],
                'userCount' => (int) $result['userCount'],
            ];
        }

        return array_values($hourlyData);
    }

    public function getTopUsers(int $limit = 10, \DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        $queryBuilder = $this->createQueryBuilder('al')
            ->select([
                'u.id as userId',
                'u.email as userEmail',
                'COUNT(al.id) as logCount',
                'COUNT(DISTINCT al.entityType) as entityTypes',
            ])
            ->join('al.user', 'u')
            ->groupBy('u.id', 'u.email')
            ->orderBy('logCount', 'DESC')
            ->setMaxResults($limit);

        if ($startDate) {
            $queryBuilder->andWhere('al.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $queryBuilder->andWhere('al.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function getEntityActivity(string $entityType, string $entityId): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.entityType = :entityType')
            ->andWhere('al.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    public function cleanupOldLogs(int $daysToKeep): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");

        $queryBuilder = $this->createQueryBuilder('al')
            ->delete()
            ->where('al.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate);

        return $queryBuilder->getQuery()->execute();
    }

    private function applyOptions($queryBuilder, array $options): void
    {
        if (!empty($options['limit'])) {
            $queryBuilder->setMaxResults($options['limit']);
        }

        if (!empty($options['offset'])) {
            $queryBuilder->setFirstResult($options['offset']);
        }

        if (!empty($options['orderBy'])) {
            foreach ($options['orderBy'] as $field => $direction) {
                $queryBuilder->addOrderBy('al.' . $field, $direction);
            }
        }
    }
}
