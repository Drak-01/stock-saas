<?php

namespace App\Domain\BillsOfMaterial;

use App\Domain\BomLines\BomLines;
use App\Domain\Products\Products;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BomLines>
 */
class BomLinesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BomLines::class);
    }

    public function save(BomLines $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BomLines $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByBom(BillsOfMaterial $bom): array
    {
        return $this->createQueryBuilder('bl')
            ->andWhere('bl.bom = :bom')
            ->andWhere('bl.deletedAt IS NULL')
            ->setParameter('bom', $bom)
            ->orderBy('bl.sequence', 'ASC')
            ->addOrderBy('bl.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByComponent(Products $component, array $options = []): array
    {
        $queryBuilder = $this->createQueryBuilder('bl')
            ->join('bl.bom', 'bom')
            ->andWhere('bl.component = :component')
            ->andWhere('bl.deletedAt IS NULL')
            ->andWhere('bom.deletedAt IS NULL')
            ->setParameter('component', $component)
            ->orderBy('bom.code', 'ASC')
            ->addOrderBy('bl.sequence', 'ASC');

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findLineByBomAndComponent(BillsOfMaterial $bom, Products $component): ?BomLines
    {
        return $this->createQueryBuilder('bl')
            ->andWhere('bl.bom = :bom')
            ->andWhere('bl.component = :component')
            ->andWhere('bl.deletedAt IS NULL')
            ->setParameter('bom', $bom)
            ->setParameter('component', $component)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getComponentUsageStatistics(Products $component): array
    {
        return $this->createQueryBuilder('bl')
            ->select([
                'COUNT(DISTINCT bom.id) as bomCount',
                'SUM(bl.quantityRequired) as totalQuantityRequired',
                'AVG(bl.wasteFactor) as averageWasteFactor',
            ])
            ->join('bl.bom', 'bom')
            ->andWhere('bl.component = :component')
            ->andWhere('bl.deletedAt IS NULL')
            ->andWhere('bom.deletedAt IS NULL')
            ->setParameter('component', $component)
            ->getQuery()
            ->getSingleResult();
    }

    public function getBomCostBreakdown(BillsOfMaterial $bom): array
    {
        return $this->createQueryBuilder('bl')
            ->select([
                'p.id as componentId',
                'p.sku as componentSku',
                'p.name as componentName',
                'bl.quantityRequired',
                'bl.wasteFactor',
                'bl.getEffectiveQuantity() as effectiveQuantity',
                'p.costPrice as unitCost',
                '(bl.getEffectiveQuantity() * p.costPrice) as totalCost',
            ])
            ->join('bl.component', 'p')
            ->andWhere('bl.bom = :bom')
            ->andWhere('bl.deletedAt IS NULL')
            ->setParameter('bom', $bom)
            ->orderBy('bl.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getComponentsSummary(BillsOfMaterial $bom): array
    {
        return $this->createQueryBuilder('bl')
            ->select([
                'COUNT(bl.id) as componentsCount',
                'SUM(bl.quantityRequired) as totalQuantityRequired',
                'SUM(bl.getEffectiveQuantity()) as totalEffectiveQuantity',
                'AVG(bl.wasteFactor) as averageWasteFactor',
            ])
            ->andWhere('bl.bom = :bom')
            ->andWhere('bl.deletedAt IS NULL')
            ->setParameter('bom', $bom)
            ->getQuery()
            ->getSingleResult();
    }

    public function findComponentsWithLowStock(BillsOfMaterial $bom, array $stockLevels): array
    {
        $results = $this->createQueryBuilder('bl')
            ->select([
                'bl.id',
                'p.id as componentId',
                'p.sku as componentSku',
                'p.name as componentName',
                'bl.getEffectiveQuantity() as requiredQuantity',
                's.quantityOnHand as availableStock',
                'p.minStockLevel',
                'p.reorderPoint',
            ])
            ->join('bl.component', 'p')
            ->leftJoin('p.stockLocations', 's')
            ->andWhere('bl.bom = :bom')
            ->andWhere('bl.deletedAt IS NULL')
            ->setParameter('bom', $bom)
            ->getQuery()
            ->getResult();

        $lowStockComponents = [];
        foreach ($results as $result) {
            $available = $result['availableStock'] ?? '0';
            $required = $result['requiredQuantity'];
            $minStock = $result['minStockLevel'] ?? '0';
            $reorderPoint = $result['reorderPoint'] ?? '0';

            // Vérifier si le stock est insuffisant pour la production
            if (bccomp($available, $required, 6) < 0) {
                $lowStockComponents[] = [
                    'component' => [
                        'id' => $result['componentId'],
                        'sku' => $result['componentSku'],
                        'name' => $result['componentName'],
                    ],
                    'requiredQuantity' => $required,
                    'availableStock' => $available,
                    'shortage' => bcsub($required, $available, 6),
                    'isBelowReorderPoint' => bccomp($available, $reorderPoint, 6) <= 0,
                    'isBelowMinStock' => bccomp($available, $minStock, 6) < 0,
                ];
            }
        }

        return $lowStockComponents;
    }

    private function applyOptions($queryBuilder, array $options): void
    {
        if (!empty($options['limit'])) {
            $queryBuilder->setMaxResults($options['limit']);
        }

        if (!empty($options['offset'])) {
            $queryBuilder->setFirstResult($options['offset']);
        }

        if (isset($options['bomActive'])) {
            $queryBuilder->andWhere('bom.isActive = :bomActive')
                ->setParameter('bomActive', $options['bomActive']);
        }

        if (!empty($options['orderBy'])) {
            foreach ($options['orderBy'] as $field => $direction) {
                if (strpos($field, '.') !== false) {
                    $queryBuilder->addOrderBy($field, $direction);
                } else {
                    $queryBuilder->addOrderBy('bl.' . $field, $direction);
                }
            }
        }
    }
}
