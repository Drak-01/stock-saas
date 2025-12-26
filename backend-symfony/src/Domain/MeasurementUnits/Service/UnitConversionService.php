<?php

namespace App\Domain\MeasurementUnits\Service;

use App\Domain\MeasurementUnits\MeasurementUnits;
use App\Repository\MeasurementUnitsRepository;

class UnitConversionService
{
    private MeasurementUnitsRepository $unitRepository;

    public function __construct(MeasurementUnitsRepository $unitRepository)
    {
        $this->unitRepository = $unitRepository;
    }

    /**
     * Convertit une quantité entre deux unités
     */
    public function convert(
        float $quantity,
        MeasurementUnits $fromUnit,
        MeasurementUnits $toUnit
    ): float {
        // Vérification de compatibilité
        if ($fromUnit->getUnitType() !== $toUnit->getUnitType()) {
            throw new \InvalidArgumentException(sprintf(
                'Incompatible unit types: %s cannot be converted to %s',
                $fromUnit->getUnitType(),
                $toUnit->getUnitType()
            ));
        }

        // Si mêmes unités, pas de conversion
        if ($fromUnit->getId() === $toUnit->getId()) {
            return $quantity;
        }

        // Utiliser la méthode de conversion de l'entité
        return $fromUnit->convertTo($toUnit, $quantity);
    }

    /**
     * Convertit vers l'unité de base
     */
    public function convertToBase(float $quantity, MeasurementUnits $unit): float
    {
        return $unit->convertToBase($quantity);
    }

    /**
     * Normalise toutes les quantités vers l'unité de base pour un type donné
     */
    public function normalizeQuantities(array $quantitiesByUnit): array
    {
        $normalized = [];

        foreach ($quantitiesByUnit as $unitId => $quantity) {
            $unit = $this->unitRepository->find($unitId);
            if (!$unit) {
                continue;
            }

            $baseUnit = $this->getBaseUnitForType($unit->getUnitType());
            if (!$baseUnit) {
                continue;
            }

            $normalized[$baseUnit->getId()] =
                ($normalized[$baseUnit->getId()] ?? 0) +
                $this->convert($quantity, $unit, $baseUnit);
        }

        return $normalized;
    }

    /**
     * Trouve l'unité de base pour un type donné
     */
    public function getBaseUnitForType(string $unitType): ?MeasurementUnits
    {
        $baseUnits = $this->unitRepository->findByType($unitType);

        foreach ($baseUnits as $unit) {
            if ($unit->isBaseUnit()) {
                return $unit;
            }
        }

        return null;
    }

    /**
     * Trouve la meilleure unité pour afficher une quantité
     */
    public function findBestDisplayUnit(float $quantityInBase, string $unitType): MeasurementUnits
    {
        $availableUnits = $this->unitRepository->findByType($unitType);

        // Trier par facteur de conversion décroissant
        usort($availableUnits, function($a, $b) {
            return (float) $b->getConversionFactor() <=> (float) $a->getConversionFactor();
        });

        // Trouver la plus grande unité qui donne un nombre >= 1
        foreach ($availableUnits as $unit) {
            $converted = $this->convert($quantityInBase, $this->getBaseUnitForType($unitType), $unit);
            if ($converted >= 1 || $unit->isBaseUnit()) {
                return $unit;
            }
        }

        // Fallback à l'unité de base
        return $this->getBaseUnitForType($unitType);
    }

    /**
     * Exemple : Gestion des recettes avec conversions
     */
    public function calculateRecipeRequirements(array $ingredients): array
    {
        // $ingredients = [
        //     ['product_id' => '...', 'quantity' => 10, 'unit' => 'kg', 'required_unit' => 'g']
        // ]

        $requirements = [];

        foreach ($ingredients as $ingredient) {
            $fromUnit = $this->unitRepository->findBySymbol($ingredient['unit']);
            $toUnit = $this->unitRepository->findBySymbol($ingredient['required_unit']);

            if (!$fromUnit || !$toUnit) {
                throw new \InvalidArgumentException('Unit not found');
            }

            $convertedQuantity = $this->convert(
                $ingredient['quantity'],
                $fromUnit,
                $toUnit
            );

            $requirements[] = [
                'product_id' => $ingredient['product_id'],
                'required_quantity' => $convertedQuantity,
                'required_unit' => $ingredient['required_unit'],
                'original_quantity' => $ingredient['quantity'],
                'original_unit' => $ingredient['unit']
            ];
        }

        return $requirements;
    }
}
