<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use JsonException;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Module\Logging\QuoteTraceLogger;
use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartMapper;
use OxidShipping\Module\Quote\QuoteFacade;
use OxidShipping\Module\Seo\ProductFields;
use OxidShipping\Module\Seo\ProductOfferSchema;
use OxidShipping\Module\Seo\ProductSchemaBuilder;
use OxidShipping\Module\Seo\ShippingFacts;
use OxidShipping\Module\Tariff\TariffProvider;
use OxidShipping\Module\Tests\Support\FakeTariffRepository;
use OxidShipping\Module\Tests\Support\FixtureTariff;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ProductSchemaBuilderTest extends TestCase
{
    public function testLad500JsonHasThreeRatesByPostalCode(): void
    {
        $json = $this->builder()->json($this->lad500(), $this->fields());
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);
        $details = $data['offers']['shippingDetails'];
        $this->assertCount(3, $details);
        $this->assertSame(['01067'], $details[0]['shippingDestination']['postalCode']);
        $this->assertSame('60.00', $details[0]['shippingRate']['value']);
        $this->assertSame(5, $details[0]['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(['20095'], $details[1]['shippingDestination']['postalCode']);
        $this->assertSame('60.00', $details[1]['shippingRate']['value']);
        $this->assertSame(['27498'], $details[2]['shippingDestination']['postalCode']);
        $this->assertSame('68.00', $details[2]['shippingRate']['value']);
        $this->assertSame(7, $details[2]['deliveryTime']['transitTime']['minValue']);
        $this->assertSame($data['offers']['priceCurrency'], $details[0]['shippingRate']['currency']);
        $this->assertStringNotContainsString('zoneId', $json);
        $this->assertStringNotContainsString('configHash', $json);
        $this->assertStringNotContainsString('\u003C', $json);
    }

    public function testEmptyFactsOmitShippingDetailsKey(): void
    {
        $broken = new CartLine('BROKEN', 0.0, 0.44, 0.11, 14.2, 1.0);
        $json = $this->builder()->json($broken, $this->fields());
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('shippingDetails', $data['offers']);
    }

    public function testMissingPriceYieldsEmptyString(): void
    {
        $fields = new ProductFields('Ladder', 'LAD-500', null, null, null, 'EUR', 'https://schema.org/InStock');

        $this->assertSame('', $this->builder()->json($this->lad500(), $fields));
    }

    public function testScriptTagInNameIsHexEscaped(): void
    {
        $fields = new ProductFields(
            'Ladder</script><script>alert(1)',
            'LAD-500',
            null,
            null,
            '129.00',
            'EUR',
            'https://schema.org/InStock',
        );

        $json = $this->builder()->json($this->lad500(), $fields);

        $this->assertStringContainsString('\u003C', $json);
        $this->assertStringNotContainsString('</script>', $json);
    }

    public function testInvalidUtf8ThrowsJsonException(): void
    {
        $fields = new ProductFields(
            "\xB1\x31",
            'LAD-500',
            null,
            null,
            '129.00',
            'EUR',
            'https://schema.org/InStock',
        );

        $this->expectException(JsonException::class);
        $this->builder()->json($this->lad500(), $fields);
    }

    private function builder(): ProductSchemaBuilder
    {
        $tariff = new TariffProvider(new FakeTariffRepository(FixtureTariff::config()));

        return new ProductSchemaBuilder(
            new ShippingFacts(
                new QuoteFacade(
                    new QuoteEngine(),
                    $tariff,
                    new CartMapper(),
                    new NullLogger(),
                    new QuoteTraceLogger(
                        new NullLogger(),
                        '1.0.0',
                        sys_get_temp_dir() . '/shipping-quotes-test.ndjson',
                    ),
                ),
                $tariff,
                new NullLogger(),
            ),
            new ProductOfferSchema(),
        );
    }

    private function lad500(): CartLine
    {
        return new CartLine('LAD-500', 5.00, 0.44, 0.11, 14.2, 1.0);
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
