<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Classification;

use OxidShipping\Engine\Domain\ThresholdTable;
use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\ShippingClass;

/** Gurtmaß */
final readonly class GirthRule implements PieceClassificationRule
{
    public function __construct(private ThresholdTable $table)
    {
    }

    public function floor(MeasuredPiece $piece): ?ShippingClass
    {
        return $this->table->floor($piece->dimensions->girthMm());
    }
}
