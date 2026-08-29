<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Measurement;

final readonly class Dimensions
{
    private function __construct(
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
    ) {
    }

    public static function canonical(int $a, int $b, int $c): self
    {
        if ($a <= 0 || $b <= 0 || $c <= 0) {
            throw new \InvalidArgumentException('Each side must be greater than 0 millimetres.');
        }

        $sides = [$a, $b, $c];
        rsort($sides, SORT_NUMERIC);

        return new self($sides[0], $sides[1], $sides[2]);
    }

    /**
     * Gurtmaß: longest side plus twice each of the other two (millimetres).
     * Sides are bounded by input validation; girth assumes validated dimensions.
     */
    public function girthMm(): int
    {
        return $this->lengthMm + (2 * $this->widthMm) + (2 * $this->heightMm);
    }
}
