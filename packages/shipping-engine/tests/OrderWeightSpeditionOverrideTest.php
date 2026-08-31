<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\OrderWeightThreshold;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\OrderRules\OrderWeightSpeditionOverride;
use OxidShipping\Engine\Result\PieceRejection;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class OrderWeightSpeditionOverrideTest extends TestCase
{
    private OrderWeightSpeditionOverride $override;

    protected function setUp(): void
    {
        $this->override = new OrderWeightSpeditionOverride();
    }

    /**
     * Rejection sits beside the fixture; it is not an apply() argument.
     * The signature is the guard — a Rejected piece cannot be raised to Spedition.
     */
    public function testLivePiecesAtThresholdRaiseAndSiblingRejectionStaysRejected(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('live-a', 100, 100, 100, 15000, 1),
                new OrderLine('live-b', 100, 100, 100, 15000, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );
        $rejection = new PieceRejection(
            'rejected-line',
            2,
            0,
            new Rejected(RejectReason::ZoneForbidden),
        );

        $result = $this->override->apply($classified, new OrderWeightThreshold(30000));

        $this->assertCount(2, $result);
        $this->assertSame(ShippingClass::Spedition, $result[0]->class);
        $this->assertSame(ShippingClass::Spedition, $result[1]->class);
        $this->assertSame(RejectReason::ZoneForbidden, $rejection->rejected->reason);
    }

    /**
     * Same fixture shape: one live Paket, a 20000 g rejection not in the list.
     * Sum of live actual grams is below N, so the live piece stays Paket.
     */
    public function testLivePieceBelowThresholdStaysPaketWhenSiblingRejectionIsNotInTheList(): void
    {
        $classified = $this->classified(
            [new OrderLine('live', 100, 100, 100, 20000, 1)],
            [ShippingClass::Paket],
        );
        $rejection = new PieceRejection(
            'rejected-20000',
            1,
            0,
            new Rejected(RejectReason::ZoneForbidden),
        );

        $result = $this->override->apply($classified, new OrderWeightThreshold(30000));

        $this->assertCount(1, $result);
        $this->assertSame(ShippingClass::Paket, $result[0]->class);
        $this->assertSame(20000, $result[0]->piece->actualGrams);
        $this->assertSame(RejectReason::ZoneForbidden, $rejection->rejected->reason);
    }

    public function testExactThresholdRaisesAndOneGramBelowDoesNot(): void
    {
        $atThreshold = $this->classified(
            [
                new OrderLine('a', 100, 100, 100, 20000, 1),
                new OrderLine('b', 100, 100, 100, 20000, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );
        $below = $this->classified(
            [
                new OrderLine('a', 100, 100, 100, 20000, 1),
                new OrderLine('b', 100, 100, 100, 19999, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );

        $raised = $this->override->apply($atThreshold, new OrderWeightThreshold(40000));
        $unchanged = $this->override->apply($below, new OrderWeightThreshold(40000));

        $this->assertSame(ShippingClass::Spedition, $raised[0]->class);
        $this->assertSame(ShippingClass::Spedition, $raised[1]->class);
        $this->assertSame(ShippingClass::Paket, $unchanged[0]->class);
        $this->assertSame(ShippingClass::Paket, $unchanged[1]->class);
    }

    public function testSumUsesActualGramsNotBillable(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('vol-a', 500, 450, 400, 10000, 1),
                new OrderLine('vol-b', 500, 450, 400, 10000, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Paket],
        );

        $this->assertSame(500, $classified[0]->piece->dimensions->lengthMm);
        $this->assertSame(10000, $classified[0]->piece->actualGrams);
        $this->assertSame(18000, $classified[0]->piece->billableGrams);
        $this->assertSame(
            20000,
            $classified[0]->piece->actualGrams + $classified[1]->piece->actualGrams,
        );
        $this->assertSame(
            36000,
            $classified[0]->piece->billableGrams + $classified[1]->piece->billableGrams,
        );

        $result = $this->override->apply($classified, new OrderWeightThreshold(30000));

        $this->assertSame(ShippingClass::Paket, $result[0]->class);
        $this->assertSame(ShippingClass::Paket, $result[1]->class);
    }

    public function testEveryLivePieceRaisesNotASubset(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('long', 2001, 50, 50, 1, 1),
                new OrderLine('cube-a', 100, 100, 100, 20000, 1),
                new OrderLine('cube-b', 100, 100, 100, 20000, 1),
            ],
            [ShippingClass::Sperrgut, ShippingClass::Paket, ShippingClass::Paket],
        );

        $result = $this->override->apply($classified, new OrderWeightThreshold(40000));

        $this->assertCount(3, $result);
        $this->assertSame(ShippingClass::Spedition, $result[0]->class);
        $this->assertSame(ShippingClass::Spedition, $result[1]->class);
        $this->assertSame(ShippingClass::Spedition, $result[2]->class);
        $this->assertSame(1, $result[0]->piece->actualGrams);
        $this->assertSame($classified[0]->piece, $result[0]->piece);
    }

    public function testAlreadySpeditionStaysSpeditionWhenThresholdMet(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('a', 100, 100, 100, 20000, 1),
                new OrderLine('b', 100, 100, 100, 20000, 1),
            ],
            [ShippingClass::Spedition, ShippingClass::Spedition],
        );

        $result = $this->override->apply($classified, new OrderWeightThreshold(40000));

        $this->assertSame(ShippingClass::Spedition, $result[0]->class);
        $this->assertSame(ShippingClass::Spedition, $result[1]->class);
    }

    public function testEmptyListStaysEmpty(): void
    {
        $result = $this->override->apply([], new OrderWeightThreshold(40000));

        $this->assertSame([], $result);
    }

    public function testQuantityIsNotMultipliedAgainOnAlreadyExpandedPieces(): void
    {
        $classified = $this->classified(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            [ShippingClass::Paket, ShippingClass::Paket],
        );

        $this->assertCount(2, $classified);
        $this->assertSame(20000, $classified[0]->piece->actualGrams);
        $this->assertSame(20000, $classified[1]->piece->actualGrams);

        $result = $this->override->apply($classified, new OrderWeightThreshold(40000));

        $this->assertCount(2, $result);
        $this->assertSame(ShippingClass::Spedition, $result[0]->class);
        $this->assertSame(ShippingClass::Spedition, $result[1]->class);
    }

    public function testInputOrderOfPaketThenSperrgutIsPreserved(): void
    {
        $classified = $this->classified(
            [
                new OrderLine('cube', 100, 100, 100, 20000, 1),
                new OrderLine('long', 2001, 50, 50, 20000, 1),
            ],
            [ShippingClass::Paket, ShippingClass::Sperrgut],
        );

        $result = $this->override->apply($classified, new OrderWeightThreshold(40000));

        $this->assertSame(0, $result[0]->piece->lineIndex);
        $this->assertSame(0, $result[0]->piece->pieceIndex);
        $this->assertSame(1, $result[1]->piece->lineIndex);
        $this->assertSame(0, $result[1]->piece->pieceIndex);
        $this->assertSame($classified[0]->piece, $result[0]->piece);
        $this->assertSame($classified[1]->piece, $result[1]->piece);
        $this->assertSame(ShippingClass::Spedition, $result[0]->class);
        $this->assertSame(ShippingClass::Spedition, $result[1]->class);
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
