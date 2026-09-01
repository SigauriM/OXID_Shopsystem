<?php

declare(strict_types=1);

namespace OxidShipping\Module\Quote;

enum ShopQuoteStatus: string
{
    case NeedAddress = 'NeedAddress';
    case Quoted = 'Quoted';
    case NotPossible = 'NotPossible';
    case Invalid = 'Invalid';
}
