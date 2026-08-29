<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final class AddressShape
{
    public const POSTAL_CODE_PATTERN = '/^[0-9A-Za-z][0-9A-Za-z \-]{1,9}$/';

    /** ISO 3166-1 alpha-2 shape after normalisation. Not membership in {DE, AT}. */
    public const COUNTRY_PATTERN = '/^[A-Z]{2}$/';

    private function __construct()
    {
    }

    public static function postalCode(string $postalCode): string
    {
        return trim($postalCode);
    }

    /**
     * Trim and uppercase. Does not make the value valid: deu becomes DEU and still fails alpha-2.
     */
    public static function country(string $country): string
    {
        return strtoupper(trim($country));
    }
}
