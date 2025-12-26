<?php

namespace App\Domain\Warehouses;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Warehouses>
 */
class WarehousesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Warehouses::class);
    }

    public function save(Warehouses $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Warehouses $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCode(string $code): ?Warehouses
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.code = :code')
            ->andWhere('w.deletedAt IS NULL')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveWarehouses(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.isActive = true')
            ->andWhere('w.deletedAt IS NULL')
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.type = :type')
            ->andWhere('w.deletedAt IS NULL')
            ->andWhere('w.isActive = true')
            ->setParameter('type', $type)
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getDefaultWarehouse(): ?Warehouses
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.deletedAt IS NULL')
            ->andWhere('w.isActive = true')
            ->andWhere("JSON_EXTRACT(w.settings, '$.is_default') = true")
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getPhysicalWarehouses(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.deletedAt IS NULL')
            ->andWhere('w.isActive = true')
            ->andWhere('w.type IN (:physicalTypes)')
            ->setParameter('physicalTypes', [
                Warehouses::TYPE_WAREHOUSE,
                Warehouses::TYPE_STORE,
                Warehouses::TYPE_PRODUCTION,
                Warehouses::TYPE_RETURNS
            ])
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchWarehouses(string $search): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.deletedAt IS NULL')
            ->andWhere('w.isActive = true')
            ->andWhere('w.code LIKE :search OR w.name LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
