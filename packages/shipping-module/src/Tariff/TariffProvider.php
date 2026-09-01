<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tariff;

use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;

class TariffProvider
{
    private ?TariffConfig $cached = null;

    public function get(): TariffConfig
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $path = dirname(__DIR__, 2) . '/config/shop-tariff.json';
        if (!is_readable($path)) {
            throw new TariffLoadFailed('Shop tariff file cannot be read.');
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new TariffLoadFailed('Shop tariff file cannot be read.');
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new TariffLoadFailed('Shop tariff JSON is invalid.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new TariffLoadFailed('Shop tariff JSON root must be an object.');
        }

        try {
            $this->cached = TariffDocument::fromArray($decoded);
        } catch (\InvalidArgumentException $exception) {
            throw new TariffLoadFailed('Shop tariff document is invalid.', 0, $exception);
        }

        return $this->cached;
    }
}
