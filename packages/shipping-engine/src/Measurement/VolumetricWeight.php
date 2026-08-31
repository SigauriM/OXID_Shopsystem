<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Measurement;

use OxidShipping\Engine\Domain\VolumetricDivisor;

final class VolumetricWeight
{
    // 200_000³ × 1000 = 8e18, compared to 64-bit PHP_INT_MAX (9.223e18).
    // This guard is not self-sufficient: it assumes the QuoteEngine 64-bit RuntimeException.
    private const MAX_SAFE_SIDE_MM = 200_000;

    private function __construct()
    {
    }

    public static function grams(Dimensions $dimensions, VolumetricDivisor $divisor): int
    {
        foreach ([$dimensions->lengthMm, $dimensions->widthMm, $dimensions->heightMm] as $side) {
            if ($side > self::MAX_SAFE_SIDE_MM) {
                throw new \InvalidArgumentException(
                    'Side exceeds the 64-bit safe range for volumetric weight.',
                );
            }
        }

        $mmG = $divisor->mmG();
        $numerator = ($dimensions->lengthMm * $dimensions->widthMm * $dimensions->heightMm * 1000)
            + $mmG
            - 1;

        return intdiv($numerator, $mmG);
    }
}
