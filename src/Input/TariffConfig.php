<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Input;

use OxidShipping\Engine\Domain\ClassificationConfig;
use OxidShipping\Engine\Domain\OrderWeightThreshold;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Domain\ZoneConfig;

final readonly class TariffConfig
{
    public function __construct(
        public string $version,
        public VolumetricDivisor $volumetricDivisor,
        public ZoneConfig $zones,
        public ClassificationConfig $classification,
        public OrderWeightThreshold $orderWeightSpeditionThreshold,
    ) {
    }
}
