<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests\Support;

use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Module\Tariff\TariffLoadFailed;
use OxidShipping\Module\Tariff\TariffProvider;

final class FakeTariffSource extends TariffProvider
{
    public function __construct(
        private TariffConfig|TariffLoadFailed $result,
    ) {
    }

    public function get(): TariffConfig
    {
        if ($this->result instanceof TariffLoadFailed) {
            throw $this->result;
        }

        return $this->result;
    }
}
