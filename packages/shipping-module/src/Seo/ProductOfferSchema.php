<?php

declare(strict_types=1);

namespace OxidShipping\Module\Seo;

final class ProductOfferSchema
{
    /**
     * @param list<ShippingFact> $facts
     * @return array<string, mixed>
     */
    public function build(ProductFields $fields, array $facts): array
    {
        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
        ];
        if ($fields->name !== '') {
            $product['name'] = $fields->name;
        }
        if ($fields->sku !== '') {
            $product['sku'] = $fields->sku;
        }
        if ($fields->image !== null && $fields->image !== '') {
            $product['image'] = $fields->image;
        }
        if ($fields->url !== null && $fields->url !== '') {
            $product['url'] = $fields->url;
        }

        $offer = [
            '@type' => 'Offer',
            'price' => $fields->price,
            'priceCurrency' => $fields->priceCurrency,
            'availability' => $fields->availability,
        ];
        if ($fields->url !== null && $fields->url !== '') {
            $offer['url'] = $fields->url;
        }
        if ($facts !== []) {
            $offer['shippingDetails'] = $this->shippingDetails($facts, $fields->priceCurrency);
        }
        $product['offers'] = $offer;

        return $product;
    }

    /**
     * @param list<ShippingFact> $facts
     * @return list<array<string, mixed>>
     */
    private function shippingDetails(array $facts, string $currency): array
    {
        $details = [];
        foreach ($facts as $fact) {
            $details[] = [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => [
                    '@type' => 'MonetaryAmount',
                    'value' => $this->centsToRate($fact->cents),
                    'currency' => $currency,
                ],
                'shippingDestination' => [
                    '@type' => 'DefinedRegion',
                    'addressCountry' => $fact->country,
                    'postalCode' => $fact->postalCodes,
                ],
                'deliveryTime' => [
                    '@type' => 'ShippingDeliveryTime',
                    'transitTime' => [
                        '@type' => 'QuantitativeValue',
                        'minValue' => $fact->days,
                        'maxValue' => $fact->days,
                        'unitCode' => 'DAY',
                    ],
                ],
            ];
        }

        return $details;
    }

    private function centsToRate(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
