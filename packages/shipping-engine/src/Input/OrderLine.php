<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Input;

final readonly class OrderLine
{
    /**
     * Dimensions and weight are for a single piece. quantity is how many identical pieces.
     */
    public function __construct(
        public string $lineId,
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
        public int $weightGrams,
        public int $quantity,
    ) {
    }
}
