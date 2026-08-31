<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\PieceClassifier;
use OxidShipping\Engine\Domain\ClassificationConfig;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class PieceClassifierTest extends TestCase
{
    public function testOneLongPieceIsAboveThreeShortPiecesWithTheSameLengthSum(): void
    {
        $classifier = new PieceClassifier();
        $config = $this->classification();

        $long = $classifier->classifyAll(
            $this->pieces(new OrderLine('long', 2001, 50, 50, 1, 1)),
            $config,
        );
        $shorts = $classifier->classifyAll(
            $this->pieces(new OrderLine('shorts', 667, 50, 50, 1, 3)),
            $config,
        );

        $this->assertCount(1, $long);
        $this->assertSame(ShippingClass::Sperrgut, $long[0]->class);

        $this->assertCount(3, $shorts);
        foreach ($shorts as $classified) {
            $this->assertSame(ShippingClass::Paket, $classified->class);
        }
        $this->assertGreaterThan($shorts[0]->class->rank(), $long[0]->class->rank());
        $this->assertSame(
            2001,
            $shorts[0]->piece->dimensions->lengthMm
            + $shorts[1]->piece->dimensions->lengthMm
            + $shorts[2]->piece->dimensions->lengthMm,
        );
    }

    public function testTwoFifteenKilogramPiecesAreTwoPlacesOfOneClassNotOneSpedition(): void
    {
        $classified = (new PieceClassifier())->classifyAll(
            $this->pieces(new OrderLine('ladders', 100, 100, 100, 15000, 2)),
            $this->classification(),
        );

        $this->assertCount(2, $classified);
        $this->assertSame(ShippingClass::Paket, $classified[0]->class);
        $this->assertSame(ShippingClass::Paket, $classified[1]->class);
        $this->assertNotSame(ShippingClass::Spedition, $classified[0]->class);
    }

    public function testGirthExactlyThreeThousandIsSilenceAndThreeThousandOneIsSperrgut(): void
    {
        $classifier = new PieceClassifier();
        $config = $this->classification();

        $atThreshold = $classifier->classifyAll(
            $this->pieces(new OrderLine('girth-3000', 2000, 490, 10, 1, 1)),
            $config,
        );
        $above = $classifier->classifyAll(
            $this->pieces(new OrderLine('girth-3001', 1999, 491, 10, 1, 1)),
            $config,
        );

        $this->assertSame(3000, $atThreshold[0]->piece->dimensions->girthMm());
        $this->assertSame(ShippingClass::Paket, $atThreshold[0]->class);

        $this->assertSame(3001, $above[0]->piece->dimensions->girthMm());
        $this->assertSame(ShippingClass::Sperrgut, $above[0]->class);
        $this->assertSame(1, $above[0]->piece->actualGrams);
        $this->assertLessThan(20000, $above[0]->piece->billableGrams);
        $this->assertLessThanOrEqual(2000, $above[0]->piece->dimensions->lengthMm);
    }

    public function testGirthExactlyThirtySixHundredIsStillSperrgutAndOneAboveIsSpedition(): void
    {
        $classifier = new PieceClassifier();
        $config = $this->classification();

        $atThreshold = $classifier->classifyAll(
            $this->pieces(new OrderLine('girth-3600', 2000, 790, 10, 1, 1)),
            $config,
        );
        $above = $classifier->classifyAll(
            $this->pieces(new OrderLine('girth-3601', 1999, 791, 10, 1, 1)),
            $config,
        );

        $this->assertSame(3600, $atThreshold[0]->piece->dimensions->girthMm());
        $this->assertSame(ShippingClass::Sperrgut, $atThreshold[0]->class);

        $this->assertSame(3601, $above[0]->piece->dimensions->girthMm());
        $this->assertSame(ShippingClass::Spedition, $above[0]->class);
    }

    public function testCanonicalLengthOnFullPieceRaisesAtTwoThousandOne(): void
    {
        $classifier = new PieceClassifier();
        $config = $this->classification();

        $atThreshold = $classifier->classifyAll(
            $this->pieces(new OrderLine('length-2000', 2000, 10, 10, 1, 1)),
            $config,
        );
        $above = $classifier->classifyAll(
            $this->pieces(new OrderLine('length-2001', 2001, 10, 10, 1, 1)),
            $config,
        );

        $this->assertSame(2000, $atThreshold[0]->piece->dimensions->lengthMm);
        $this->assertSame(2040, $atThreshold[0]->piece->dimensions->girthMm());
        $this->assertSame(ShippingClass::Paket, $atThreshold[0]->class);

        $this->assertSame(2001, $above[0]->piece->dimensions->lengthMm);
        $this->assertSame(ShippingClass::Sperrgut, $above[0]->class);
    }

    public function testBillableWeightExactlyTwentyThousandIsSilenceAndOneGramAboveIsSpedition(): void
    {
        $classifier = new PieceClassifier();
        $config = $this->classification();

        $atThreshold = $classifier->classifyAll(
            $this->pieces(new OrderLine('weight-20000', 100, 100, 100, 20000, 1)),
            $config,
        );
        $above = $classifier->classifyAll(
            $this->pieces(new OrderLine('weight-20001', 100, 100, 100, 20001, 1)),
            $config,
        );

        $this->assertSame(20000, $atThreshold[0]->piece->billableGrams);
        $this->assertSame(ShippingClass::Paket, $atThreshold[0]->class);

        $this->assertSame(20001, $above[0]->piece->billableGrams);
        $this->assertSame(ShippingClass::Spedition, $above[0]->class);
    }

    public function testWeightRuleLooksAtBillableNotOnlyActual(): void
    {
        $classifier = new PieceClassifier();
        $config = $this->classification();

        $heavyActual = $classifier->classifyAll(
            $this->pieces(new OrderLine('actual-20001', 100, 100, 100, 20001, 1)),
            $config,
        );
        $lightActual = $classifier->classifyAll(
            $this->pieces(new OrderLine('actual-1', 100, 100, 100, 1, 1)),
            $config,
        );

        $this->assertSame(20001, $heavyActual[0]->piece->actualGrams);
        $this->assertSame(ShippingClass::Spedition, $heavyActual[0]->class);

        $this->assertSame(1, $lightActual[0]->piece->actualGrams);
        $this->assertSame(200, $lightActual[0]->piece->billableGrams);
        $this->assertSame(ShippingClass::Paket, $lightActual[0]->class);
    }

    public function testTwoRulesVotingDifferentClassesTakeTheLatticeMax(): void
    {
        $classified = (new PieceClassifier())->classifyAll(
            $this->pieces(new OrderLine('max-join', 2001, 800, 10, 1, 1)),
            $this->classification(),
        );

        $piece = $classified[0]->piece;
        $this->assertSame(2001, $piece->dimensions->lengthMm);
        $this->assertSame(3621, $piece->dimensions->girthMm());
        $this->assertSame(3202, $piece->billableGrams);
        $this->assertSame(ShippingClass::Spedition, $classified[0]->class);
    }

    public function testAllRulesSilentYieldPaket(): void
    {
        $classified = (new PieceClassifier())->classifyAll(
            $this->pieces(new OrderLine('cube', 100, 100, 100, 1, 1)),
            $this->classification(),
        );

        $this->assertSame(ShippingClass::Paket, $classified[0]->class);
    }

    /**
     * @return list<MeasuredPiece>
     */
    private function pieces(OrderLine $line): array
    {
        return (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
    }

    private function classification(): ClassificationConfig
    {
        return TestConfig::classification();
    }
}
