<?php

namespace App\Domain\BillsOfMaterial;

use App\Domain\Products\Products;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BillsOfMaterial>
 */
class BillsOfMaterialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BillsOfMaterial::class);
    }

    public function save(BillsOfMaterial $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BillsOfMaterial $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function softDelete(BillsOfMaterial $entity, bool $flush = true): void
    {
        $entity->setDeletedAt(new \DateTime());
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCode(string $code): ?BillsOfMaterial
    {
        return $this->createQueryBuilder('bom')
            ->andWhere('bom.code = :code')
            ->andWhere('bom.deletedAt IS NULL')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByFinishedProduct(Products $product, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('bom')
            ->andWhere('bom.finishedProduct = :product')
            ->andWhere('bom.deletedAt IS NULL')
            ->setParameter('product', $product)
            ->orderBy('bom.version', 'DESC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findActiveByFinishedProduct(Products $product): ?BillsOfMaterial
    {
        return $this->createQueryBuilder('bom')
            ->andWhere('bom.finishedProduct = :product')
            ->andWhere('bom.isActive = :active')
            ->andWhere('bom.deletedAt IS NULL')
            ->setParameter('product', $product)
            ->setParameter('active', true)
            ->orderBy('bom.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('bom')
            ->andWhere('bom.isActive = :active')
            ->andWhere('bom.deletedAt IS NULL')
            ->setParameter('active', true)
            ->orderBy('bom.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestVersions(): array
    {
        // Sous-requête pour trouver la version max par produit
        $subQuery = $this->createQueryBuilder('bom2')
            ->select('MAX(bom2.version)')
            ->andWhere('bom2.finishedProduct = bom.finishedProduct')
            ->andWhere('bom2.deletedAt IS NULL')
            ->getDQL();

        return $this->createQueryBuilder('bom')
            ->andWhere('bom.version = (' . $subQuery . ')')
            ->andWhere('bom.deletedAt IS NULL')
            ->orderBy('bom.finishedProduct.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $searchTerm, int $limit = 20): array
    {
        return $this->createQueryBuilder('bom')
            ->leftJoin('bom.finishedProduct', 'p')
            ->andWhere('bom.deletedAt IS NULL')
            ->andWhere('bom.code LIKE :search OR p.name LIKE :search OR p.sku LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('bom.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function paginate(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $queryBuilder = $this->createQueryBuilder('bom')
            ->andWhere('bom.deletedAt IS NULL')
            ->orderBy('bom.code', 'ASC')
            ->addOrderBy('bom.version', 'DESC');

        // Appliquer les filtres
        if (!empty($filters['finishedProductId'])) {
            $queryBuilder->andWhere('bom.finishedProduct = :finishedProductId')
                ->setParameter('finishedProductId', $filters['finishedProductId']);
        }

        if (isset($filters['isActive'])) {
            $queryBuilder->andWhere('bom.isActive = :isActive')
                ->setParameter('isActive', $filters['isActive']);
        }

        if (!empty($filters['search'])) {
            $queryBuilder->leftJoin('bom.finishedProduct', 'p')
                ->andWhere('bom.code LIKE :search OR p.name LIKE :search OR p.sku LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['minComponents'])) {
            $queryBuilder->join('bom.lines', 'l')
                ->groupBy('bom.id')
                ->having('COUNT(l.id) >= :minComponents')
                ->setParameter('minComponents', $filters['minComponents']);
        }

        if (!empty($filters['maxComponents'])) {
            $queryBuilder->join('bom.lines', 'l')
                ->groupBy('bom.id')
                ->having('COUNT(l.id) <= :maxComponents')
                ->setParameter('maxComponents', $filters['maxComponents']);
        }

        // Compter le total
        $countQuery = clone $queryBuilder;
        $total = $countQuery->select('COUNT(bom.id)')
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

    public function getStatistics(): array
    {
        return $this->createQueryBuilder('bom')
            ->select([
                'COUNT(bom.id) as totalBoms',
                'SUM(CASE WHEN bom.isActive = true THEN 1 ELSE 0 END) as activeCount',
                'AVG(bom.version) as averageVersion',
                'MIN(bom.createdAt) as oldestBom',
                'MAX(bom.createdAt) as newestBom',
            ])
            ->andWhere('bom.deletedAt IS NULL')
            ->getQuery()
            ->getSingleResult();
    }

    public function getProductBomStatistics(Products $product): array
    {
        return $this->createQueryBuilder('bom')
            ->select([
                'COUNT(bom.id) as totalVersions',
                'MAX(bom.version) as latestVersion',
                'SUM(CASE WHEN bom.isActive = true THEN 1 ELSE 0 END) as activeCount',
                'MIN(bom.createdAt) as firstVersionDate',
                'MAX(bom.createdAt) as lastVersionDate',
            ])
            ->andWhere('bom.finishedProduct = :product')
            ->andWhere('bom.deletedAt IS NULL')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleResult();
    }

    public function findBomsByComponent(Products $component): array
    {
        return $this->createQueryBuilder('bom')
            ->join('bom.lines', 'l')
            ->andWhere('l.component = :component')
            ->andWhere('bom.deletedAt IS NULL')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('component', $component)
            ->orderBy('bom.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getComponentsUsage(): array
    {
        return $this->createQueryBuilder('bom')
            ->select([
                'p.id as componentId',
                'p.sku as componentSku',
                'p.name as componentName',
                'COUNT(DISTINCT bom.id) as bomCount',
                'SUM(l.quantityRequired) as totalQuantityRequired',
            ])
            ->join('bom.lines', 'l')
            ->join('l.component', 'p')
            ->andWhere('bom.deletedAt IS NULL')
            ->andWhere('l.deletedAt IS NULL')
            ->groupBy('p.id', 'p.sku', 'p.name')
            ->orderBy('bomCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function applyOptions($queryBuilder, array $options): void
    {
        if (!empty($options['limit'])) {
            $queryBuilder->setMaxResults($options['limit']);
        }

        if (!empty($options['offset'])) {
            $queryBuilder->setFirstResult($options['offset']);
        }

        if (isset($options['isActive'])) {
            $queryBuilder->andWhere('bom.isActive = :isActive')
                ->setParameter('isActive', $options['isActive']);
        }

        if (!empty($options['orderBy'])) {
            foreach ($options['orderBy'] as $field => $direction) {
                $queryBuilder->addOrderBy('bom.' . $field, $direction);
            }
        }
    }
}
