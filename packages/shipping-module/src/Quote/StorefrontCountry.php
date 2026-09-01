<?php

declare(strict_types=1);

namespace OxidShipping\Module\Quote;

final class StorefrontCountry
{
    /**
     * @var list<string>
     */
    public const CODES = ['DE'];

    private function __construct()
    {
    }

    public static function default(): string
    {
        return self::CODES[0];
    }
}
