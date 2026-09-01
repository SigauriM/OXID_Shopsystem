<?php

declare(strict_types=1);

namespace OxidShipping\Module\Mapping;

use OxidShipping\Engine\Input\OrderLine;

final readonly class MappedCart
{
    /**
     * @param list<OrderLine> $lines
     */
    public function __construct(
        public array $lines,
        public string $postalCode,
        public string $country,
    ) {
    }
}
