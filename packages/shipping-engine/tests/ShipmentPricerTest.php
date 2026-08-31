<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\BaseRateConfig;
use OxidShipping\Engine\Domain\IndoorSurchargeRate;
use OxidShipping\Engine\Domain\IslandSurchargeRate;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Domain\SurchargeConfig;
use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Domain\TransitEntry;
use OxidShipping\Engine\Domain\TransitTable;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Domain\WeightRateStep;
use OxidShipping\Engine\Domain\WeightRateTable;
use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\Result\PieceRejection;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tariff\PriceRuleId;
use OxidShipping\Engine\Tariff\PricedShipment;
use OxidShipping\Engine\Tariff\ShipmentPricer;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class ShipmentPricerTest extends TestCase
{
    private ShipmentPricer $pricer;

    protected function setUp(): void
    {
        $this->pricer = new ShipmentPricer();
    }

    public function testTwoSpeditionStairsIndoorChargeIndoorOnce(): void
    {
        $stop = $this->stop(
            ShippingClass::Spedition,
            'de-01',
            true,
            [new OrderLine('line-1', 100, 100, 100, 20000, 2)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame(6000, $priced->baseCents);
        $this->assertSame(7500, $priced->totalCents);
        $this->assertCount(1, $this->linesOf($priced, PriceRuleId::Indoor));
        $this->assertSame(1500, $this->linesOf($priced, PriceRuleId::Indoor)[0]->deltaCents);
        $this->assertTraceSums($priced);
    }

    public function testRejectionBesideALiveStopIsNotPriced(): void
    {
        $stop = $this->stop(
            ShippingClass::Spedition,
            'de-01',
            false,
            [new OrderLine('line-1', 100, 100, 100, 20000, 1)],
        );
        $rejection = new PieceRejection(
            'line-x',
            0,
            0,
            new Rejected(RejectReason::ZoneForbidden),
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertInstanceOf(PieceRejection::class, $rejection);
        $this->assertSame(3000, $priced->totalCents);
        $this->assertSame($priced->baseCents, $priced->totalCents);
        $this->assertTraceSums($priced);
    }

    public function testBaseIsPerPieceNotTheGroupAverage(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [
                new OrderLine('light', 100, 100, 100, 1, 1),
                new OrderLine('heavy', 100, 100, 100, 15000, 1),
            ],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame([200, 15000], $this->billables($stop));
        $this->assertSame(1000, $priced->baseCents);
        $this->assertSame(400, $priced->lines[0]->deltaCents);
        $this->assertSame(600, $priced->lines[1]->deltaCents);
        $this->assertTraceSums($priced);
    }

    public function testBaseUsesBillableNotActualGrams(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [new OrderLine('line-1', 200, 200, 200, 1, 1)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame(1, $stop->pieces[0]->piece->actualGrams);
        $this->assertSame(1600, $stop->pieces[0]->piece->billableGrams);
        $this->assertSame(600, $priced->baseCents);
        $this->assertNotSame(400, $priced->baseCents);
        $this->assertTraceSums($priced);
    }

    public function testPaketIndoorHasNoIndoorSurcharge(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-01',
            true,
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertTrue($stop->indoor);
        $this->assertSame([], $this->linesOf($priced, PriceRuleId::Indoor));
        $this->assertSame(400, $priced->totalCents);
        $this->assertTraceSums($priced);
    }

    public function testSperrgutIndoorHasNoIndoorSurcharge(): void
    {
        $stop = $this->stop(
            ShippingClass::Sperrgut,
            'de-01',
            true,
            [new OrderLine('line-1', 2001, 50, 50, 1, 1)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertTrue($stop->indoor);
        $this->assertSame([], $this->linesOf($priced, PriceRuleId::Indoor));
        $this->assertSame(1500, $priced->totalCents);
        $this->assertTraceSums($priced);
    }

    public function testFormerSperrgutUsesSpeditionRate(): void
    {
        $stop = $this->stop(
            ShippingClass::Spedition,
            'de-01',
            false,
            [
                new OrderLine('long', 2001, 50, 50, 1, 1),
                new OrderLine('cubes', 100, 100, 100, 20000, 2),
            ],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame(1001, $stop->pieces[0]->piece->billableGrams);
        $this->assertSame(8800, $priced->baseCents);
        $this->assertSame(2800, $priced->lines[0]->deltaCents);
        $this->assertSame(3000, $priced->lines[1]->deltaCents);
        $this->assertSame(3000, $priced->lines[2]->deltaCents);
        $this->assertTraceSums($priced);
    }

    public function testOverrideSpeditionIndoorChargesIndoorOnce(): void
    {
        $stop = $this->stop(
            ShippingClass::Spedition,
            'de-01',
            true,
            [
                new OrderLine('long', 2001, 50, 50, 1, 1),
                new OrderLine('cubes', 100, 100, 100, 20000, 2),
            ],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame(ShippingClass::Spedition, $stop->class);
        $this->assertCount(1, $this->linesOf($priced, PriceRuleId::Indoor));
        $this->assertSame(1500, $this->linesOf($priced, PriceRuleId::Indoor)[0]->deltaCents);
        $this->assertSame(10300, $priced->totalCents);
        $this->assertTraceSums($priced);
    }

    public function testIslandSurchargeOnceForTwoPaket(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-island',
            false,
            [new OrderLine('line-1', 100, 100, 100, 1, 2)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame(800, $priced->baseCents);
        $this->assertCount(1, $this->linesOf($priced, PriceRuleId::Island));
        $this->assertSame(800, $this->linesOf($priced, PriceRuleId::Island)[0]->deltaCents);
        $this->assertSame(1600, $priced->totalCents);
        $this->assertTraceSums($priced);
    }

    public function testSurchargeOrderFollowsPriorityNotRuleId(): void
    {
        $stop = $this->stop(
            ShippingClass::Spedition,
            'de-island',
            true,
            [new OrderLine('line-1', 100, 100, 100, 20000, 1)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame(PriceRuleId::Base, $priced->lines[0]->ruleId);
        $this->assertSame(PriceRuleId::Island, $priced->lines[1]->ruleId);
        $this->assertSame(PriceRuleId::Indoor, $priced->lines[2]->ruleId);
        $this->assertLessThan(
            PriceRuleId::Island->value,
            PriceRuleId::Indoor->value,
        );
        $this->assertTraceSums($priced);
    }

    public function testSurchargeTraceFollowsPriorityAndCentsFromTheRates(): void
    {
        $stop = $this->stop(
            ShippingClass::Spedition,
            'de-island',
            true,
            [new OrderLine('line-1', 100, 100, 100, 20000, 1)],
        );
        $full = $this->rates();
        $rates = new TariffRates(
            $full->base,
            new SurchargeConfig(
                new IslandSurchargeRate(20, 123, ['de-island']),
                new IndoorSurchargeRate(5, 456),
            ),
            $full->transit,
        );

        $priced = $this->pricer->price($stop, $rates);

        $this->assertSame(PriceRuleId::Base, $priced->lines[0]->ruleId);
        $this->assertSame(PriceRuleId::Indoor, $priced->lines[1]->ruleId);
        $this->assertSame(456, $priced->lines[1]->deltaCents);
        $this->assertSame(PriceRuleId::Island, $priced->lines[2]->ruleId);
        $this->assertSame(123, $priced->lines[2]->deltaCents);
        $this->assertNotSame(1500, $priced->lines[1]->deltaCents);
        $this->assertNotSame(800, $priced->lines[2]->deltaCents);
        $this->assertTraceSums($priced);
    }

    public function testAustriaIsNotIsland(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'at-w',
            false,
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame([], $this->linesOf($priced, PriceRuleId::Island));
        $this->assertSame(400, $priced->totalCents);
        $this->assertTraceSums($priced);
    }

    public function testEmptyPriceAllYieldsEmptyList(): void
    {
        $this->assertSame([], $this->pricer->priceAll([], $this->rates()));
    }

    public function testMissingWeightStepIsProgrammerErrorNotZero(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
        );
        $rates = $this->ratesWithBase(
            WeightRateTable::fromEntries([new WeightRateStep(100, 400)]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No weight rate covers the billable grams.');
        $this->pricer->price($stop, $rates);
    }

    public function testMissingTransitCellIsProgrammerErrorNotZero(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
        );
        $rates = new TariffRates(
            $this->rates()->base,
            $this->rates()->surcharges,
            TransitTable::fromEntries([
                new TransitEntry(ShippingClass::Paket, 'de-hh', 2),
            ]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No transit days for this class and zone.');
        $this->pricer->price($stop, $rates);
    }

    public function testTwoCallsWithReversedPiecesYieldTheSameTrace(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('line-a', 100, 100, 100, 1, 1),
                new OrderLine('line-b', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );
        $forward = new Shipment(ShippingClass::Paket, 'de-01', false, $classified);
        $reversed = new Shipment(
            ShippingClass::Paket,
            'de-01',
            false,
            [$classified[1], $classified[0]],
        );

        $first = $this->pricer->price($forward, $this->rates());
        $second = $this->pricer->price($reversed, $this->rates());

        $this->assertSame($this->traceSignature($first), $this->traceSignature($second));
        $this->assertSame($first->transitDays, $second->transitDays);
        $this->assertTraceSums($first);
        $this->assertTraceSums($second);
    }

    public function testMainlandPaketCurbHasOnlyBaseLines(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertCount(1, $priced->lines);
        $this->assertSame(PriceRuleId::Base, $priced->lines[0]->ruleId);
        $this->assertSame([], $this->linesOf($priced, PriceRuleId::Island));
        $this->assertSame([], $this->linesOf($priced, PriceRuleId::Indoor));
        $this->assertTraceSums($priced);
    }

    public function testPriceAllReadsZoneFromEachStop(): void
    {
        $mainland = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [new OrderLine('line-a', 100, 100, 100, 1, 1)],
        );
        $island = $this->stop(
            ShippingClass::Paket,
            'de-island',
            false,
            [new OrderLine('line-b', 100, 100, 100, 1, 1)],
        );

        $priced = $this->pricer->priceAll([$mainland, $island], $this->rates());

        $this->assertCount(2, $priced);
        $this->assertSame(2, $priced[0]->transitDays);
        $this->assertSame(4, $priced[1]->transitDays);
        $this->assertSame([], $this->linesOf($priced[0], PriceRuleId::Island));
        $this->assertCount(1, $this->linesOf($priced[1], PriceRuleId::Island));
        $this->assertSame(800, $this->linesOf($priced[1], PriceRuleId::Island)[0]->deltaCents);
        $this->assertSame(400, $priced[0]->baseCents);
        $this->assertSame(400, $priced[1]->baseCents);
        $this->assertTraceSums($priced[0]);
        $this->assertTraceSums($priced[1]);
    }

    public function testPriceAllDoesNotReorderShipments(): void
    {
        $spedition = $this->stop(
            ShippingClass::Spedition,
            'de-01',
            false,
            [new OrderLine('line-a', 100, 100, 100, 20000, 1)],
        );
        $sperrgut = $this->stop(
            ShippingClass::Sperrgut,
            'de-01',
            false,
            [new OrderLine('line-b', 2001, 50, 50, 1, 1)],
        );

        $priced = $this->pricer->priceAll([$spedition, $sperrgut], $this->rates());

        $this->assertSame(ShippingClass::Spedition, $priced[0]->shipment->class);
        $this->assertSame(ShippingClass::Sperrgut, $priced[1]->shipment->class);
    }

    public function testExactlyTwentyThousandGramsIsPaketSixHundred(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [new OrderLine('line-1', 100, 100, 100, 20000, 1)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame(20000, $stop->pieces[0]->piece->billableGrams);
        $this->assertSame(600, $priced->baseCents);
        $this->assertNotSame(3000, $priced->baseCents);
        $this->assertTraceSums($priced);
    }

    public function testBaseCoordinatesAreFieldsMatchingPieces(): void
    {
        $stop = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [new OrderLine('line-1', 100, 100, 100, 1, 2)],
        );

        $priced = $this->pricer->price($stop, $this->rates());

        $this->assertSame(400, $priced->lines[0]->deltaCents);
        $this->assertSame(400, $priced->lines[1]->deltaCents);
        $this->assertSame($stop->pieces[0]->piece->lineIndex, $priced->lines[0]->lineIndex);
        $this->assertSame($stop->pieces[0]->piece->pieceIndex, $priced->lines[0]->pieceIndex);
        $this->assertSame($stop->pieces[1]->piece->lineIndex, $priced->lines[1]->lineIndex);
        $this->assertSame($stop->pieces[1]->piece->pieceIndex, $priced->lines[1]->pieceIndex);
        $this->assertTraceSums($priced);
    }

    public function testPriceAllReadsClassFromEachStop(): void
    {
        $paket = $this->stop(
            ShippingClass::Paket,
            'de-01',
            false,
            [new OrderLine('line-a', 100, 100, 100, 1, 1)],
        );
        $sperrgut = $this->stop(
            ShippingClass::Sperrgut,
            'de-01',
            false,
            [new OrderLine('line-b', 2001, 50, 50, 1, 1)],
        );

        $priced = $this->pricer->priceAll([$paket, $sperrgut], $this->rates());

        $this->assertSame(400, $priced[0]->baseCents);
        $this->assertSame(1500, $priced[1]->baseCents);
        $this->assertSame(2, $priced[0]->transitDays);
        $this->assertSame(3, $priced[1]->transitDays);
        $this->assertTraceSums($priced[0]);
        $this->assertTraceSums($priced[1]);
    }

    public function testPriceAllReadsIndoorFromEachStop(): void
    {
        $indoor = $this->stop(
            ShippingClass::Spedition,
            'de-01',
            true,
            [new OrderLine('line-a', 100, 100, 100, 20000, 1)],
        );
        $curb = $this->stop(
            ShippingClass::Spedition,
            'de-01',
            false,
            [new OrderLine('line-b', 100, 100, 100, 20000, 1)],
        );

        $priced = $this->pricer->priceAll([$indoor, $curb], $this->rates());

        $this->assertCount(1, $this->linesOf($priced[0], PriceRuleId::Indoor));
        $this->assertSame(1500, $this->linesOf($priced[0], PriceRuleId::Indoor)[0]->deltaCents);
        $this->assertSame([], $this->linesOf($priced[1], PriceRuleId::Indoor));
        $this->assertTraceSums($priced[0]);
        $this->assertTraceSums($priced[1]);
    }

    /**
     * @param list<OrderLine> $lines
     */
    private function stop(
        ShippingClass $class,
        string $zoneId,
        bool $indoor,
        array $lines,
    ): Shipment {
        $classes = array_fill(0, $this->expandedCount($lines), $class);

        return new Shipment($class, $zoneId, $indoor, $this->classified($lines, $classes));
    }

    /**
     * @param list<OrderLine> $lines
     */
    private function expandedCount(array $lines): int
    {
        $count = 0;
        foreach ($lines as $line) {
            $count += $line->quantity;
        }

        return $count;
    }

    /**
     * @param list<OrderLine> $lines
     * @param list<ShippingClass> $classes
     * @return list<ClassifiedPiece>
     */
    private function classified(array $lines, array $classes): array
    {
        $pieces = (new PieceFactory())->expand(
            $lines,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $this->assertCount(count($classes), $pieces);

        $classified = [];
        foreach ($pieces as $index => $piece) {
            $classified[] = new ClassifiedPiece($piece, $classes[$index]);
        }

        return $classified;
    }

    private function rates(): TariffRates
    {
        return TestConfig::rates();
    }

    private function ratesWithBase(WeightRateTable $paket): TariffRates
    {
        $full = $this->rates();

        return new TariffRates(
            new BaseRateConfig($paket, $full->base->sperrgut, $full->base->spedition),
            $full->surcharges,
            $full->transit,
        );
    }

    /**
     * @return list<\OxidShipping\Engine\Tariff\PriceLine>
     */
    private function linesOf(PricedShipment $priced, PriceRuleId $ruleId): array
    {
        $matched = [];
        foreach ($priced->lines as $line) {
            if ($line->ruleId === $ruleId) {
                $matched[] = $line;
            }
        }

        return $matched;
    }

    private function assertTraceSums(PricedShipment $priced): void
    {
        $sum = 0;
        foreach ($priced->lines as $line) {
            $sum += $line->deltaCents;
        }
        $this->assertSame($priced->totalCents, $sum);
    }

    /**
     * @return list<int>
     */
    private function billables(Shipment $stop): array
    {
        $grams = [];
        foreach ($stop->pieces as $item) {
            $grams[] = $item->piece->billableGrams;
        }

        return $grams;
    }

    /**
     * @return list<array{0: PriceRuleId, 1: int, 2: ?int, 3: ?int}>
     */
    private function traceSignature(PricedShipment $priced): array
    {
        $signature = [];
        foreach ($priced->lines as $line) {
            $signature[] = [$line->ruleId, $line->deltaCents, $line->lineIndex, $line->pieceIndex];
        }

        return $signature;
    }
}
