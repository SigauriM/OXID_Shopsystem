<?php

declare(strict_types=1);

namespace OxidShipping\Module\Quote;

final readonly class ShopPiece
{
    public function __construct(
        public int $lineIndex,
        public int $pieceIndex,
        public string $lineId,
        public int $billableGrams,
    ) {
    }
}
