<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class ZoneDefinition
{
    public function __construct(
        public string $zoneId,
        public bool $forbidden,
    ) {
        ZoneId::assert($zoneId);
    }
}
