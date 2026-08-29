<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Input\OrderLine;

final readonly class InputSnapshot
{
    /**
     * @param list<OrderLine> $lines
     */
    public function __construct(
        public array $lines,
        public string $postalCode,
        /** Normalised ISO 3166-1 alpha-2 (QuoteEngine only builds a snapshot after validation). */
        public string $country,
        public bool $indoor,
    ) {
    }
}