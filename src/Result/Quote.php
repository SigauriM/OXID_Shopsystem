<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Measurement\MeasuredPiece;

final readonly class Quote implements QuoteResult
{
    /**
     * @param list<MeasuredPiece> $pieces
     */
    public function __construct(
        /** Pipeline until grouping; not the §5.6 contract. */
        public array $pieces,
        public InputSnapshot $snapshot,
        public string $configVersion,
    ) {
    }
}
