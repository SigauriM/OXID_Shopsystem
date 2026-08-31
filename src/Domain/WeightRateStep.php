<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class WeightRateStep
{
    public function __construct(
        public int $upTo,
        public int $cents,
    ) {
        if ($upTo < 1) {
            throw new \InvalidArgumentException('Weight rate upTo must be 1 or greater.');
        }
        if ($cents < 1) {
            throw new \InvalidArgumentException('Weight rate cents must be 1 or greater.');
        }
    }
}
