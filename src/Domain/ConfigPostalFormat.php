<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final class ConfigPostalFormat
{
    private const PATTERNS = [
        'DE' => '/^[0-9]{5}$/',
        'AT' => '/^[0-9]{4}$/',
    ];

    private function __construct()
    {
    }

    public static function supports(string $country): bool
    {
        return isset(self::PATTERNS[$country]);
    }

    public static function assertPostalCode(string $country, string $postalCode): void
    {
        if (!self::supports($country)) {
            throw new \InvalidArgumentException('Country has no config postal form.');
        }

        if (preg_match(self::PATTERNS[$country], $postalCode) !== 1) {
            throw new \InvalidArgumentException(
                'Postal code does not match the config form for the country.',
            );
        }
    }
}
