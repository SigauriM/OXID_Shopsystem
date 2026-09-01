<?php

declare(strict_types=1);

namespace OxidShipping\Module\Mapping;

interface CartSource
{
    /**
     * @return list<CartLine>
     */
    public function lines(): array;

    public function postalCode(): string;

    public function countryIso(): string;

    /**
     * @return array<int, string> lineIndex => storefront label (title + article number)
     */
    public function lineLabels(): array;
}
