<?php

declare(strict_types=1);

namespace OxidShipping\Module\Seo;

final readonly class ProductFields
{
    public function __construct(
        public string $name,
        public string $sku,
        public ?string $image,
        public ?string $url,
        public ?string $price,
        public string $priceCurrency,
        public string $availability,
    ) {
    }
}
