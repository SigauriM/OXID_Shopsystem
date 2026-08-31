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
use OxidShipping\Engine\Tariff\PriceLine;
use OxidShipping\Engine\Tariff\PriceRuleId;
use OxidShipping\Engine\Tariff\PriceStage;
use OxidShipping\Engine\Tariff\PricedShipment;
use OxidShipping\Engine\Tests\Support\TestHashes;
use OxidShipping\Engine\Tests\Support\TestPricing;
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
        );

        $this->assertSame([], $quote->classified);
        $this->assertSame([], $quote->shipments);
        $this->assertSame(0, $quote->totalCents);
        $this->assertSame([], $quote->trace);
        $this->assertSame(
            [[0, 0], [1, 0]],
            array_map(
                static fn ($rejection): array => [$rejection->lineIndex, $rejection->pieceIndex],
                $quote->rejections,
            ),
        );
        $this->assertSame(TestHashes::PLACEHOLDER, $quote->configHash);
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestHashes::PLACEHOLDER,
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
            TestPricing::priceAll($shipments),
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
        );
    }

    public function testShipmentPieceClassMismatchIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $classified = [new ClassifiedPiece($pieces[0], ShippingClass::Paket)];
        $shipments = [
            new Shipment(ShippingClass::Spedition, 'de-01', false, [
                new ClassifiedPiece($pieces[0], ShippingClass::Spedition),
            ]),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipment piece class does not match the classified piece.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            TestPricing::priceAll($shipments),
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
        );
    }

    public function testShipmentPieceBillableMismatchIsProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $divisor = VolumetricDivisor::fromDimFactorCmKg(5000);
        $pieces = (new PieceFactory())->expand([$line], $divisor);
        $classified = [new ClassifiedPiece($pieces[0], ShippingClass::Paket)];
        $heavier = MeasuredPiece::from(
            'line-1',
            0,
            0,
            Dimensions::canonical(100, 100, 100),
            15000,
            $divisor,
        );
        $shipments = [
            new Shipment(ShippingClass::Paket, 'de-01', false, [
                new ClassifiedPiece($heavier, ShippingClass::Paket),
            ]),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipment piece billableGrams does not match the classified piece.');
        Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            TestPricing::priceAll($shipments),
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
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
            TestPricing::priceAll($shipments),
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
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
            TestPricing::priceAll($shipments),
            new InputSnapshot([$line], '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
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
            TestPricing::priceAll($shipments),
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
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
            TestPricing::priceAll($shipments),
            new InputSnapshot([$line], '99999', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
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
            TestPricing::priceAll($shipments),
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
        );

        $this->assertSame(ShippingClass::Sperrgut, $quote->shipments[0]->shipment->class);
        $this->assertSame(ShippingClass::Spedition, $quote->shipments[1]->shipment->class);
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
            TestPricing::priceAll($shipments),
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
        );

        $this->assertInstanceOf(KnownZone::class, $quote->destination);
        $this->assertCount(2, $quote->shipments);
        $this->assertSame('de-01', $quote->shipments[0]->shipment->zoneId);
        $this->assertSame('de-hh', $quote->shipments[1]->shipment->zoneId);
        $this->assertSame('de-01', $quote->destination->zoneId);
        $this->assertSame(
            $quote->shipments[0]->totalCents + $quote->shipments[1]->totalCents,
            $quote->totalCents,
        );
        $this->assertSame(
            array_merge($quote->shipments[0]->lines, $quote->shipments[1]->lines),
            $quote->trace,
        );
        $sum = 0;
        foreach ($quote->trace as $line) {
            $sum += $line->deltaCents;
        }
        $this->assertSame($quote->totalCents, $sum);
    }

    public function testFromPipelineSortsByRankWhenSpeditionIsCheaperThanSperrgut(): void
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
        $spedition = new PricedShipment(
            new Shipment(ShippingClass::Spedition, 'de-01', false, [$classified[0]]),
            100,
            100,
            5,
            [
                new PriceLine(
                    PriceRuleId::Base,
                    PriceStage::Base,
                    100,
                    'Base rate for piece (0, 0), billable 200 g.',
                    $classified[0]->piece->lineIndex,
                    $classified[0]->piece->pieceIndex,
                ),
            ],
        );
        $sperrgut = new PricedShipment(
            new Shipment(ShippingClass::Sperrgut, 'de-01', false, [$classified[1]]),
            9000,
            9000,
            3,
            [
                new PriceLine(
                    PriceRuleId::Base,
                    PriceStage::Base,
                    9000,
                    'Base rate for piece (1, 0), billable 200 g.',
                    $classified[1]->piece->lineIndex,
                    $classified[1]->piece->pieceIndex,
                ),
            ],
        );

        $quote = Quote::fromPipeline(
            $pieces,
            new KnownZone('de-01'),
            [],
            $classified,
            [$spedition, $sperrgut],
            new InputSnapshot($lines, '01067', 'DE', false),
            'test-2026',
            TestHashes::PLACEHOLDER,
        );

        $this->assertSame(ShippingClass::Sperrgut, $quote->shipments[0]->shipment->class);
        $this->assertSame(ShippingClass::Spedition, $quote->shipments[1]->shipment->class);
        $this->assertSame(9000, $quote->shipments[0]->totalCents);
        $this->assertSame(100, $quote->shipments[1]->totalCents);
        $this->assertLessThan($sperrgut->totalCents, $spedition->totalCents);
        $this->assertSame(9100, $quote->totalCents);
        $this->assertSame(
            array_merge($quote->shipments[0]->lines, $quote->shipments[1]->lines),
            $quote->trace,
        );
        $this->assertSame(PriceRuleId::Base, $quote->trace[0]->ruleId);
        $this->assertSame(9000, $quote->trace[0]->deltaCents);
        $this->assertSame(100, $quote->trace[1]->deltaCents);
    }

    public function testEmptyConfigHashIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config hash must be 64 lowercase hexadecimal characters.');
        $this->fromPipelineWithHash('');
    }

    public function testUppercaseConfigHashIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config hash must be 64 lowercase hexadecimal characters.');
        $this->fromPipelineWithHash(strtoupper(TestHashes::PLACEHOLDER));
    }

    public function testShortConfigHashIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config hash must be 64 lowercase hexadecimal characters.');
        $this->fromPipelineWithHash(str_repeat('a', 63));
    }

    public function testLongConfigHashIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config hash must be 64 lowercase hexadecimal characters.');
        $this->fromPipelineWithHash(str_repeat('a', 65));
    }

    public function testPrefixedConfigHashIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config hash must be 64 lowercase hexadecimal characters.');
        $this->fromPipelineWithHash('sha256:' . TestHashes::PLACEHOLDER);
    }

    private function fromPipelineWithHash(string $configHash): Quote
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);
        $pieces = (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
        $rejected = new Rejected(RejectReason::UnknownZone);

        return Quote::fromPipeline(
            $pieces,
            $rejected,
            [new PieceRejection('line-1', 0, 0, $rejected)],
            [],
            [],
            new InputSnapshot([$line], '99999', 'DE', false),
            'test-2026',
            $configHash,
        );
    }
}
