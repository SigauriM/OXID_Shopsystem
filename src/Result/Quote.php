<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Domain\Rejected;

final readonly class Quote implements QuoteResult
{
    /**
     * @param list<never> $shipments
     * @param list<Rejected> $rejections
     */
    public function __construct(
        public array $shipments,
        public array $rejections,
        public int $totalCents,
        public InputSnapshot $snapshot,
        public string $configVersion,
    ) {
    }
}