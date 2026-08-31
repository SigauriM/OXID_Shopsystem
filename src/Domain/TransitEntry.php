<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

use OxidShipping\Engine\ShippingClass;

final readonly class TransitEntry
{
    public function __construct(
        public ShippingClass $class,
        public string $zoneId,
        public int $days,
    ) {
        ZoneId::assert($zoneId);
        if ($days < 1) {
            throw new \InvalidArgumentException('Transit days must be 1 or greater.');
        }
    }
}
