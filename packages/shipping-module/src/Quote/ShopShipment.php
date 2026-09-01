<?php

declare(strict_types=1);

namespace OxidShipping\Module\Quote;

final readonly class ShopShipment
{
    public function __construct(
        public string $classLangKey,
        public string $zoneId,
        public bool $indoor,
        public int $totalCents,
        public int $transitDays,
    ) {
    }
}
