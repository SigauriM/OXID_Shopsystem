<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests\Support;

use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;

final class FixtureTariff
{
    public static function config(): TariffConfig
    {
        $path = dirname(__DIR__, 2) . '/config/shop-tariff.json';
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException('Shop tariff fixture cannot be read.');
        }

        return TariffDocument::fromJson($json);
    }
}
