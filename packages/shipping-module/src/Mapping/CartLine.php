<?php

declare(strict_types=1);

namespace OxidShipping\Module\Mapping;

final readonly class CartLine
{
    public function __construct(
        public string $articleNumber,
        public float $lengthMeters,
        public float $widthMeters,
        public float $heightMeters,
        public float $weightKg,
        public float $quantity,
    ) {
    }
}
