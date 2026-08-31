<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tariff\PriceLine;
use OxidShipping\Engine\Tariff\PriceRuleId;
use OxidShipping\Engine\Tariff\PriceStage;
use OxidShipping\Engine\Tariff\PricedShipment;
use PHPUnit\Framework\TestCase;

final class PriceLineTest extends TestCase
{
    public function testBaseWithoutIndicesIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Base price line must carry coordinates.');
        new PriceLine(
            PriceRuleId::Base,
            PriceStage::Base,
            400,
            'Base rate for piece (0, 0), billable 200 g.',
            null,
            null,
        );
    }

    public function testSurchargeWithIndicesIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Surcharge price line must not carry coordinates.');
        new PriceLine(
            PriceRuleId::Island,
            PriceStage::Surcharge,
            800,
            'Island or foreign-zone surcharge (one stop).',
            0,
            0,
        );
    }

    public function testOneIndexSetAndTheOtherNullIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price line indices must both be set or both be null.');
        new PriceLine(
            PriceRuleId::Base,
            PriceStage::Base,
            400,
            'Base rate for piece (0, 0), billable 200 g.',
            0,
            null,
        );
    }

    public function testPricedShipmentBaseLineOnTheWrongPieceIsProgrammerError(): void
    {
        $classified = $this->classified(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            [ShippingClass::Paket],
        );
        $shipment = new Shipment(ShippingClass::Paket, 'de-01', false, $classified);
        $line = new PriceLine(
            PriceRuleId::Base,
            PriceStage::Base,
            400,
            'Base rate for piece (0, 1), billable 200 g.',
            0,
            1,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Base price line coordinates must match the piece.');
        new PricedShipment($shipment, 400, 400, 2, [$line]);
    }

    public function testPricedShipmentLineSumMismatchIsProgrammerError(): void
    {
        $classified = $this->classified(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            [ShippingClass::Paket],
        );
        $shipment = new Shipment(ShippingClass::Paket, 'de-01', false, $classified);
        $line = new PriceLine(
            PriceRuleId::Base,
            PriceStage::Base,
            400,
            'Base rate for piece (0, 0), billable 200 g.',
            0,
            0,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Priced shipment lines must sum to totalCents.');
        new PricedShipment($shipment, 400, 401, 2, [$line]);
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
