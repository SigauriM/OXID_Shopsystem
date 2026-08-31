<?php

declare(strict_types=1);

namespace OxidShipping\Engine\OrderRules;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\OrderWeightThreshold;
use OxidShipping\Engine\ShippingClass;

final readonly class OrderWeightSpeditionOverride
{
    /**
     * @param list<ClassifiedPiece> $classified
     * @return list<ClassifiedPiece>
     */
    public function apply(array $classified, OrderWeightThreshold $threshold): array
    {
        $actualGrams = 0;
        foreach ($classified as $item) {
            $actualGrams += $item->piece->actualGrams;
        }

        if ($actualGrams >= $threshold->grams) {
            $raised = [];
            foreach ($classified as $item) {
                $raised[] = new ClassifiedPiece(
                    $item->piece,
                    $item->class->atLeast(ShippingClass::Spedition),
                );
            }

            return $raised;
        }

        return $classified;
    }
}
