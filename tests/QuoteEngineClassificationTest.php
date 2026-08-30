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
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class QuoteEngineClassificationTest extends TestCase
{
    private QuoteEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new QuoteEngine();
    }

    public function testSmallCubeIsKnownPaketWithNoRejections(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 1);

        $this->assertInstanceOf(KnownZone::class, $quote->destination);
        $this->assertCount(1, $quote->classified);
        $this->assertSame(ShippingClass::Paket, $quote->classified[0]->class);
        $this->assertSame([], $quote->rejections);
    }

    public function testOneLongAndThreeShortInOneQuoteDoNotShareASummedLength(): void
    {
        $quote = $this->quote([
            new OrderLine('long', 2001, 50, 50, 1, 1),
            new OrderLine('shorts', 667, 50, 50, 1, 3),
        ]);

        $this->assertCount(4, $quote->classified);
        $this->assertSame(ShippingClass::Sperrgut, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[1]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[2]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[3]->class);
        $this->assertSame(
            2001,
            $quote->classified[1]->piece->dimensions->lengthMm
            + $quote->classified[2]->piece->dimensions->lengthMm
            + $quote->classified[3]->piece->dimensions->lengthMm,
        );
    }

    public function testTwoFifteenKilogramPiecesAreTwoPlacesOfOneClassNotSpedition(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 15000, 2);

        $this->assertCount(2, $quote->classified);
        $this->assertSame($quote->classified[0]->class, $quote->classified[1]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[0]->class);
        $this->assertNotSame(ShippingClass::Spedition, $quote->classified[0]->class);
        $this->assertCount(1, $quote->shipments);
        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->class);
        $this->assertCount(2, $quote->shipments[0]->pieces);
    }

    public function testGirthThresholdIsStrictlyAboveThreeThousand(): void
    {
        $atThreshold = $this->quoteLine(2000, 490, 10, 1);
        $above = $this->quoteLine(1999, 491, 10, 1);

        $this->assertSame(3000, $atThreshold->classified[0]->piece->dimensions->girthMm());
        $this->assertSame(ShippingClass::Paket, $atThreshold->classified[0]->class);

        $this->assertSame(3001, $above->classified[0]->piece->dimensions->girthMm());
        $this->assertGreaterThanOrEqual(
            ShippingClass::Sperrgut->rank(),
            $above->classified[0]->class->rank(),
        );
        $this->assertLessThan(20000, $above->classified[0]->piece->billableGrams);
    }

    public function testCanonicalLengthThresholdIsStrictlyAboveTwoThousand(): void
    {
        $atThreshold = $this->quoteLine(2000, 10, 10, 1);
        $above = $this->quoteLine(2001, 10, 10, 1);

        $this->assertSame(2000, $atThreshold->classified[0]->piece->dimensions->lengthMm);
        $this->assertSame(ShippingClass::Paket, $atThreshold->classified[0]->class);

        $this->assertSame(2001, $above->classified[0]->piece->dimensions->lengthMm);
        $this->assertGreaterThanOrEqual(
            ShippingClass::Sperrgut->rank(),
            $above->classified[0]->class->rank(),
        );
    }

    public function testBillableWeightThresholdIsStrictlyAboveTwentyThousandGrams(): void
    {
        $atThreshold = $this->quoteLine(100, 100, 100, 20000);
        $above = $this->quoteLine(100, 100, 100, 20001);

        $this->assertSame(20000, $atThreshold->classified[0]->piece->billableGrams);
        $this->assertSame(ShippingClass::Paket, $atThreshold->classified[0]->class);

        $this->assertSame(20001, $above->classified[0]->piece->billableGrams);
        $this->assertSame(ShippingClass::Spedition, $above->classified[0]->class);
    }

    public function testForbiddenZoneHasNoClassifiedPieces(): void
    {
        $quote = $this->quote(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            '18565',
            'DE',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::ZoneForbidden, $quote->destination->reason);
        $this->assertSame([], $quote->classified);
        $this->assertSame([], $quote->shipments);
    }

    public function testUnservedCountryHasNoClassifiedPieces(): void
    {
        $quote = $this->quote(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            '8001',
            'CH',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::CountryNotServed, $quote->destination->reason);
        $this->assertSame([], $quote->classified);
        $this->assertSame([], $quote->shipments);
    }

    public function testFieldOrderDoesNotChangeClass(): void
    {
        $a = $this->quoteLine(20, 100, 10, 1);
        $b = $this->quoteLine(100, 20, 10, 1);

        $this->assertSame($a->classified[0]->class, $b->classified[0]->class);
        $this->assertSame(ShippingClass::Paket, $a->classified[0]->class);
    }

    /**
     * @param list<OrderLine> $lines
     */
    private function quote(
        array $lines,
        string $postalCode = '01067',
        string $country = 'DE',
    ): Quote {
        $result = $this->engine->quote(new QuoteRequest(
            lines: $lines,
            postalCode: $postalCode,
            country: $country,
            indoor: false,
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
}
