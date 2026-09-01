<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Module\Seo\ProductFields;
use OxidShipping\Module\Seo\ProductOfferSchema;
use OxidShipping\Module\Seo\ShippingFact;
use PHPUnit\Framework\TestCase;

final class ProductOfferSchemaTest extends TestCase
{
    public function testBuildsProductOfferWithThreeShippingDetails(): void
    {
        $data = (new ProductOfferSchema())->build(
            $this->fields(),
            [
                new ShippingFact('de-01', 'DE', ['01067'], 6000, 5),
                new ShippingFact('de-hh', 'DE', ['20095'], 6000, 5),
                new ShippingFact('de-island', 'DE', ['27498'], 6800, 7),
            ],
        );

        $this->assertSame(
            ['@context', '@type', 'name', 'sku', 'image', 'url', 'offers'],
            array_keys($data),
        );
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Product', $data['@type']);

        $offer = $data['offers'];
        $this->assertSame(
            ['@type', 'price', 'priceCurrency', 'availability', 'url', 'shippingDetails'],
            array_keys($offer),
        );
        $this->assertSame('Offer', $offer['@type']);
        $this->assertSame('129.00', $offer['price']);
        $this->assertSame('EUR', $offer['priceCurrency']);

        $details = $offer['shippingDetails'];
        $this->assertCount(3, $details);
        $this->assertSame('60.00', $details[0]['shippingRate']['value']);
        $this->assertIsString($details[0]['shippingRate']['value']);
        $this->assertSame('EUR', $details[0]['shippingRate']['currency']);
        $this->assertSame(['01067'], $details[0]['shippingDestination']['postalCode']);
        $this->assertIsString($details[0]['shippingDestination']['postalCode'][0]);
        $this->assertSame(5, $details[0]['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(5, $details[0]['deliveryTime']['transitTime']['maxValue']);
        $this->assertSame('DAY', $details[0]['deliveryTime']['transitTime']['unitCode']);

        $this->assertSame('60.00', $details[1]['shippingRate']['value']);
        $this->assertSame(['20095'], $details[1]['shippingDestination']['postalCode']);
        $this->assertSame('68.00', $details[2]['shippingRate']['value']);
        $this->assertSame(['27498'], $details[2]['shippingDestination']['postalCode']);
        $this->assertSame(7, $details[2]['deliveryTime']['transitTime']['minValue']);

        $encoded = json_encode($data);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('zoneId', $encoded);
        $this->assertStringNotContainsString('configHash', $encoded);
        $this->assertStringNotContainsString('handlingTime', $encoded);
        $this->assertStringNotContainsString('Paket', $encoded);
        $this->assertStringNotContainsString('Spedition', $encoded);
        $this->assertStringNotContainsString('de-01', $encoded);
    }

    public function testOmitsShippingDetailsWhenFactsAreEmpty(): void
    {
        $data = (new ProductOfferSchema())->build($this->fields(), []);

        $this->assertArrayNotHasKey('shippingDetails', $data['offers']);
        $this->assertSame(
            ['@type', 'price', 'priceCurrency', 'availability', 'url'],
            array_keys($data['offers']),
        );
    }

    public function testOmitsOptionalProductFieldsWhenEmpty(): void
    {
        $data = (new ProductOfferSchema())->build(
            new ProductFields('', '', null, null, '10.00', 'EUR', 'https://schema.org/InStock'),
            [],
        );

        $this->assertSame(['@context', '@type', 'offers'], array_keys($data));
        $this->assertArrayNotHasKey('url', $data['offers']);
    }

    private function fields(): ProductFields
    {
        return new ProductFields(
            'Anlegeleiter 5.0 m',
            'LAD-500',
            'http://127.0.0.1:8080/out/pictures/1.jpg',
            'http://127.0.0.1:8080/Geruest-und-Leitern/Anlegeleiter-5-0-m.html',
            '129.00',
            'EUR',
            'https://schema.org/InStock',
        );
    }
}
