<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class TariffRates
{
    public function __construct(
        public BaseRateConfig $base,
        public SurchargeConfig $surcharges,
        public TransitTable $transit,
    ) {
    }
}
