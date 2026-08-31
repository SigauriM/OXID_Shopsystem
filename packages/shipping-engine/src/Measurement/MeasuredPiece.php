<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Measurement;

use OxidShipping\Engine\Domain\VolumetricDivisor;

final readonly class MeasuredPiece
{
    public static function from(
        string $lineId,
        int $lineIndex,
        int $pieceIndex,
        Dimensions $dimensions,
        int $actualGrams,
        VolumetricDivisor $divisor,
    ): self {
        if ($actualGrams <= 0) {
            throw new \InvalidArgumentException('actualGrams must be greater than 0.');
        }
        if ($lineIndex < 0 || $pieceIndex < 0) {
            throw new \InvalidArgumentException('lineIndex and pieceIndex must be 0 or greater.');
        }

        $volumetricGrams = VolumetricWeight::grams($dimensions, $divisor);

        return new self(
            $lineId,
            $lineIndex,
            $pieceIndex,
            $dimensions,
            $actualGrams,
            $volumetricGrams,
            max($actualGrams, $volumetricGrams),
        );
    }

    /**
     * @param int<0, max> $lineIndex
     * @param int<0, max> $pieceIndex
     * @param positive-int $actualGrams
     */
    private function __construct(
        public string $lineId,
        public int $lineIndex,
        public int $pieceIndex,
        public Dimensions $dimensions,
        public int $actualGrams,
        public int $volumetricGrams,
        public int $billableGrams,
    ) {
    }
}
