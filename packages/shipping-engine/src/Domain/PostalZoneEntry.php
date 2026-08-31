<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class PostalZoneEntry
{
    public function __construct(
        public string $country,
        public string $postalCode,
        public string $zoneId,
    ) {
        if ($country !== AddressShape::country($country)) {
            throw new \InvalidArgumentException('Postal country must already be normalised.');
        }
        if ($postalCode !== AddressShape::postalCode($postalCode)) {
            throw new \InvalidArgumentException('Postal code must already be normalised.');
        }

        ConfigPostalFormat::assertPostalCode($country, $postalCode);
        ZoneId::assert($zoneId);
    }
}
