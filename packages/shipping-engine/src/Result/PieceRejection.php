<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Domain\Rejected;

final readonly class PieceRejection
{
    public function __construct(
        public string $lineId,
        public int $lineIndex,
        public int $pieceIndex,
        public Rejected $rejected,
    ) {
    }
}
