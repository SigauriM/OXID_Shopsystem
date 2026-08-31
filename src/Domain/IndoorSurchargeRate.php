<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class IndoorSurchargeRate
{
    public function __construct(
        public int $priority,
        public int $cents,
    ) {
        if ($cents < 1) {
            throw new \InvalidArgumentException('Indoor surcharge cents must be 1 or greater.');
        }
    }
}
