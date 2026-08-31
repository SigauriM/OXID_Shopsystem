<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tariff;

enum PriceStage: string
{
    case Base = 'base';
    case Surcharge = 'surcharge';
}
