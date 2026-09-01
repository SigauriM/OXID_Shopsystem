<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Module\Logging\QuoteTraceLogger;
use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartMapper;
use OxidShipping\Module\Quote\QuoteFacade;
use OxidShipping\Module\Quote\ShopQuoteStatus;
use OxidShipping\Module\Tariff\TariffLoadFailed;
use OxidShipping\Module\Tariff\TariffProvider;
use OxidShipping\Module\Tests\Support\FakeCartSource;
use OxidShipping\Module\Tests\Support\FakeTariffSource;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class QuoteFacadeTest extends TestCase
{
    public function testGoldenCaseIsQuotedWithTwoShipments(): void
    {
        $quote = $this->facade()->quote($this->goldenSource('01067', 'DE'));

        $this->assertSame(ShopQuoteStatus::Quoted, $quote->status);
        $this->assertTrue($quote->isQuoted());
        $this->assertSame(6600, $quote->totalCents);
        $this->assertCount(2, $quote->shipments);
        $this->assertSame('OXIDSHIPPING_CLASS_PAKET', $quote->shipments[0]->classLangKey);
        $this->assertSame('de-01', $quote->shipments[0]->zoneId);
        $this->assertFalse($quote->shipments[0]->indoor);
        $this->assertSame(600, $quote->shipments[0]->totalCents);
        $this->assertSame(2, $quote->shipments[0]->transitDays);
        $this->assertSame('OXIDSHIPPING_CLASS_SPEDITION', $quote->shipments[1]->classLangKey);
        $this->assertSame('de-01', $quote->shipments[1]->zoneId);
        $this->assertFalse($quote->shipments[1]->indoor);
        $this->assertSame(6000, $quote->shipments[1]->totalCents);
        $this->assertSame(5, $quote->shipments[1]->transitDays);
    }

    public function testForbiddenZoneIsNotPossible(): void
    {
        $quote = $this->facade()->quote($this->goldenSource('18565', 'DE'));

        $this->assertSame(ShopQuoteStatus::NotPossible, $quote->status);
        $this->assertSame('OXIDSHIPPING_NOT_POSSIBLE_ZONE', $quote->messageLangKey);
        $this->assertSame(0, $quote->totalCents);
    }

    public function testUnknownPostalCodeIsNotPossible(): void
    {
        $quote = $this->facade()->quote($this->goldenSource('12345', 'DE'));

        $this->assertSame(ShopQuoteStatus::NotPossible, $quote->status);
        $this->assertSame('OXIDSHIPPING_NOT_POSSIBLE_PLZ', $quote->messageLangKey);
    }

    public function testUnservedCountryIsNotPossible(): void
    {
        $quote = $this->facade()->quote($this->goldenSource('8000', 'CH'));

        $this->assertSame(ShopQuoteStatus::NotPossible, $quote->status);
        $this->assertSame('OXIDSHIPPING_NOT_POSSIBLE_COUNTRY', $quote->messageLangKey);
    }

    public function testEmptyPostalCodeIsNeedAddressWithoutCallingEngine(): void
    {
        $tariff = new FakeTariffSource(new TariffLoadFailed('must not load tariff'));
        $facade = new QuoteFacade(new QuoteEngine(), $tariff, new CartMapper(), new NullLogger(), $this->traceLogger());

        $quote = $facade->quote(new FakeCartSource($this->goldenLines(), '', 'DE'));

        $this->assertSame(ShopQuoteStatus::NeedAddress, $quote->status);
        $this->assertSame('OXIDSHIPPING_NEED_ADDRESS', $quote->messageLangKey);
    }

    public function testZeroLengthIsInvalid(): void
    {
        $source = new FakeCartSource(
            [new CartLine('BROKEN', 0.0, 0.34, 0.08, 5.6, 1.0)],
            '01067',
            'DE',
        );

        $quote = $this->facade()->quote($source);

        $this->assertSame(ShopQuoteStatus::Invalid, $quote->status);
        $this->assertSame('OXIDSHIPPING_INVALID', $quote->messageLangKey);
        $this->assertSame(0, $quote->totalCents);
    }

    public function testTariffLoadFailureIsInvalidNotAnException(): void
    {
        $facade = new QuoteFacade(
            new QuoteEngine(),
            new FakeTariffSource(new TariffLoadFailed('Shop tariff document is invalid.')),
            new CartMapper(),
            new NullLogger(),
            $this->traceLogger(),
        );

        $quote = $facade->quote($this->goldenSource('01067', 'DE'));

        $this->assertSame(ShopQuoteStatus::Invalid, $quote->status);
        $this->assertSame('OXIDSHIPPING_INVALID', $quote->messageLangKey);
    }

    public function testQuoteExceptionFromIncompleteGridIsInvalid(): void
    {
        $payload = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/config/shop-tariff.json'),
            true,
        );
        $this->assertIsArray($payload);
        $payload['rates']['base']['paket'] = [
            ['cents' => 400, 'upTo' => 1000],
        ];

        $facade = new QuoteFacade(
            new QuoteEngine(),
            new FakeTariffSource(TariffDocument::fromArray($payload)),
            new CartMapper(),
            new NullLogger(),
            $this->traceLogger(),
        );

        $quote = $facade->quote($this->goldenSource('01067', 'DE'));

        $this->assertSame(ShopQuoteStatus::Invalid, $quote->status);
        $this->assertSame('OXIDSHIPPING_INVALID', $quote->messageLangKey);
    }

    private function facade(): QuoteFacade
    {
        return new QuoteFacade(
            new QuoteEngine(),
            new TariffProvider(),
            new CartMapper(),
            new NullLogger(),
            $this->traceLogger(),
        );
    }

    private function traceLogger(): QuoteTraceLogger
    {
        return new QuoteTraceLogger(new NullLogger(), '1.0.0', sys_get_temp_dir() . '/shipping-quotes-test.ndjson');
    }

    private function goldenSource(string $postalCode, string $country): FakeCartSource
    {
        return new FakeCartSource($this->goldenLines(), $postalCode, $country);
    }

    /**
     * @return list<CartLine>
     */
    private function goldenLines(): array
    {
        return [
            new CartLine('LAD-200', 2.00, 0.34, 0.08, 5.6, 1.0),
            new CartLine('LAD-500', 5.00, 0.44, 0.11, 14.2, 1.0),
        ];
    }
}
