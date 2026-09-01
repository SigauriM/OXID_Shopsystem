<?php

declare(strict_types=1);

namespace OxidShipping\Module\Mapping;

use OxidShipping\Engine\Input\OrderLine;

final class CartMapper
{
    private const QUANTITY_TOLERANCE = 1e-9;

    /**
     * @param array<mixed> $lines
     */
    public function map(array $lines, string $postalCode, string $country): MappedCart|MappingFailed
    {
        if (!array_is_list($lines)) {
            return new MappingFailed('lines');
        }

        $orderLines = [];
        foreach ($lines as $index => $line) {
            if (!$line instanceof CartLine) {
                return new MappingFailed('lines[' . $index . ']');
            }

            $mapped = $this->mapLine($line);
            if ($mapped instanceof MappingFailed) {
                return $mapped;
            }

            $orderLines[] = $mapped;
        }

        return new MappedCart($orderLines, $postalCode, $country);
    }

    private function mapLine(CartLine $line): OrderLine|MappingFailed
    {
        if (
            !is_finite($line->lengthMeters)
            || !is_finite($line->widthMeters)
            || !is_finite($line->heightMeters)
        ) {
            return new MappingFailed('dimension');
        }

        if (!is_finite($line->weightKg)) {
            return new MappingFailed('weight');
        }

        $quantity = $this->mapQuantity($line->quantity);
        if ($quantity instanceof MappingFailed) {
            return $quantity;
        }

        return new OrderLine(
            $line->articleNumber,
            (int) round($line->lengthMeters * 1000),
            (int) round($line->widthMeters * 1000),
            (int) round($line->heightMeters * 1000),
            (int) round($line->weightKg * 1000),
            $quantity,
        );
    }

    private function mapQuantity(float $amount): int|MappingFailed
    {
        if (!is_finite($amount)) {
            return new MappingFailed('quantity');
        }

        $rounded = (int) round($amount);
        if ($rounded < 1 || abs($amount - $rounded) > self::QUANTITY_TOLERANCE) {
            return new MappingFailed('quantity');
        }

        return $rounded;
    }
}
