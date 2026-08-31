<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Input;

use OxidShipping\Engine\Domain\ClassificationConfig;
use OxidShipping\Engine\Domain\OrderWeightThreshold;
use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Domain\ZoneConfig;
use OxidShipping\Engine\ShippingClass;

final readonly class TariffConfig
{
    public function __construct(
        public string $version,
        public VolumetricDivisor $volumetricDivisor,
        public ZoneConfig $zones,
        public ClassificationConfig $classification,
        public OrderWeightThreshold $orderWeightSpeditionThreshold,
        public TariffRates $rates,
    ) {
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/', $version) !== 1) {
            throw new \InvalidArgumentException('Tariff config version is invalid.');
        }

        foreach ($rates->surcharges->island->zoneIds as $zoneId) {
            if (!$zones->directory->has($zoneId)) {
                throw new \InvalidArgumentException(
                    'Island surcharge zone id is not in the directory.',
                );
            }
        }

        foreach ($zones->directory->definitions() as $definition) {
            if ($definition->forbidden) {
                continue;
            }
            foreach (ShippingClass::cases() as $class) {
                if (!$rates->transit->has($class, $definition->zoneId)) {
                    throw new \InvalidArgumentException(
                        'Transit days are required for every live zone and class.',
                    );
                }
            }
        }
    }
}
