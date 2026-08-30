<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class QuoteEngineGroupingTest extends TestCase
{
    private QuoteEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new QuoteEngine();
    }

    public function testSmallCubeIsOnePaketShipmentOnDe01Curb(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 1);

        $this->assertInstanceOf(KnownZone::class, $quote->destination);
        $this->assertSame('de-01', $quote->destination->zoneId);
        $this->assertSame([], $quote->rejections);
        $this->assertCount(1, $quote->shipments);
        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->class);
        $this->assertSame('de-01', $quote->shipments[0]->zoneId);
        $this->assertFalse($quote->shipments[0]->indoor);
        $this->assertCount(1, $quote->shipments[0]->pieces);
    }

    public function testQuantityThreeIsOneShipmentWithThreePieces(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 1, 3);

        $this->assertCount(1, $quote->shipments);
        $this->assertCount(3, $quote->shipments[0]->pieces);
        $this->assertSame(
            [[0, 0], [0, 1], [0, 2]],
            $this->coordinates($quote->shipments[0]),
        );
    }

    public function testTwoFifteenKilogramPiecesAreOnePaketShipmentNotSpedition(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 15000, 2);

        $this->assertCount(2, $quote->classified);
        $this->assertCount(1, $quote->shipments);
        $this->assertCount(2, $quote->shipments[0]->pieces);
        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->class);
        $this->assertNotSame(ShippingClass::Spedition, $quote->shipments[0]->class);
    }

    public function testOneLongAndThreeShortAreTwoShipmentsPaketThenSperrgut(): void
    {
        $quote = $this->quote([
            new OrderLine('long', 2001, 50, 50, 1, 1),
            new OrderLine('shorts', 667, 50, 50, 1, 3),
        ]);

        $this->assertCount(2, $quote->shipments);
        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->class);
        $this->assertCount(3, $quote->shipments[0]->pieces);
        $this->assertSame(ShippingClass::Sperrgut, $quote->shipments[1]->class);
        $this->assertCount(1, $quote->shipments[1]->pieces);
        $this->assertSame('de-01', $quote->shipments[0]->zoneId);
        $this->assertSame('de-01', $quote->shipments[1]->zoneId);
        $this->assertFalse($quote->shipments[0]->indoor);
        $this->assertFalse($quote->shipments[1]->indoor);
        $this->assertSame(
            2001,
            $quote->classified[1]->piece->dimensions->lengthMm
            + $quote->classified[2]->piece->dimensions->lengthMm
            + $quote->classified[3]->piece->dimensions->lengthMm,
        );
    }

    public function testLightAndHeavyPaketShareOneShipmentWithDifferentBillable(): void
    {
        $quote = $this->quote([
            new OrderLine('light', 100, 100, 100, 1, 1),
            new OrderLine('heavy', 100, 100, 100, 15000, 1),
        ]);

        $this->assertCount(1, $quote->shipments);
        $this->assertCount(2, $quote->shipments[0]->pieces);
        $this->assertNotSame(
            $quote->shipments[0]->pieces[0]->piece->billableGrams,
            $quote->shipments[0]->pieces[1]->piece->billableGrams,
        );
    }

    public function testTwoPaketLinesWithDifferentLineIdsAreOneShipment(): void
    {
        $quote = $this->quote([
            new OrderLine('sku-a', 100, 100, 100, 1, 1),
            new OrderLine('sku-b', 100, 100, 100, 1, 1),
        ]);

        $this->assertNotSame($quote->classified[0]->piece->lineId, $quote->classified[1]->piece->lineId);
        $this->assertCount(1, $quote->shipments);
        $this->assertCount(2, $quote->shipments[0]->pieces);
        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->class);
    }

    public function testTwoTwentyKilogramCubesAreOneSpeditionShipmentAfterOverride(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 20000, 2);

        $this->assertCount(1, $quote->shipments);
        $this->assertSame(ShippingClass::Spedition, $quote->shipments[0]->class);
        $this->assertCount(2, $quote->shipments[0]->pieces);
        $this->assertSame(ShippingClass::Spedition, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Spedition, $quote->classified[1]->class);
    }

    public function testSperrgutAndTwoHeavyPaketBecomeOneSpeditionShipment(): void
    {
        $quote = $this->quote([
            new OrderLine('long', 2001, 50, 50, 1, 1),
            new OrderLine('cubes', 100, 100, 100, 20000, 2),
        ]);

        $this->assertCount(1, $quote->shipments);
        $this->assertSame(ShippingClass::Spedition, $quote->shipments[0]->class);
        $this->assertCount(3, $quote->shipments[0]->pieces);
    }

    public function testIndoorFlagOnTwoHeavyCubesIsCopiedOntoTheSingleShipment(): void
    {
        $curb = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: false,
        );
        $indoor = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: true,
        );

        $this->assertCount(1, $curb->shipments);
        $this->assertCount(1, $indoor->shipments);
        $this->assertFalse($curb->shipments[0]->indoor);
        $this->assertTrue($indoor->shipments[0]->indoor);
        $this->assertSame(ShippingClass::Spedition, $curb->shipments[0]->class);
        $this->assertSame(ShippingClass::Spedition, $indoor->shipments[0]->class);
    }

    public function testSameCubeInAustriaAndGermanyPutsZoneIdOnTheShipmentKey(): void
    {
        $austria = $this->quote(
            [new OrderLine('cube', 100, 100, 100, 1, 1)],
            '1010',
            'AT',
        );
        $germany = $this->quote(
            [new OrderLine('cube', 100, 100, 100, 1, 1)],
            '01067',
            'DE',
        );

        $this->assertCount(1, $austria->shipments);
        $this->assertCount(1, $germany->shipments);
        $this->assertSame('at-w', $austria->shipments[0]->zoneId);
        $this->assertSame('de-01', $germany->shipments[0]->zoneId);
    }

    public function testSperrgutLineBeforePaketStillShipsPaketFirst(): void
    {
        $quote = $this->quote([
            new OrderLine('long', 2001, 50, 50, 1, 1),
            new OrderLine('cube', 100, 100, 100, 1, 1),
        ]);

        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->class);
        $this->assertSame(ShippingClass::Sperrgut, $quote->shipments[1]->class);
    }

    public function testSperrgutAndGirthSpeditionSortByRankNotBacking(): void
    {
        $quote = $this->quote([
            new OrderLine('spedition', 1999, 791, 10, 1, 1),
            new OrderLine('sperrgut', 2001, 50, 50, 1, 1),
        ]);

        $this->assertSame(3601, $quote->classified[0]->piece->dimensions->girthMm());
        $this->assertSame(ShippingClass::Spedition, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Sperrgut, $quote->classified[1]->class);
        $this->assertSame(
            2,
            $quote->classified[0]->piece->actualGrams + $quote->classified[1]->piece->actualGrams,
        );
        $this->assertCount(2, $quote->shipments);
        $this->assertSame(ShippingClass::Sperrgut, $quote->shipments[0]->class);
        $this->assertSame(ShippingClass::Spedition, $quote->shipments[1]->class);
    }

    public function testForbiddenZoneHasNoShipments(): void
    {
        $quote = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 1, 2)],
            '18565',
            'DE',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::ZoneForbidden, $quote->destination->reason);
        $this->assertSame([], $quote->shipments);
        $this->assertSame([], $quote->classified);
        $this->assertSame(
            [[0, 0], [0, 1]],
            array_map(
                static fn ($rejection): array => [$rejection->lineIndex, $rejection->pieceIndex],
                $quote->rejections,
            ),
        );
    }

    public function testUnservedCountryHasNoShipments(): void
    {
        $quote = $this->quote(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            '8001',
            'CH',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::CountryNotServed, $quote->destination->reason);
        $this->assertSame([], $quote->shipments);
    }

    public function testUnknownZoneHasNoShipments(): void
    {
        $quote = $this->quote(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            '99999',
            'DE',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::UnknownZone, $quote->destination->reason);
        $this->assertSame([], $quote->shipments);
    }

    public function testTwoQuotesOfOneLongAndThreeShortYieldTheSameShipmentKeysAndCoordinates(): void
    {
        $lines = [
            new OrderLine('long', 2001, 50, 50, 1, 1),
            new OrderLine('shorts', 667, 50, 50, 1, 3),
        ];

        $first = $this->quote($lines);
        $second = $this->quote($lines);

        $this->assertSame($this->keys($first->shipments), $this->keys($second->shipments));
        $this->assertSame(
            array_map($this->coordinates(...), $first->shipments),
            array_map($this->coordinates(...), $second->shipments),
        );
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
     * @param list<Shipment> $shipments
     * @return list<array{0: ShippingClass, 1: string, 2: bool}>
     */
    private function keys(array $shipments): array
    {
        $keys = [];
        foreach ($shipments as $shipment) {
            $keys[] = [$shipment->class, $shipment->zoneId, $shipment->indoor];
        }

        return $keys;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function coordinates(Shipment $shipment): array
    {
        $coordinates = [];
        foreach ($shipment->pieces as $item) {
            $coordinates[] = [$item->piece->lineIndex, $item->piece->pieceIndex];
        }

        return $coordinates;
    }
}
