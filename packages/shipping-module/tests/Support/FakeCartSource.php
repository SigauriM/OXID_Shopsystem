<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests\Support;

use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartSource;

final class FakeCartSource implements CartSource
{
    /**
     * @param list<CartLine> $lines
     */
    public function __construct(
        private array $lines,
        private string $postalCode,
        private string $countryIso,
    ) {
    }

    public function lines(): array
    {
        return $this->lines;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function countryIso(): string
    {
        return $this->countryIso;
    }
}
