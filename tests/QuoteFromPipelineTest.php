<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\Dimensions;
use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\Result\InputSnapshot;
use OxidShipping\Engine\Result\PieceRejection;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class QuoteFromPipelineTest extends TestCase
{
    public function testRejectedDestinationWithoutPieceRejectionsIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejected destination requires a rejection for every piece.');
        Quote::fromPipeline(
            $pieces,
            new Rejected(RejectReason::UnknownZone),
            [],
            [],
            [],
            new InputSnapshot([$line], '99999', 'DE', false),
            'test-2026',
        );
    }

    public function testRejectionForMissingPieceIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejection does not refer to a piece.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [new PieceRejection('line-1', 0, 1, new Rejected(RejectReason::UnknownZone))],
            [],
            [],
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testDuplicatePieceCoordinateIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $piece = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        )[0];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate piece coordinate.');
        Quote::fromPipeline(
            [$piece, $piece],
            new KnownZone('de-01'),
            [],
            [],
            [],
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testDuplicateRejectionCoordinateIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $rejected = new Rejected(RejectReason::UnknownZone);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate rejection coordinate.');
        Quote::fromPipeline(
            $pieces,
            $rejected,
            [
                new PieceRejection('line-1', 0, 0, $rejected),
                new PieceRejection('line-1', 0, 0, $rejected),
            ],
            [],
            [],
            new InputSnapshot([$line], '99999', 'DE', false),
            'test-2026',
        );
    }

    public function testRejectionLineIdMismatchIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejection lineId does not match the piece.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [new PieceRejection('other-line', 0, 0, new Rejected(RejectReason::UnknownZone))],
            [],
            [],
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testFromPipelineSortsPiecesAndRejectionsByCoordinate(): void
    {
        $lines = [
            new OrderLine('line-a', 100, 100, 100, 1, 1),
            new OrderLine('line-b', 100, 100, 100, 1, 1),
        ];
        $pieces = (new PieceFactory())->expand(
            $lines,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $rejected = new Rejected(RejectReason::UnknownZone);

        $quote = Quote::fromPipeline(
            [$pieces[1], $pieces[0]],
            $rejected,
            [
                new PieceRejection('line-b', 1, 0, $rejected),
                new PieceRejection('line-a', 0, 0, $rejected),
            ],
            [],
            [],
            new InputSnapshot($lines, '99999', 'DE', false),
            'test-2026',
        );

        $this->assertSame([], $quote->classified);
        $this->assertSame([], $quote->shipments);
        $this->assertSame(
            [[0, 0], [1, 0]],
            array_map(
                static fn ($rejection): array => [$rejection->lineIndex, $rejection->pieceIndex],
                $quote->rejections,
            ),
        );
    }

    public function testKnownDestinationWithoutClassifiedPiecesIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Known destination requires a classified piece for every piece.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            [],
            [],
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testRejectedDestinationWithClassifiedPiecesIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $rejected = new Rejected(RejectReason::UnknownZone);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejected destination must not include classified pieces.');
        Quote::fromPipeline(
            $pieces,
            $rejected,
            [new PieceRejection('line-1', 0, 0, $rejected)],
            [new ClassifiedPiece($pieces[0], ShippingClass::Paket)],
            [],
            new InputSnapshot([$line], '99999', 'DE', false),
            'test-2026',
        );
    }

    public function testDuplicateClassifiedCoordinateIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $classified = new ClassifiedPiece($pieces[0], ShippingClass::Paket);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate classified coordinate.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            [$classified, $classified],
            [],
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testClassifiedPieceForMissingPieceIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $divisor = VolumetricDivisor::fromDimFactorCmKg(5000);
        $pieces = (new PieceFactory())->expand([$line], $divisor);
        $stray = MeasuredPiece::from(
            'line-1',
            0,
            1,
            Dimensions::canonical(100, 100, 100),
            1,
            $divisor,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Classified piece does not refer to a pipeline piece.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            [new ClassifiedPiece($stray, ShippingClass::Paket)],
            [],
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testClassifiedPieceLineIdMismatchIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $divisor = VolumetricDivisor::fromDimFactorCmKg(5000);
        $pieces = (new PieceFactory())->expand([$line], $divisor);
        $mismatched = MeasuredPiece::from(
            'other-line',
            0,
            0,
            Dimensions::canonical(100, 100, 100),
            1,
            $divisor,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Classified piece lineId does not match the piece.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            [new ClassifiedPiece($mismatched, ShippingClass::Paket)],
            [],
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testKnownDestinationWithClassifiedPiecesAndNoShipmentsIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $classified = [new ClassifiedPiece($pieces[0], ShippingClass::Paket)];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Known destination requires every classified piece in a shipment.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            [],
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testShipmentCoordinateNotInClassifiedIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $divisor = VolumetricDivisor::fromDimFactorCmKg(5000);
        $pieces = (new PieceFactory())->expand([$line], $divisor);
        $classified = [new ClassifiedPiece($pieces[0], ShippingClass::Paket)];
        $stray = MeasuredPiece::from(
            'line-1',
            0,
            1,
            Dimensions::canonical(100, 100, 100),
            1,
            $divisor,
        );
        $shipments = [
            new Shipment(ShippingClass::Paket, 'de-01', false, [
                new ClassifiedPiece($stray, ShippingClass::Paket),
            ]),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipment piece does not refer to a classified piece.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            $shipments,
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testClassifiedPieceMissingFromEveryShipmentIsProgrammerError(): void
    {
        $lines = [
            new OrderLine('line-a', 100, 100, 100, 1, 1),
            new OrderLine('line-b', 100, 100, 100, 1, 1),
        ];
        $pieces = (new PieceFactory())->expand(
            $lines,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $classified = [
            new ClassifiedPiece($pieces[0], ShippingClass::Paket),
            new ClassifiedPiece($pieces[1], ShippingClass::Paket),
        ];
        $shipments = [
            new Shipment(ShippingClass::Paket, 'de-01', false, [$classified[0]]),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Known destination requires every classified piece in a shipment.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            $shipments,
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testClassifiedPieceInTwoShipmentsWithDifferentKeysIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $classified = [new ClassifiedPiece($pieces[0], ShippingClass::Paket)];
        $shipments = [
            new Shipment(ShippingClass::Paket, 'de-01', false, $classified),
            new Shipment(ShippingClass::Paket, 'de-hh', false, $classified),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Classified piece belongs to more than one shipment.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            $shipments,
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testDuplicateShipmentKeyIsProgrammerError(): void
    {
        $lines = [
            new OrderLine('line-a', 100, 100, 100, 1, 1),
            new OrderLine('line-b', 100, 100, 100, 1, 1),
        ];
        $pieces = (new PieceFactory())->expand(
            $lines,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $classified = [
            new ClassifiedPiece($pieces[0], ShippingClass::Paket),
            new ClassifiedPiece($pieces[1], ShippingClass::Paket),
        ];
        $shipments = [
            new Shipment(ShippingClass::Paket, 'de-01', false, [$classified[0]]),
            new Shipment(ShippingClass::Paket, 'de-01', false, [$classified[1]]),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate shipment key.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            $shipments,
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
        );
    }

    public function testRejectedDestinationWithShipmentsIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $rejected = new Rejected(RejectReason::UnknownZone);
        $shipments = [
            new Shipment(
                ShippingClass::Paket,
                'de-01',
                false,
                [new ClassifiedPiece($pieces[0], ShippingClass::Paket)],
            ),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejected destination must not include shipments.');
        Quote::fromPipeline(
            $pieces,
            $rejected,
            [new PieceRejection('line-1', 0, 0, $rejected)],
            [],
            $shipments,
            new InputSnapshot([$line], '99999', 'DE', false),
            'test-2026',
        );
    }

    public function testFromPipelineSortsSpeditionThenSperrgutToRankOrder(): void
    {
        $lines = [
            new OrderLine('spedition', 100, 100, 100, 1, 1),
            new OrderLine('sperrgut', 100, 100, 100, 1, 1),
        ];
        $pieces = (new PieceFactory())->expand(
            $lines,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $classified = [
            new ClassifiedPiece($pieces[0], ShippingClass::Spedition),
            new ClassifiedPiece($pieces[1], ShippingClass::Sperrgut),
        ];
        $shipments = [
            new Shipment(ShippingClass::Spedition, 'de-01', false, [$classified[0]]),
            new Shipment(ShippingClass::Sperrgut, 'de-01', false, [$classified[1]]),
        ];

        $quote = Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            $shipments,
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
        );

        $this->assertSame(ShippingClass::Sperrgut, $quote->shipments[0]->class);
        $this->assertSame(ShippingClass::Spedition, $quote->shipments[1]->class);
    }

    public function testMixedShipmentZonesAssembleWhenDestinationIsDe01(): void
    {
        $lines = [
            new OrderLine('dresden', 100, 100, 100, 1, 1),
            new OrderLine('hamburg', 100, 100, 100, 1, 1),
        ];
        $pieces = (new PieceFactory())->expand(
            $lines,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $classified = [
            new ClassifiedPiece($pieces[0], ShippingClass::Paket),
            new ClassifiedPiece($pieces[1], ShippingClass::Paket),
        ];
        $shipments = [
            new Shipment(ShippingClass::Paket, 'de-01', false, [$classified[0]]),
            new Shipment(ShippingClass::Paket, 'de-hh', false, [$classified[1]]),
        ];

        $quote = Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            $shipments,
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
        );

        $this->assertInstanceOf(KnownZone::class, $quote->destination);
        $this->assertCount(2, $quote->shipments);
        $this->assertSame('de-01', $quote->shipments[0]->zoneId);
        $this->assertSame('de-hh', $quote->shipments[1]->zoneId);
        $this->assertSame('de-01', $quote->destination->zoneId);
    }
}
