<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

use OxidShipping\Engine\ShippingClass;

final readonly class Shippable implements PieceOutcome
{
    public function __construct(public ShippingClass $class)
    {
    }
}