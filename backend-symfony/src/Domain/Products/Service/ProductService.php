<?php

namespace App\Domain\Products\Service;

use App\Domain\Products\Products;
use App\Domain\Products\ProductsRepository;
use App\Domain\StockLocations\StockLocationsRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    private ProductsRepository $productsRepository;
    private StockLocationsRepository $stockLocationsRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ProductsRepository $productsRepository,
        StockLocationsRepository $stockLocationsRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->productsRepository = $productsRepository;
        $this->stockLocationsRepository = $stockLocationsRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Crée un nouveau produit avec validation
     */
    public function createProduct(array $data): Products
    {
        $product = new Products();

        $product->setSku($data['sku']);
        $product->setName($data['name']);
        $product->setProductType($data['product_type']);

        if (isset($data['barcode'])) {
            $product->setBarcode($data['barcode']);
        }

        if (isset($data['description'])) {
            $product->setDescription($data['description']);
        }

        if (isset($data['category_id'])) {
            $category = $this->entityManager->getReference(
                'App\Domain\ProductCategories\ProductCategories',
                $data['category_id']
            );
            $product->setCategory($category);
        }

        // Unit est obligatoire
        $unit = $this->entityManager->getReference(
            'App\Domain\MeasurementUnits\MeasurementUnits',
            $data['unit_id']
        );
        $product->setUnit($unit);

        // Prix et stock
        if (isset($data['cost_price'])) {
            $product->setCostPrice($data['cost_price']);
        }

        if (isset($data['selling_price'])) {
            $product->setSellingPrice($data['selling_price']);
        }

        if (isset($data['min_stock_level'])) {
            $product->setMinStockLevel($data['min_stock_level']);
        }

        if (isset($data['max_stock_level'])) {
            $product->setMaxStockLevel($data['max_stock_level']);
        }

        if (isset($data['reorder_point'])) {
            $product->setReorderPoint($data['reorder_point']);
        }

        // Attributs
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $product->setAttributes($data['attributes']);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    /**
     * Met à jour le stock d'un produit dans un entrepôt
     */
    public function updateStock(
        Products $product,
        string $warehouseId,
        float $quantityChange,
        string $reason = 'adjustment'
    ): void {
        $stockLocation = $this->stockLocationsRepository->findByProductAndWarehouse(
            $product->getId(),
            $warehouseId
        );

        if ($stockLocation === null) {
            $warehouse = $this->entityManager->getReference(
                'App\Domain\Warehouses\Warehouses',
                $warehouseId
            );

            $stockLocation = new StockLocations();
            $stockLocation->setProduct($product);
            $stockLocation->setWarehouse($warehouse);
            $stockLocation->setQuantityOnHand(0);
            $stockLocation->setQuantityReserved(0);
            $stockLocation->setQuantityOrdered(0);

            $this->entityManager->persist($stockLocation);
        }

        $newQuantity = $stockLocation->getQuantityOnHand() + $quantityChange;

        // Vérifier le stock négatif selon les règles de l'entrepôt
        if ($newQuantity < 0 && !$stockLocation->getWarehouse()->getSetting('allow_negative_stock', false)) {
            throw new \RuntimeException('Negative stock not allowed for this warehouse');
        }

        $stockLocation->setQuantityOnHand($newQuantity);

        // Enregistrer le mouvement (dans une vraie implémentation)
        // $this->recordStockMovement($product, $warehouseId, $quantityChange, $reason);

        $this->entityManager->flush();
    }

    /**
     * Calcule le prix de vente recommandé basé sur la marge cible
     */
    public function calculateRecommendedPrice(
        Products $product,
        float $targetMarginPercent
    ): ?float {
        $costPrice = $product->getCostPrice();

        if ($costPrice === null) {
            return null;
        }

        $cost = (float) $costPrice;
        $recommendedPrice = $cost * (1 + ($targetMarginPercent / 100));

        return round($recommendedPrice, 4);
    }

    /**
     * Trouve des produits similaires (même catégorie, fournisseur, etc.)
     */
    public function findSimilarProducts(Products $product, int $limit = 10): array
    {
        $qb = $this->productsRepository->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.isActive = true')
            ->andWhere('p.id != :currentProductId')
            ->setParameter('currentProductId', $product->getId())
            ->setMaxResults($limit);

        // Priorité 1: Même catégorie
        if ($product->getCategory() !== null) {
            $qb->andWhere('p.category = :categoryId')
                ->setParameter('categoryId', $product->getCategory()->getId());
        }

        // Priorité 2: Même fournisseur
        $supplierId = $product->getAttribute('supplier_id');
        if ($supplierId !== null) {
            $qb->orWhere("JSON_EXTRACT(p.attributes, '$.supplier_id') = :supplierId")
                ->setParameter('supplierId', $supplierId);
        }

        // Priorité 3: Mots-clés similaires dans le nom
        $keywords = explode(' ', $product->getName());
        foreach (array_slice($keywords, 0, 3) as $index => $keyword) {
            if (strlen($keyword) > 3) {
                $qb->orWhere("p.name LIKE :keyword{$index}")
                    ->setParameter("keyword{$index}", '%' . $keyword . '%');
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Génère un SKU automatique basé sur la catégorie et des métadonnées
     */
    public function generateSku(
        string $name,
        ?string $categoryCode = null,
        ?string $supplierCode = null
    ): string {
        $base = '';

        if ($categoryCode !== null) {
            $base .= substr(strtoupper($categoryCode), 0, 3) . '-';
        }

        if ($supplierCode !== null) {
            $base .= substr(strtoupper($supplierCode), 0, 3) . '-';
        }

        // Extraire les premières lettres des mots significatifs
        $words = preg_split('/\s+/', strtoupper($name));
        $acronym = '';

        foreach ($words as $word) {
            if (strlen($word) > 2 && !in_array($word, ['ET', 'DE', 'LE', 'LA', 'LES', 'DU', 'DES'])) {
                $acronym .= $word[0];
            }
        }

        $base .= substr($acronym, 0, 3);

        // Ajouter un numéro séquentiel
        $lastProduct = $this->productsRepository->createQueryBuilder('p')
            ->select('p.sku')
            ->andWhere('p.sku LIKE :base')
            ->setParameter('base', $base . '%')
            ->orderBy('p.sku', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastProduct) {
            $lastSku = $lastProduct['sku'];
            $lastNumber = (int) substr($lastSku, strlen($base));
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '001';
        }

        return $base . $nextNumber;
    }

    /**
     * Vérifie si un produit peut être supprimé (pas de stock, pas de transactions récentes)
     */
    public function canDeleteProduct(Products $product): bool
    {
        // Vérifier le stock
        if ($product->getTotalStock() > 0) {
            return false;
        }

        // Vérifier les mouvements récents (30 derniers jours)
        $thirtyDaysAgo = new \DateTime('-30 days');

        $recentMovements = $this->entityManager->createQueryBuilder()
            ->select('COUNT(sm.id)')
            ->from('App\Domain\StockMovements\stock_movements', 'sm')
            ->where('sm.product = :productId')
            ->andWhere('sm.createdAt >= :date')
            ->setParameter('productId', $product->getId())
            ->setParameter('date', $thirtyDaysAgo)
            ->getQuery()
            ->getSingleScalarResult();

        return $recentMovements == 0;
    }

    /**
     * Calcule la valeur totale du stock pour un produit
     */
    public function calculateTotalStockValue(Products $product): array
    {
        $stockLocations = $this->stockLocationsRepository->findByProduct($product->getId());

        $totalValue = 0;
        $byWarehouse = [];

        foreach ($stockLocations as $location) {
            $warehouseValue = $location->getQuantityOnHand() * (float) ($product->getCostPrice() ?? 0);
            $totalValue += $warehouseValue;

            $byWarehouse[$location->getWarehouse()->getName()] = [
                'warehouse' => $location->getWarehouse()->getName(),
                'quantity' => $location->getQuantityOnHand(),
                'value' => $warehouseValue,
                'average_cost' => $location->getAverageCost(),
            ];
        }

        return [
            'total_value' => $totalValue,
            'by_warehouse' => $byWarehouse,
            'cost_price' => $product->getCostPrice(),
            'total_quantity' => $product->getTotalStock(),
        ];
    }

    /**
     * Met à jour les points de réappro automatiquement basé sur l'historique des ventes
     */
    public function updateReorderPointsAutomatically(
        Products $product,
        int $leadTimeDays = 7,
        int $safetyStockDays = 3
    ): void {
        // Calculer la demande moyenne quotidienne sur les 90 derniers jours
        $ninetyDaysAgo = new \DateTime('-90 days');

        $dailyDemand = $this->entityManager->createQueryBuilder()
            ->select('SUM(sol.quantityFulfilled) / 90 as avg_daily_demand')
            ->from('App\Domain\SalesOrderLines\SalesOrderLines', 'sol')
            ->join('sol.salesOrder', 'so')
            ->where('sol.product = :productId')
            ->andWhere('so.orderDate >= :date')
            ->andWhere('so.status = :completed')
            ->setParameter('productId', $product->getId())
            ->setParameter('date', $ninetyDaysAgo)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        if ($dailyDemand > 0) {
            $leadTimeDemand = $dailyDemand * $leadTimeDays;
            $safetyStock = $dailyDemand * $safetyStockDays;

            $newReorderPoint = $leadTimeDemand + $safetyStock;

            $product->setReorderPoint((string) round($newReorderPoint, 2));
            $this->entityManager->flush();
        }
    }
}
