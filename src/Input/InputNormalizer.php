<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Input;

final class InputNormalizer
{
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