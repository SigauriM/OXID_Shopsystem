<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Module\Logging\QuoteTraceLogger;
use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartMapper;
use OxidShipping\Module\Quote\QuoteChannel;
use OxidShipping\Module\Quote\QuoteFacade;
use OxidShipping\Module\Quote\ShopQuoteStatus;
use OxidShipping\Module\Tariff\TariffLoadFailed;
use OxidShipping\Module\Tariff\TariffProvider;
use OxidShipping\Module\Tests\Support\FakeCartSource;
use OxidShipping\Module\Tests\Support\FakeTariffRepository;
use OxidShipping\Module\Tests\Support\FakeTariffSource;
use OxidShipping\Module\Tests\Support\FixtureTariff;
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
        $this->assertSame([0 => 'LAD-200', 1 => 'LAD-500'], $quote->lineLabels);
        $this->assertCount(1, $quote->shipments[0]->pieces);
        $this->assertSame(0, $quote->shipments[0]->pieces[0]->lineIndex);
        $this->assertSame(0, $quote->shipments[0]->pieces[0]->pieceIndex);
        $this->assertSame('LAD-200', $quote->shipments[0]->pieces[0]->lineId);
        $this->assertCount(1, $quote->shipments[1]->pieces);
        $this->assertSame(1, $quote->shipments[1]->pieces[0]->lineIndex);
        $this->assertSame('LAD-500', $quote->shipments[1]->pieces[0]->lineId);
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

    public function testLad500ProductPageIsSpeditionSixtyEuro(): void
    {
        $quote = $this->facade()->quote($this->lad500('01067'), QuoteChannel::ProductPage);

        $this->assertSame(ShopQuoteStatus::Quoted, $quote->status);
        $this->assertSame(6000, $quote->totalCents);
        $this->assertCount(1, $quote->shipments);
        $this->assertSame('OXIDSHIPPING_CLASS_SPEDITION', $quote->shipments[0]->classLangKey);
        $this->assertSame(5, $quote->shipments[0]->transitDays);
    }

    public function testUnknownPostal99999IsNotPossible(): void
    {
        $quote = $this->facade()->quote($this->lad500('99999'), QuoteChannel::ProductPage);

        $this->assertSame(ShopQuoteStatus::NotPossible, $quote->status);
        $this->assertSame('OXIDSHIPPING_NOT_POSSIBLE_PLZ', $quote->messageLangKey);
        $this->assertSame(0, $quote->totalCents);
    }

    public function testProductPageChannelDoesNotWriteNdjson(): void
    {
        $path = sys_get_temp_dir() . '/shipping-quotes-product-' . bin2hex(random_bytes(8)) . '.ndjson';
        @unlink($path);
        $facade = new QuoteFacade(
            new QuoteEngine(),
            new TariffProvider(new FakeTariffRepository(FixtureTariff::config())),
            new CartMapper(),
            new NullLogger(),
            new QuoteTraceLogger(new NullLogger(), '1.0.0', $path),
        );

        $facade->quote($this->lad500('01067'), QuoteChannel::ProductPage);
        $this->assertFileDoesNotExist($path);

        $facade->quote($this->lad500('01067'), QuoteChannel::Basket);
        $this->assertFileExists($path);
        $this->assertSame(1, substr_count((string) file_get_contents($path), "\n"));
        @unlink($path);
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

    public function testNoActiveTariffIsInvalidNotAnException(): void
    {
        $facade = new QuoteFacade(
            new QuoteEngine(),
            new TariffProvider(new FakeTariffRepository()),
            new CartMapper(),
            new NullLogger(),
            $this->traceLogger(),
        );

        $quote = $facade->quote($this->goldenSource('01067', 'DE'));

        $this->assertSame(ShopQuoteStatus::Invalid, $quote->status);
        $this->assertSame('OXIDSHIPPING_INVALID', $quote->messageLangKey);
    }

    public function testBrokenPayloadIsInvalidNotAnException(): void
    {
        $facade = new QuoteFacade(
            new QuoteEngine(),
            new TariffProvider(FakeTariffRepository::withBrokenActivePayload()),
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
            new TariffProvider(new FakeTariffRepository(FixtureTariff::config())),
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

    private function lad500(string $postalCode): FakeCartSource
    {
        return new FakeCartSource(
            [new CartLine('LAD-500', 5.00, 0.44, 0.11, 14.2, 1.0)],
            $postalCode,
            'DE',
        );
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
