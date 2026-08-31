<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\IndoorSurchargeRate;
use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\SurchargeConfig;
use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteEncoder;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tariff\PriceRuleId;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class QuoteEngineQuoteJsonTest extends TestCase
{
    public function testTwoQuotesOfTwoHeavyIndoorCubesYieldByteIdenticalJson(): void
    {
        $engine = new QuoteEngine();
        $request = $this->request(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: true,
        );

        $first = $engine->quote($request);
        $second = $engine->quote($request);

        $this->assertInstanceOf(Quote::class, $first);
        $this->assertInstanceOf(Quote::class, $second);
        $this->assertSame(QuoteEncoder::encode($first), QuoteEncoder::encode($second));
        $this->assertSame($first->configHash, $second->configHash);
        $this->assertSame('test-2026', $first->configVersion);
        $this->assertSame(TariffDocument::hash(TestConfig::tariff()), $first->configHash);
        $this->assertSame(
            ['country', 'indoor', 'lines', 'postalCode'],
            array_keys($this->decoded(QuoteEncoder::encode($first))['snapshot']),
        );
    }

    public function testTwoQuoteEngineInstancesYieldTheSameJson(): void
    {
        $request = $this->request(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: true,
        );

        $first = (new QuoteEngine())->quote($request);
        $second = (new QuoteEngine())->quote($request);

        $this->assertInstanceOf(Quote::class, $first);
        $this->assertInstanceOf(Quote::class, $second);
        $this->assertSame(QuoteEncoder::encode($first), QuoteEncoder::encode($second));
    }

    public function testReplayFromSnapshotFieldsYieldsTheSameJson(): void
    {
        $first = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: true,
        );

        $replayed = (new QuoteEngine())->quote(new QuoteRequest(
            $first->snapshot->lines,
            $first->snapshot->postalCode,
            $first->snapshot->country,
            $first->snapshot->indoor,
            TestConfig::tariff(),
        ));

        $this->assertInstanceOf(Quote::class, $replayed);
        $this->assertSame(QuoteEncoder::encode($first), QuoteEncoder::encode($replayed));
    }

    public function testPaddedPostalSnapshotAndJsonKeepDresdenAsString(): void
    {
        $quote = $this->quote(
            [new OrderLine('cube', 100, 100, 100, 1, 1)],
            '  01067  ',
            'de',
        );

        $this->assertSame('01067', $quote->snapshot->postalCode);
        $this->assertSame('DE', $quote->snapshot->country);
        $this->assertInstanceOf(KnownZone::class, $quote->destination);
        $this->assertSame('de-01', $quote->destination->zoneId);

        $json = QuoteEncoder::encode($quote);
        $this->assertStringContainsString('"01067"', $json);
        $decoded = $this->decoded($json);
        $this->assertSame('01067', $decoded['snapshot']['postalCode']);
        $this->assertSame(
            ['country', 'indoor', 'lines', 'postalCode'],
            array_keys($decoded['snapshot']),
        );
    }

    public function testVersionLabelDoesNotChangeHashOrMoney(): void
    {
        $lines = [new OrderLine('cube', 100, 100, 100, 1, 1)];
        $labelled = $this->quote($lines, config: TestConfig::tariff('other-label'));
        $default = $this->quote($lines, config: TestConfig::tariff('test-2026'));

        $this->assertSame($default->configHash, $labelled->configHash);
        $this->assertSame('other-label', $labelled->configVersion);
        $this->assertSame('test-2026', $default->configVersion);
        $this->assertSame($default->totalCents, $labelled->totalCents);
    }

    public function testCustomIndoorCentsChangesHashAndMoneyOnSpeditionIndoor(): void
    {
        $lines = [new OrderLine('pair', 100, 100, 100, 20000, 2)];
        $default = $this->quote($lines, indoor: true);
        $customRates = TestConfig::rates();
        $custom = $this->quote(
            $lines,
            indoor: true,
            config: new TariffConfig(
                'test-2026',
                VolumetricDivisor::fromDimFactorCmKg(5000),
                TestConfig::zones(),
                TestConfig::classification(),
                TestConfig::orderWeightThreshold(),
                new TariffRates(
                    $customRates->base,
                    new SurchargeConfig(
                        $customRates->surcharges->island,
                        new IndoorSurchargeRate(20, 1501),
                    ),
                    $customRates->transit,
                ),
            ),
        );

        $this->assertNotSame($default->configHash, $custom->configHash);
        $this->assertSame(7500, $default->totalCents);
        $this->assertSame(7501, $custom->totalCents);
    }

    public function testHelgolandIsKnownIslandWithSameConfigHashAsDresdenPaket(): void
    {
        $cube = [new OrderLine('cube', 100, 100, 100, 1, 1)];
        $helgoland = $this->quote($cube, '27498', 'DE');
        $dresden = $this->quote($cube, '01067', 'DE');

        $this->assertInstanceOf(KnownZone::class, $helgoland->destination);
        $this->assertSame('de-island', $helgoland->destination->zoneId);
        $this->assertSame(1200, $helgoland->totalCents);
        $this->assertSame(1, preg_match('/^[a-f0-9]{64}$/', $helgoland->configHash));
        $this->assertSame($dresden->configHash, $helgoland->configHash);
        $this->assertSame(TariffDocument::hash(TestConfig::tariff()), $helgoland->configHash);
    }

    public function testAddressRejectsCarryTheSameConfigHashAsSuccessfulPaket(): void
    {
        $cube = [new OrderLine('cube', 100, 100, 100, 1, 1)];
        $paket = $this->quote($cube, '01067', 'DE');
        $expected = TariffDocument::hash(TestConfig::tariff());

        foreach (
            [
                $this->quote($cube, '8001', 'CH'),
                $this->quote($cube, '99999', 'DE'),
                $this->quote($cube, '18565', 'DE'),
            ] as $rejected
        ) {
            $this->assertInstanceOf(Quote::class, $rejected);
            $this->assertInstanceOf(Rejected::class, $rejected->destination);
            $this->assertSame(0, $rejected->totalCents);
            $this->assertSame(1, preg_match('/^[a-f0-9]{64}$/', $rejected->configHash));
            $this->assertSame($expected, $rejected->configHash);
            $this->assertSame($paket->configHash, $rejected->configHash);
        }
    }

    public function testEmptyCartIsValidationFailedWithoutConfigHash(): void
    {
        $result = (new QuoteEngine())->quote(new QuoteRequest(
            [],
            '01067',
            'DE',
            false,
            TestConfig::tariff(),
        ));

        $this->assertInstanceOf(ValidationFailed::class, $result);
    }

    public function testQuantityThreeJsonHasThreeClassifiedBaseLinesAndStableEncode(): void
    {
        $request = $this->request([new OrderLine('cube', 100, 100, 100, 1, 3)]);
        $engine = new QuoteEngine();
        $first = $engine->quote($request);
        $second = $engine->quote($request);

        $this->assertInstanceOf(Quote::class, $first);
        $this->assertInstanceOf(Quote::class, $second);
        $this->assertCount(3, $first->classified);
        $this->assertCount(1, $first->shipments);
        $this->assertCount(3, $first->trace);
        foreach ($first->trace as $line) {
            $this->assertSame(PriceRuleId::Base, $line->ruleId);
        }
        $this->assertSame(QuoteEncoder::encode($first), QuoteEncoder::encode($second));
    }

    public function testLongAndThreeShortJsonOrdersShipmentsByRankAndIsStable(): void
    {
        $request = $this->request([
            new OrderLine('long', 2001, 50, 50, 1, 1),
            new OrderLine('shorts', 667, 50, 50, 1, 3),
        ]);
        $engine = new QuoteEngine();
        $first = $engine->quote($request);
        $second = $engine->quote($request);

        $this->assertInstanceOf(Quote::class, $first);
        $this->assertInstanceOf(Quote::class, $second);
        $this->assertCount(2, $first->shipments);
        $this->assertSame(ShippingClass::Paket, $first->shipments[0]->shipment->class);
        $this->assertSame(ShippingClass::Sperrgut, $first->shipments[1]->shipment->class);
        $decoded = $this->decoded(QuoteEncoder::encode($first));
        $this->assertSame('paket', $decoded['shipments'][0]['shipment']['class']);
        $this->assertSame('sperrgut', $decoded['shipments'][1]['shipment']['class']);
        $this->assertSame(QuoteEncoder::encode($first), QuoteEncoder::encode($second));
    }

    public function testTwoFifteenKilogramPiecesRemainPaketWithTwoBaseSixHundredInJson(): void
    {
        $quote = $this->quote([new OrderLine('pair', 100, 100, 100, 15000, 2)]);

        $this->assertCount(2, $quote->classified);
        $this->assertSame(ShippingClass::Paket, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[1]->class);
        $this->assertNotSame(ShippingClass::Spedition, $quote->classified[0]->class);

        $decoded = $this->decoded(QuoteEncoder::encode($quote));
        $this->assertSame('paket', $decoded['classified'][0]['class']);
        $this->assertSame('paket', $decoded['classified'][1]['class']);
        $this->assertCount(1, $decoded['shipments']);
        $this->assertSame('paket', $decoded['shipments'][0]['shipment']['class']);
        $this->assertSame(600, $decoded['shipments'][0]['lines'][0]['deltaCents']);
        $this->assertSame(600, $decoded['shipments'][0]['lines'][1]['deltaCents']);
        $this->assertSame('base', $decoded['shipments'][0]['lines'][0]['ruleId']);
        $this->assertSame('base', $decoded['shipments'][0]['lines'][1]['ruleId']);
    }

    /**
     * @param list<OrderLine> $lines
     */
    private function quote(
        array $lines,
        string $postalCode = '01067',
        string $country = 'DE',
        bool $indoor = false,
        ?TariffConfig $config = null,
    ): Quote {
        $result = (new QuoteEngine())->quote($this->request(
            $lines,
            $postalCode,
            $country,
            $indoor,
            $config,
        ));
        $this->assertInstanceOf(Quote::class, $result);

        return $result;
    }

    /**
     * @param list<OrderLine> $lines
     */
    private function request(
        array $lines,
        string $postalCode = '01067',
        string $country = 'DE',
        bool $indoor = false,
        ?TariffConfig $config = null,
    ): QuoteRequest {
        return new QuoteRequest(
            $lines,
            $postalCode,
            $country,
            $indoor,
            $config ?? TestConfig::tariff(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decoded(string $json): array
    {
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
