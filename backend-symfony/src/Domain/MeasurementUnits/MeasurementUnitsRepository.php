<?php

namespace App\Repository;

use App\Domain\MeasurementUnits\MeasurementUnits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MeasurementUnits>
 */
class MeasurementUnitsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MeasurementUnits::class);
    }

    public function save(MeasurementUnits $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MeasurementUnits $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySymbol(string $symbol): ?MeasurementUnits
    {
        return $this->createQueryBuilder('mu')
            ->andWhere('mu.symbol = :symbol')
            ->andWhere('mu.deletedAt IS NULL')
            ->setParameter('symbol', $symbol)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findBaseUnits(): array
    {
        return $this->createQueryBuilder('mu')
            ->andWhere('mu.baseUnit IS NULL')
            ->andWhere('mu.deletedAt IS NULL')
            ->andWhere('mu.conversionFactor = 1')
            ->orderBy('mu.unitType', 'ASC')
            ->addOrderBy('mu.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $unitType): array
    {
        return $this->createQueryBuilder('mu')
            ->andWhere('mu.unitType = :unitType')
            ->andWhere('mu.deletedAt IS NULL')
            ->setParameter('unitType', $unitType)
            ->orderBy('mu.conversionFactor', 'ASC')
            ->addOrderBy('mu.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDerivedUnitsOf(MeasurementUnits $baseUnit): array
    {
        return $this->createQueryBuilder('mu')
            ->andWhere('mu.baseUnit = :baseUnit')
            ->andWhere('mu.deletedAt IS NULL')
            ->setParameter('baseUnit', $baseUnit)
            ->orderBy('mu.conversionFactor', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getCommonUnits(): array
    {
        // Unités les plus courantes par défaut
        return $this->createQueryBuilder('mu')
            ->andWhere('mu.deletedAt IS NULL')
            ->andWhere('mu.symbol IN (:commonSymbols)')
            ->setParameter('commonSymbols', [
                'pcs', 'kg', 'g', 'L', 'ml', 'm', 'cm', 'm²'
            ])
            ->orderBy('mu.unitType', 'ASC')
            ->addOrderBy('mu.conversionFactor', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchUnits(string $search): array
    {
        return $this->createQueryBuilder('mu')
            ->andWhere('mu.deletedAt IS NULL')
            ->andWhere('mu.name LIKE :search OR mu.symbol LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('mu.unitType', 'ASC')
            ->addOrderBy('mu.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
