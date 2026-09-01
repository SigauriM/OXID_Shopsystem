<?php

declare(strict_types=1);

namespace OxidShipping\Module\Adapter;

use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartSource;

final class SingleArticleSource implements CartSource
{
    public function __construct(
        private CartLine $line,
        private string $postalCode,
        private string $countryIso,
    ) {
    }

    public function lines(): array
    {
        return [$this->line];
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function countryIso(): string
    {
        return $this->countryIso;
    }

    public function lineLabels(): array
    {
        return [0 => $this->line->articleNumber];
    }
}
