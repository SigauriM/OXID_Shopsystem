<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Input;

final readonly class TariffConfig
{
    public function __construct(public string $version)
    {
    }
}