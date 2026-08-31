<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class ShipmentTest extends TestCase
{
    public function testOnePaketWithValidZoneAndIndoorFalseAssembles(): void
    {
        $classified = $this->classified([new OrderLine('line-1', 100, 100, 100, 1, 1)], [ShippingClass::Paket]);

        $shipment = new Shipment(ShippingClass::Paket, 'de-01', false, $classified);

        $this->assertSame(ShippingClass::Paket, $shipment->class);
        $this->assertSame('de-01', $shipment->zoneId);
        $this->assertFalse($shipment->indoor);
        $this->assertCount(1, $shipment->pieces);
        $this->assertSame(0, $shipment->pieces[0]->piece->lineIndex);
        $this->assertSame(0, $shipment->pieces[0]->piece->pieceIndex);
    }

    public function testEmptyPieceListIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipment must contain at least one piece.');
        new Shipment(ShippingClass::Paket, 'de-01', false, []);
    }

    public function testSperrgutPieceInPaketShipmentIsProgrammerError(): void
    {
        $classified = $this->classified(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            [ShippingClass::Sperrgut],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipment piece class does not match the shipment class.');
        new Shipment(ShippingClass::Paket, 'de-01', false, $classified);
    }

    public function testDuplicateCoordinatesInOneShipmentIsProgrammerError(): void
    {
        $classified = $this->classified(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            [ShippingClass::Paket],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate piece coordinate.');
        new Shipment(ShippingClass::Paket, 'de-01', false, [$classified[0], $classified[0]]);
    }

    public function testEmptyZoneIdIsProgrammerError(): void
    {
        $classified = $this->classified([new OrderLine('line-1', 100, 100, 100, 1, 1)], [ShippingClass::Paket]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone id length must be between 1 and 32.');
        new Shipment(ShippingClass::Paket, '', false, $classified);
    }

    public function testInvalidZoneIdIsProgrammerError(): void
    {
        $classified = $this->classified([new OrderLine('line-1', 100, 100, 100, 1, 1)], [ShippingClass::Paket]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone id does not match the required pattern.');
        new Shipment(ShippingClass::Paket, 'DE-01', false, $classified);
    }

    public function testPiecesAreSortedByLineIndexThenPieceIndex(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('line-a', 100, 100, 100, 1, 1),
                new OrderLine('line-b', 100, 100, 100, 1, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );

        $shipment = new Shipment(
            ShippingClass::Paket,
            'de-01',
            false,
            [$classified[1], $classified[0]],
        );

        $this->assertSame(0, $shipment->pieces[0]->piece->lineIndex);
        $this->assertSame(0, $shipment->pieces[0]->piece->pieceIndex);
        $this->assertSame(1, $shipment->pieces[1]->piece->lineIndex);
        $this->assertSame(0, $shipment->pieces[1]->piece->pieceIndex);
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
}
