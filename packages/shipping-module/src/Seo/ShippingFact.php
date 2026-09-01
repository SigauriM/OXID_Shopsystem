<?php

declare(strict_types=1);

namespace OxidShipping\Module\Seo;

final readonly class ShippingFact
{
    /**
     * @param list<string> $postalCodes
     */
    public function __construct(
        public string $zoneId,
        public string $country,
        public array $postalCodes,
        public int $cents,
        public int $days,
    ) {
    }
}
