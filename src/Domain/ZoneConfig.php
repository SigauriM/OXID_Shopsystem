<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class ZoneConfig
{
    public function __construct(
        public ServedCountries $served,
        public ZoneDirectory $directory,
    ) {
        $directoryCountries = $directory->countries();
        foreach ($directoryCountries as $country) {
            if (!$served->has($country)) {
                throw new \InvalidArgumentException('Directory country is not served.');
            }
        }

        foreach ($served->codes() as $country) {
            if (!in_array($country, $directoryCountries, true)) {
                throw new \InvalidArgumentException('Served country has no directory entries.');
            }
        }
    }
}
