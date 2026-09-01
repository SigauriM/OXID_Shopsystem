<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tariff;

use OxidShipping\Engine\Input\TariffConfig;

class TariffProvider
{
    private ?TariffConfig $cached = null;

    public function __construct(
        private TariffRepositoryInterface $repository,
    ) {
    }

    public function get(): TariffConfig
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        try {
            $config = $this->repository->findActive();
        } catch (\InvalidArgumentException $exception) {
            throw new TariffLoadFailed('Shop tariff document is invalid.', 0, $exception);
        } catch (\Throwable $exception) {
            throw new TariffLoadFailed('Shop tariff cannot be read.', 0, $exception);
        }

        if ($config === null) {
            throw new TariffLoadFailed('No active shipping tariff.');
        }

        $this->cached = $config;

        return $this->cached;
    }
}
