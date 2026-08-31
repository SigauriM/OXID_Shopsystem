<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Grouping\GroupablePiece;
use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\Grouping\ShipmentGrouper;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class ShipmentGrouperTest extends TestCase
{
    private ShipmentGrouper $grouper;

    protected function setUp(): void
    {
        $this->grouper = new ShipmentGrouper();
    }

    public function testTwoSpeditionInDifferentZonesAreTwoShipmentsDe01ThenDeHh(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('dresden', 100, 100, 100, 1, 1),
                new OrderLine('hamburg', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Spedition, ShippingClass::Spedition],
        );

        $shipments = $this->grouper->group([
            new GroupablePiece($classified[0], 'de-01', false),
            new GroupablePiece($classified[1], 'de-hh', false),
        ]);

        $this->assertSame(
            [
                [ShippingClass::Spedition, 'de-01', false],
                [ShippingClass::Spedition, 'de-hh', false],
            ],
            $this->keys($shipments),
        );
        $this->assertCount(1, $shipments[0]->pieces);
        $this->assertCount(1, $shipments[1]->pieces);
    }

    public function testFourDifferentKeysInReverseInputYieldFourShipmentsInCanonicalOrder(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('paket-de01-curb', 100, 100, 100, 1, 1),
                new OrderLine('paket-de01-indoor', 100, 100, 100, 1, 1),
                new OrderLine('paket-dehh-curb', 100, 100, 100, 1, 1),
                new OrderLine('sperrgut-de01-curb', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket, ShippingClass::Paket, ShippingClass::Sperrgut],
        );

        $shipments = $this->grouper->group([
            new GroupablePiece($classified[3], 'de-01', false),
            new GroupablePiece($classified[2], 'de-hh', false),
            new GroupablePiece($classified[1], 'de-01', true),
            new GroupablePiece($classified[0], 'de-01', false),
        ]);

        $this->assertSame(
            [
                [ShippingClass::Paket, 'de-01', false],
                [ShippingClass::Paket, 'de-01', true],
                [ShippingClass::Paket, 'de-hh', false],
                [ShippingClass::Sperrgut, 'de-01', false],
            ],
            $this->keys($shipments),
        );
    }

    public function testPaketWithDifferentIndoorAreTwoShipmentsCurbFirst(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('indoor', 100, 100, 100, 1, 1),
                new OrderLine('curb', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );

        $shipments = $this->grouper->group([
            new GroupablePiece($classified[0], 'de-01', true),
            new GroupablePiece($classified[1], 'de-01', false),
        ]);

        $this->assertSame(
            [
                [ShippingClass::Paket, 'de-01', false],
                [ShippingClass::Paket, 'de-01', true],
            ],
            $this->keys($shipments),
        );
    }

    public function testOneKeyWithThreePiecesIsOneShipmentSortedByCoordinate(): void
    {
        $classified = $this->classified(
            [new OrderLine('line-1', 100, 100, 100, 1, 3)],
            [ShippingClass::Paket, ShippingClass::Paket, ShippingClass::Paket],
        );

        $shipments = $this->grouper->group([
            new GroupablePiece($classified[2], 'de-01', false),
            new GroupablePiece($classified[0], 'de-01', false),
            new GroupablePiece($classified[1], 'de-01', false),
        ]);

        $this->assertCount(1, $shipments);
        $this->assertSame([ShippingClass::Paket, 'de-01', false], $this->keys($shipments)[0]);
        $this->assertSame(
            [[0, 0], [0, 1], [0, 2]],
            $this->coordinates($shipments[0]),
        );
    }

    public function testDifferentBillableDoesNotSplitTheGroup(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('light', 100, 100, 100, 1, 1),
                new OrderLine('heavy', 100, 100, 100, 15000, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );

        $shipments = $this->grouper->group([
            new GroupablePiece($classified[0], 'de-01', false),
            new GroupablePiece($classified[1], 'de-01', false),
        ]);

        $this->assertCount(1, $shipments);
        $this->assertCount(2, $shipments[0]->pieces);
        $this->assertSame(1, $classified[0]->piece->actualGrams);
        $this->assertSame(15000, $classified[1]->piece->actualGrams);
        $this->assertNotSame(
            $shipments[0]->pieces[0]->piece->billableGrams,
            $shipments[0]->pieces[1]->piece->billableGrams,
        );
    }

    public function testDifferentLineIdsWithTheSameKeyAreOneShipment(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('sku-a', 100, 100, 100, 1, 1),
                new OrderLine('sku-b', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );

        $this->assertNotSame($classified[0]->piece->lineId, $classified[1]->piece->lineId);

        $shipments = $this->grouper->group([
            new GroupablePiece($classified[0], 'de-01', false),
            new GroupablePiece($classified[1], 'de-01', false),
        ]);

        $this->assertCount(1, $shipments);
        $this->assertCount(2, $shipments[0]->pieces);
    }

    public function testEmptyInputYieldsEmptyList(): void
    {
        $this->assertSame([], $this->grouper->group([]));
    }

    public function testDuplicateCoordinatesInInputIsProgrammerError(): void
    {
        $classified = $this->classified(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            [ShippingClass::Paket],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate piece coordinate.');
        $this->grouper->group([
            new GroupablePiece($classified[0], 'de-01', false),
            new GroupablePiece($classified[0], 'de-hh', false),
        ]);
    }

    public function testTwoCallsWithPermutedInputYieldTheSameKeysAndCoordinates(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('a', 100, 100, 100, 1, 1),
                new OrderLine('b', 100, 100, 100, 1, 1),
                new OrderLine('c', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Sperrgut, ShippingClass::Spedition],
        );

        $forward = [
            new GroupablePiece($classified[0], 'de-hh', true),
            new GroupablePiece($classified[1], 'de-01', false),
            new GroupablePiece($classified[2], 'at-w', false),
        ];
        $reversed = array_reverse($forward);

        $first = $this->grouper->group($forward);
        $second = $this->grouper->group($reversed);

        $this->assertSame($this->keys($first), $this->keys($second));
        $this->assertSame(
            array_map($this->coordinates(...), $first),
            array_map($this->coordinates(...), $second),
        );
    }

    public function testSpeditionThenPaketOnInputYieldsPaketThenSpedition(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('spedition', 100, 100, 100, 1, 1),
                new OrderLine('paket', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Spedition, ShippingClass::Paket],
        );

        $shipments = $this->grouper->group([
            new GroupablePiece($classified[0], 'de-01', false),
            new GroupablePiece($classified[1], 'de-01', false),
        ]);

        $this->assertSame(
            [
                [ShippingClass::Paket, 'de-01', false],
                [ShippingClass::Spedition, 'de-01', false],
            ],
            $this->keys($shipments),
        );
    }

    public function testSperrgutSortsBeforeSpeditionWhenBackingWouldReverseThem(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('spedition', 100, 100, 100, 1, 1),
                new OrderLine('sperrgut', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Spedition, ShippingClass::Sperrgut],
        );

        $shipments = $this->grouper->group([
            new GroupablePiece($classified[0], 'de-01', false),
            new GroupablePiece($classified[1], 'de-01', false),
        ]);

        $this->assertSame(
            [
                [ShippingClass::Sperrgut, 'de-01', false],
                [ShippingClass::Spedition, 'de-01', false],
            ],
            $this->keys($shipments),
        );
        $this->assertLessThan(
            ShippingClass::Sperrgut->value,
            ShippingClass::Spedition->value,
        );
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
