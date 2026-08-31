<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Zone;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Domain\ZoneConfig;

final class ZoneResolver
{
    public function resolve(string $postalCode, string $country, ZoneConfig $zones): KnownZone|Rejected
    {
        if (!$zones->served->has($country)) {
            return new Rejected(RejectReason::CountryNotServed);
        }

        $lookup = $zones->directory->lookup($postalCode, $country);
        if (!$lookup instanceof KnownZone) {
            return new Rejected(RejectReason::UnknownZone);
        }

        if ($zones->directory->definition($lookup->zoneId)->forbidden) {
            return new Rejected(RejectReason::ZoneForbidden);
        }

        return $lookup;
    }
}
