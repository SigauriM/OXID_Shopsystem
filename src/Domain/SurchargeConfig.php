<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class SurchargeConfig
{
    public function __construct(
        public IslandSurchargeRate $island,
        public IndoorSurchargeRate $indoor,
    ) {
        if ($island->priority === $indoor->priority) {
            throw new \InvalidArgumentException('Duplicate surcharge priority.');
        }
    }
}
