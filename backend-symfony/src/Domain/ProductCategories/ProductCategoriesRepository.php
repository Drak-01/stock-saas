<?php

namespace App\Domain\ProductCategories;

use App\Domain\ProductCategories\ProductCategories;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductCategories>
 */
class ProductCategoriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCategories::class);
    }

    public function save(ProductCategories $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductCategories $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCode(string $code): ?ProductCategories
    {
        return $this->createQueryBuilder('pc')
            ->andWhere('pc.code = :code')
            ->andWhere('pc.deletedAt IS NULL')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('pc')
            ->andWhere('pc.parent IS NULL')
            ->andWhere('pc.deletedAt IS NULL')
            ->andWhere('pc.isActive = true')
            ->orderBy('pc.sortOrder', 'ASC')
            ->addOrderBy('pc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveCategories(): array
    {
        return $this->createQueryBuilder('pc')
            ->andWhere('pc.deletedAt IS NULL')
            ->andWhere('pc.isActive = true')
            ->orderBy('pc.fullPath', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCategoriesByParent(?ProductCategories $parent = null): array
    {
        $qb = $this->createQueryBuilder('pc')
            ->andWhere('pc.deletedAt IS NULL')
            ->andWhere('pc.isActive = true')
            ->orderBy('pc.sortOrder', 'ASC')
            ->addOrderBy('pc.name', 'ASC');

        if ($parent === null) {
            $qb->andWhere('pc.parent IS NULL');
        } else {
            $qb->andWhere('pc.parent = :parent')
                ->setParameter('parent', $parent);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByFullPath(string $fullPath): ?ProductCategories
    {
        return $this->createQueryBuilder('pc')
            ->andWhere('pc.fullPath = :fullPath')
            ->andWhere('pc.deletedAt IS NULL')
            ->setParameter('fullPath', $fullPath)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDescendants(ProductCategories $category): array
    {
        return $this->createQueryBuilder('pc')
            ->andWhere('pc.fullPath LIKE :fullPath')
            ->andWhere('pc.deletedAt IS NULL')
            ->andWhere('pc.isActive = true')
            ->setParameter('fullPath', $category->getFullPath() . '.%')
            ->orderBy('pc.fullPath', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getCategoryTree(): array
    {
        $rootCategories = $this->findRootCategories();
        $tree = [];

        foreach ($rootCategories as $root) {
            $tree[] = $this->buildCategoryNode($root);
        }

        return $tree;
    }

    private function buildCategoryNode(ProductCategories $category): array
    {
        $node = $category->toArray();
        $children = $this->findCategoriesByParent($category);

        $node['children'] = [];
        foreach ($children as $child) {
            $node['children'][] = $this->buildCategoryNode($child);
        }

        return $node;
    }

    public function searchCategories(string $search): array
    {
        return $this->createQueryBuilder('pc')
            ->andWhere('pc.deletedAt IS NULL')
            ->andWhere('pc.isActive = true')
            ->andWhere('pc.code LIKE :search OR pc.name LIKE :search OR pc.fullPath LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('pc.fullPath', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getCategoriesWithProductCount(): array
    {
        return $this->createQueryBuilder('pc')
            ->select('pc.id, pc.code, pc.name, pc.fullPath, COUNT(p.id) as product_count')
            ->leftJoin('pc.products', 'p', 'WITH', 'p.deletedAt IS NULL AND p.isActive = true')
            ->andWhere('pc.deletedAt IS NULL')
            ->andWhere('pc.isActive = true')
            ->groupBy('pc.id')
            ->orderBy('pc.fullPath', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
