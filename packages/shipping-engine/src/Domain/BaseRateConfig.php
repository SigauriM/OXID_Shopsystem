<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

use OxidShipping\Engine\ShippingClass;

final readonly class BaseRateConfig
{
    public function __construct(
        public WeightRateTable $paket,
        public WeightRateTable $sperrgut,
        public WeightRateTable $spedition,
    ) {
    }

    public function for(ShippingClass $class): WeightRateTable
    {
        return match ($class) {
            ShippingClass::Paket => $this->paket,
            ShippingClass::Sperrgut => $this->sperrgut,
            ShippingClass::Spedition => $this->spedition,
        };
    }
}
