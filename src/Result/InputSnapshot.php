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
        public string $country,
        public bool $indoor,
    ) {
    }
}