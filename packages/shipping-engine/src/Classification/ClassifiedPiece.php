<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Classification;

use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\ShippingClass;

final readonly class ClassifiedPiece
{
    public function __construct(
        public MeasuredPiece $piece,
        public ShippingClass $class,
    ) {
    }
}
