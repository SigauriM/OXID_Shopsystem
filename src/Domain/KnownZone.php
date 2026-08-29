<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class KnownZone implements ZoneLookup
{
    public function __construct(public string $zoneId)
    {
    }
}