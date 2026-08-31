<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Measurement;

use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Input\OrderLine;

final class PieceFactory
{
    /**
     * @param list<OrderLine> $lines
     * @return list<MeasuredPiece>
     */
    public function expand(array $lines, VolumetricDivisor $divisor): array
    {
        $pieces = [];

        foreach ($lines as $lineIndex => $line) {
            $dimensions = Dimensions::canonical($line->lengthMm, $line->widthMm, $line->heightMm);

            for ($pieceIndex = 0; $pieceIndex < $line->quantity; $pieceIndex++) {
                $pieces[] = MeasuredPiece::from(
                    $line->lineId,
                    $lineIndex,
                    $pieceIndex,
                    $dimensions,
                    $line->weightGrams,
                    $divisor,
                );
            }
        }

        return $pieces;
    }
}
