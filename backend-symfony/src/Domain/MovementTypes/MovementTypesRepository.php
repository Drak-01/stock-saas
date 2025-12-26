<?php

namespace App\Domain\MovementTypes;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MovementTypes>
 */
class MovementTypesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MovementTypes::class);
    }

    public function save(MovementTypes $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MovementTypes $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function softDelete(MovementTypes $entity, bool $flush = true): void
    {
        $entity->softDelete();
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCode(string $code): ?MovementTypes
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.code = :code')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.deletedAt IS NULL')
            ->orderBy('m.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByEffect(string $effect): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.effect = :effect')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('effect', $effect)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findSystemTypes(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.isSystem = :isSystem')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('isSystem', true)
            ->orderBy('m.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findIncomingTypes(): array
    {
        return $this->findByEffect('in');
    }

    public function findOutgoingTypes(): array
    {
        return $this->findByEffect('out');
    }

    public function findTransferTypes(): array
    {
        return $this->findByEffect('transfer');
    }

    public function findAdjustmentTypes(): array
    {
        return $this->findByEffect('adjustment');
    }

    public function search(string $searchTerm, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.code LIKE :search OR m.name LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('m.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function paginate(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->andWhere('m.deletedAt IS NULL');

        // Apply filters
        if (!empty($filters['effect'])) {
            $queryBuilder->andWhere('m.effect = :effect')
                ->setParameter('effect', $filters['effect']);
        }

        if (!empty($filters['isSystem'])) {
            $queryBuilder->andWhere('m.isSystem = :isSystem')
                ->setParameter('isSystem', $filters['isSystem']);
        }

        if (!empty($filters['search'])) {
            $queryBuilder->andWhere('m.code LIKE :search OR m.name LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Count total
        $countQuery = clone $queryBuilder;
        $total = $countQuery->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $results = $queryBuilder
            ->orderBy('m.code', 'ASC')
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

    public function initializeSystemTypes(): void
    {
        $existingTypes = $this->findSystemTypes();
        $existingCodes = array_map(fn($type) => $type->getCode(), $existingTypes);

        foreach (MovementTypes::getSystemTypes() as $systemTypeData) {
            if (!in_array($systemTypeData['code'], $existingCodes)) {
                $movementType = new MovementTypes();
                $movementType->setCode($systemTypeData['code']);
                $movementType->setName($systemTypeData['name']);
                $movementType->setEffect($systemTypeData['effect']);
                $movementType->setRequiresReference($systemTypeData['requiresReference']);
                $movementType->setIsSystem($systemTypeData['isSystem']);

                $this->save($movementType, false);
            }
        }

        $this->getEntityManager()->flush();
    }
}
