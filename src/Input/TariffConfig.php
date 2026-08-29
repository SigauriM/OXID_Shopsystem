<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Input;

use OxidShipping\Engine\Domain\VolumetricDivisor;

final readonly class TariffConfig
{
    public function __construct(
        public string $version,
        public VolumetricDivisor $volumetricDivisor,
    ) {
    }
}
