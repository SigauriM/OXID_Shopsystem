<?php

declare(strict_types=1);

namespace OxidShipping\Module\Quote;

enum QuoteChannel: string
{
    case Basket = 'Basket';
    case ProductPage = 'ProductPage';
}
