<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final class ZoneId
{
    public const PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    private function __construct()
    {
    }

    public static function assert(string $zoneId): void
    {
        $length = strlen($zoneId);
        if ($length < 1 || $length > 32) {
            throw new \InvalidArgumentException('Zone id length must be between 1 and 32.');
        }

        if (preg_match(self::PATTERN, $zoneId) !== 1) {
            throw new \InvalidArgumentException('Zone id does not match the required pattern.');
        }
    }
}
