<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

enum RejectReason: string
{
    case CountryNotServed = 'country_not_served';
    case UnknownZone = 'unknown_zone';
    case ZoneForbidden = 'zone_forbidden';

    public function explanation(): string
    {
        return match ($this) {
            self::CountryNotServed => 'Country is not in the shipping list.',
            self::UnknownZone => 'Postal code is not in the directory.',
            self::ZoneForbidden => 'Shipping to this zone is not allowed.',
        };
    }
}
