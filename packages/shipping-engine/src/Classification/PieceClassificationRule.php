<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Classification;

use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\ShippingClass;

interface PieceClassificationRule
{
    public function floor(MeasuredPiece $piece): ?ShippingClass;
}
