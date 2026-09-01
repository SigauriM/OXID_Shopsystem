<?php

declare(strict_types=1);

namespace OxidShipping\Module\Seo;

use OxidShipping\Module\Mapping\CartLine;

final class ProductSchemaBuilder
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES;

    public function __construct(
        private ShippingFacts $facts,
        private ProductOfferSchema $schema,
    ) {
    }

    public function json(CartLine $line, ProductFields $fields): string
    {
        if ($fields->price === null || $fields->price === '' || $fields->priceCurrency === '') {
            return '';
        }

        return json_encode(
            $this->schema->build($fields, $this->facts->for($line)),
            self::JSON_FLAGS,
        );
    }
}
