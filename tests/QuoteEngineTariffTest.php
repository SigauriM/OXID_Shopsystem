<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteEncoder;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tariff\PriceRuleId;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class QuoteEngineTariffTest extends TestCase
{
    private QuoteEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new QuoteEngine();
    }

    public function testTwoTwentyKilogramCubesIndoorChargeIndoorOnce(): void
    {
        $quote = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: true,
        );

        $this->assertCount(1, $quote->shipments);
        $this->assertSame(ShippingClass::Spedition, $quote->shipments[0]->shipment->class);
        $this->assertTrue($quote->shipments[0]->shipment->indoor);
        $this->assertSame(6000, $quote->shipments[0]->baseCents);
        $this->assertCount(1, $this->linesOf($quote, PriceRuleId::Indoor));
        $this->assertSame(1500, $this->linesOf($quote, PriceRuleId::Indoor)[0]->deltaCents);
        $this->assertSame(7500, $quote->totalCents);
        $this->assertTraceSums($quote);
    }

    public function testTwoTwentyKilogramCubesCurbHaveNoIndoorLine(): void
    {
        $quote = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: false,
        );

        $this->assertSame([], $this->linesOf($quote, PriceRuleId::Indoor));
        $this->assertSame(6000, $quote->totalCents);
        $this->assertTraceSums($quote);
    }

    public function testSingleTwentyKilogramCubeIsPaketSixHundredNotSpedition(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 20000);

        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->shipment->class);
        $this->assertNotSame(ShippingClass::Spedition, $quote->shipments[0]->shipment->class);
        $this->assertSame(600, $quote->shipments[0]->baseCents);
        $this->assertNotSame(3000, $quote->shipments[0]->baseCents);
        $this->assertSame(600, $quote->totalCents);
        $this->assertTraceSums($quote);
    }

    public function testVolumetricCubeUsesBillableStepNotActualGrams(): void
    {
        $quote = $this->quoteLine(200, 200, 200, 1);

        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->shipment->class);
        $this->assertSame(1, $quote->classified[0]->piece->actualGrams);
        $this->assertSame(1600, $quote->classified[0]->piece->billableGrams);
        $this->assertSame(600, $quote->shipments[0]->baseCents);
        $this->assertNotSame(400, $quote->shipments[0]->baseCents);
        $this->assertTraceSums($quote);
    }

    public function testPaketIndoorKeepsKeyAndHasNoIndoorSurcharge(): void
    {
        $quote = $this->quote(
            [new OrderLine('cube', 100, 100, 100, 1, 1)],
            indoor: true,
        );

        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->shipment->class);
        $this->assertTrue($quote->shipments[0]->shipment->indoor);
        $this->assertSame([], $this->linesOf($quote, PriceRuleId::Indoor));
        $this->assertSame(400, $quote->shipments[0]->baseCents);
        $this->assertSame(400, $quote->totalCents);
        $this->assertTraceSums($quote);
    }

    public function testSperrgutIndoorHasNoIndoorSurcharge(): void
    {
        $quote = $this->quote(
            [new OrderLine('long', 2001, 50, 50, 1, 1)],
            indoor: true,
        );

        $this->assertSame(ShippingClass::Sperrgut, $quote->shipments[0]->shipment->class);
        $this->assertTrue($quote->shipments[0]->shipment->indoor);
        $this->assertSame([], $this->linesOf($quote, PriceRuleId::Indoor));
        $this->assertSame(1500, $quote->shipments[0]->baseCents);
        $this->assertSame(1500, $quote->totalCents);
        $this->assertTraceSums($quote);
    }

    public function testFormerSperrgutPlusTwoCubesIndoorIsOneSpeditionStop(): void
    {
        $quote = $this->quote(
            [
                new OrderLine('long', 2001, 50, 50, 1, 1),
                new OrderLine('cubes', 100, 100, 100, 20000, 2),
            ],
            indoor: true,
        );

        $this->assertCount(1, $quote->shipments);
        $this->assertSame(ShippingClass::Spedition, $quote->shipments[0]->shipment->class);
        $this->assertSame(8800, $quote->shipments[0]->baseCents);
        $this->assertCount(1, $this->linesOf($quote, PriceRuleId::Indoor));
        $this->assertSame(1500, $this->linesOf($quote, PriceRuleId::Indoor)[0]->deltaCents);
        $this->assertSame(10300, $quote->totalCents);
        $this->assertTraceSums($quote);
    }

    public function testHelgolandCubeIsIslandSurchargeOnceWithIslandTransit(): void
    {
        $quote = $this->quote(
            [new OrderLine('cube', 100, 100, 100, 1, 1)],
            '27498',
            'DE',
        );

        $this->assertInstanceOf(KnownZone::class, $quote->destination);
        $this->assertSame('de-island', $quote->destination->zoneId);
        $this->assertSame('de-island', $quote->shipments[0]->shipment->zoneId);
        $this->assertCount(1, $this->linesOf($quote, PriceRuleId::Island));
        $this->assertSame(800, $this->linesOf($quote, PriceRuleId::Island)[0]->deltaCents);
        $this->assertSame(400, $quote->shipments[0]->baseCents);
        $this->assertSame(1200, $quote->totalCents);
        $this->assertSame(4, $quote->shipments[0]->transitDays);
        $this->assertTraceSums($quote);
    }

    public function testTwoHelgolandCubesChargeIslandOnce(): void
    {
        $quote = $this->quote(
            [new OrderLine('cube', 100, 100, 100, 1, 2)],
            '27498',
            'DE',
        );

        $this->assertCount(1, $this->linesOf($quote, PriceRuleId::Island));
        $this->assertSame(800, $quote->shipments[0]->baseCents);
        $this->assertSame(1600, $quote->totalCents);
        $this->assertTraceSums($quote);
    }

    public function testViennaIsNotIsland(): void
    {
        $quote = $this->quote(
            [new OrderLine('cube', 100, 100, 100, 1, 1)],
            '1010',
            'AT',
        );

        $this->assertSame('at-w', $quote->shipments[0]->shipment->zoneId);
        $this->assertSame([], $this->linesOf($quote, PriceRuleId::Island));
        $this->assertSame(400, $quote->shipments[0]->baseCents);
        $this->assertSame(400, $quote->totalCents);
        $this->assertSame(2, $quote->shipments[0]->transitDays);
        $this->assertTraceSums($quote);
    }

    public function testLightAndHeavyPaketSumBasesPerPiece(): void
    {
        $quote = $this->quote([
            new OrderLine('light', 100, 100, 100, 1, 1),
            new OrderLine('heavy', 100, 100, 100, 15000, 1),
        ]);

        $this->assertCount(1, $quote->shipments);
        $this->assertSame(1000, $quote->shipments[0]->baseCents);
        $this->assertSame(1000, $quote->totalCents);
        $this->assertTraceSums($quote);
    }

    public function testLongAndThreeShortAreTwoPricedShipmentsWithDaysAndTotals(): void
    {
        $quote = $this->quote([
            new OrderLine('long', 2001, 50, 50, 1, 1),
            new OrderLine('shorts', 667, 50, 50, 1, 3),
        ]);

        $this->assertCount(2, $quote->shipments);
        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->shipment->class);
        $this->assertSame(1200, $quote->shipments[0]->baseCents);
        $this->assertSame(2, $quote->shipments[0]->transitDays);
        $this->assertSame(ShippingClass::Sperrgut, $quote->shipments[1]->shipment->class);
        $this->assertSame(1500, $quote->shipments[1]->baseCents);
        $this->assertSame(3, $quote->shipments[1]->transitDays);
        $this->assertSame(2700, $quote->totalCents);
        $this->assertSame(334, $quote->classified[1]->piece->billableGrams);
        $this->assertTraceSums($quote);
        $this->assertSame(
            $quote->shipments[0]->totalCents + $quote->shipments[1]->totalCents,
            $quote->totalCents,
        );
    }

    public function testFifteenMetreCubeAtFactorOneThousandIsSpeditionSixThousand(): void
    {
        $result = $this->engine->quote(new QuoteRequest(
            lines: [new OrderLine('cube', 15000, 15000, 15000, 1, 1)],
            postalCode: '01067',
            country: 'DE',
            indoor: false,
            config: TestConfig::tariff(dimFactorCmKg: 1000),
        ));
        $this->assertInstanceOf(Quote::class, $result);

        $this->assertSame(ShippingClass::Spedition, $result->shipments[0]->shipment->class);
        $this->assertSame(3_375_000_000, $result->classified[0]->piece->billableGrams);
        $this->assertSame(6000, $result->shipments[0]->baseCents);
        $this->assertNotSame(900, $result->shipments[0]->baseCents);
        $this->assertSame(6000, $result->totalCents);
        $this->assertTraceSums($result);
    }

    public function testForbiddenZoneHasZeroCentsAndEmptyTrace(): void
    {
        $quote = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 1, 2)],
            '18565',
            'DE',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::ZoneForbidden, $quote->destination->reason);
        $this->assertSame([], $quote->shipments);
        $this->assertSame(0, $quote->totalCents);
        $this->assertSame([], $quote->trace);
    }

    public function testUnservedCountryHasZeroCents(): void
    {
        $quote = $this->quote(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            '8001',
            'CH',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::CountryNotServed, $quote->destination->reason);
        $this->assertSame(0, $quote->totalCents);
        $this->assertSame([], $quote->trace);
    }

    public function testUnknownZoneHasZeroCents(): void
    {
        $quote = $this->quote(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            '99999',
            'DE',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::UnknownZone, $quote->destination->reason);
        $this->assertSame(0, $quote->totalCents);
        $this->assertSame([], $quote->trace);
    }

    public function testTwoQuotesOfTwoHeavyIndoorCubesYieldTheSameMoneyTrace(): void
    {
        $lines = [new OrderLine('pair', 100, 100, 100, 20000, 2)];

        $first = $this->quote($lines, indoor: true);
        $second = $this->quote($lines, indoor: true);

        $this->assertSame($first->totalCents, $second->totalCents);
        $this->assertSame($this->traceSignature($first), $this->traceSignature($second));
        $this->assertSame(QuoteEncoder::encode($first), QuoteEncoder::encode($second));
    }

    /**
     * @param list<OrderLine> $lines
     */
    private function quote(
        array $lines,
        string $postalCode = '01067',
        string $country = 'DE',
        bool $indoor = false,
    ): Quote {
        $result = $this->engine->quote(new QuoteRequest(
            lines: $lines,
            postalCode: $postalCode,
            country: $country,
            indoor: $indoor,
            config: TestConfig::tariff(),
        ));

        $this->assertInstanceOf(Quote::class, $result);

        return $result;
    }

    private function quoteLine(
        int $lengthMm,
        int $widthMm,
        int $heightMm,
        int $weightGrams,
        int $quantity = 1,
    ): Quote {
        return $this->quote([
            new OrderLine('line-1', $lengthMm, $widthMm, $heightMm, $weightGrams, $quantity),
        ]);
    }

    /**
     * @return list<\OxidShipping\Engine\Tariff\PriceLine>
     */
    private function linesOf(Quote $quote, PriceRuleId $ruleId): array
    {
        $matched = [];
        foreach ($quote->trace as $line) {
            if ($line->ruleId === $ruleId) {
                $matched[] = $line;
            }
        }

        return $matched;
    }

    private function assertTraceSums(Quote $quote): void
    {
        $sum = 0;
        foreach ($quote->trace as $line) {
            $sum += $line->deltaCents;
        }
        $this->assertSame($quote->totalCents, $sum);
        foreach ($quote->shipments as $priced) {
            $stopSum = 0;
            foreach ($priced->lines as $line) {
                $stopSum += $line->deltaCents;
            }
            $this->assertSame($priced->totalCents, $stopSum);
        }
    }

    /**
     * @return list<array{0: PriceRuleId, 1: int, 2: ?int, 3: ?int}>
     */
    private function traceSignature(Quote $quote): array
    {
        $signature = [];
        foreach ($quote->trace as $line) {
            $signature[] = [$line->ruleId, $line->deltaCents, $line->lineIndex, $line->pieceIndex];
        }

        return $signature;
    }
}
