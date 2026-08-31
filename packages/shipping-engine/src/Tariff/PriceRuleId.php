<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tariff;

enum PriceRuleId: string
{
    case Base = 'base';
    case Island = 'island';
    case Indoor = 'indoor';
}
