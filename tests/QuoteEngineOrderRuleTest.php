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

final class QuoteEngineOrderRuleTest extends TestCase
{
    private QuoteEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new QuoteEngine();
    }

    public function testTwoTwentyKilogramCubesAtThresholdAreBothSpedition(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 20000, 2);

        $this->assertInstanceOf(KnownZone::class, $quote->destination);
        $this->assertCount(2, $quote->classified);
        $this->assertSame(ShippingClass::Spedition, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Spedition, $quote->classified[1]->class);
        $this->assertSame([], $quote->rejections);
    }

    public function testSingleTwentyKilogramCubeStaysPaket(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 20000);

        $this->assertSame(ShippingClass::Paket, $quote->classified[0]->class);
    }

    public function testTwoCubesOneGramBelowThresholdStayPaket(): void
    {
        $quote = $this->quote([
            new OrderLine('a', 100, 100, 100, 20000, 1),
            new OrderLine('b', 100, 100, 100, 19999, 1),
        ]);

        $this->assertSame(ShippingClass::Paket, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[1]->class);
    }

    public function testTwoFifteenKilogramPiecesAreTwoPlacesOfOneClassNotSpedition(): void
    {
        $quote = $this->quoteLine(100, 100, 100, 15000, 2);

        $this->assertCount(2, $quote->classified);
        $this->assertSame(ShippingClass::Paket, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[1]->class);
        $this->assertNotSame(ShippingClass::Spedition, $quote->classified[0]->class);
    }

    public function testThreeVolumetricPiecesStayPaketWhenActualSumIsBelowThreshold(): void
    {
        $quote = $this->quoteLine(500, 450, 400, 10000, 3);

        $this->assertCount(3, $quote->classified);
        $this->assertSame(10000, $quote->classified[0]->piece->actualGrams);
        $this->assertSame(18000, $quote->classified[0]->piece->billableGrams);
        $this->assertSame(
            30000,
            $quote->classified[0]->piece->actualGrams
            + $quote->classified[1]->piece->actualGrams
            + $quote->classified[2]->piece->actualGrams,
        );
        $this->assertSame(
            54000,
            $quote->classified[0]->piece->billableGrams
            + $quote->classified[1]->piece->billableGrams
            + $quote->classified[2]->piece->billableGrams,
        );
        $this->assertSame(ShippingClass::Paket, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[1]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[2]->class);
    }

    public function testSperrgutAndTwoPaketAllRaiseWhenOrderThresholdMet(): void
    {
        $quote = $this->quote([
            new OrderLine('long', 2001, 50, 50, 1, 1),
            new OrderLine('cubes', 100, 100, 100, 20000, 2),
        ]);

        $this->assertCount(3, $quote->classified);
        $this->assertSame(ShippingClass::Spedition, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Spedition, $quote->classified[1]->class);
        $this->assertSame(ShippingClass::Spedition, $quote->classified[2]->class);
        $this->assertSame(1, $quote->classified[0]->piece->actualGrams);
    }

    public function testForbiddenZoneWithTwoHeavyPiecesHasNoClassifiedPieces(): void
    {
        $quote = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            '18565',
            'DE',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::ZoneForbidden, $quote->destination->reason);
        $this->assertSame([], $quote->classified);
        $this->assertSame([], $quote->shipments);
    }

    public function testUnservedCountryWithTwoHeavyPiecesHasNoClassifiedPieces(): void
    {
        $quote = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            '8001',
            'CH',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::CountryNotServed, $quote->destination->reason);
        $this->assertSame([], $quote->classified);
        $this->assertSame([], $quote->shipments);
    }

    public function testUnknownZoneWithTwoHeavyPiecesHasNoClassifiedPieces(): void
    {
        $quote = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            '99999',
            'DE',
        );

        $this->assertInstanceOf(Rejected::class, $quote->destination);
        $this->assertSame(RejectReason::UnknownZone, $quote->destination->reason);
        $this->assertSame([], $quote->classified);
        $this->assertSame([], $quote->shipments);
    }

    public function testIndoorFlagDoesNotChangeOrderWeightClass(): void
    {
        $curb = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: false,
        );
        $indoor = $this->quote(
            [new OrderLine('pair', 100, 100, 100, 20000, 2)],
            indoor: true,
        );

        $this->assertSame($curb->classified[0]->class, $indoor->classified[0]->class);
        $this->assertSame($curb->classified[1]->class, $indoor->classified[1]->class);
        $this->assertSame(ShippingClass::Spedition, $curb->classified[0]->class);
        $this->assertSame(ShippingClass::Spedition, $indoor->classified[0]->class);
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
}
