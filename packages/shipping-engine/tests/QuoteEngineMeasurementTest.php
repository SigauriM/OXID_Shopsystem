<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Tests\Support\TestConfig;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tariff\PriceRuleId;
use PHPUnit\Framework\TestCase;

final class QuoteEngineMeasurementTest extends TestCase
{
    private QuoteEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new QuoteEngine();
    }

    public function testQuantityThreeYieldsThreePiecesAfterValidation(): void
    {
        $quote = $this->quoteLine(quantity: 3);

        $this->assertCount(3, $quote->classified);
        $this->assertSame(0, $quote->classified[0]->piece->pieceIndex);
        $this->assertSame(1, $quote->classified[1]->piece->pieceIndex);
        $this->assertSame(2, $quote->classified[2]->piece->pieceIndex);
        $this->assertSame(ShippingClass::Paket, $quote->classified[0]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[1]->class);
        $this->assertSame(ShippingClass::Paket, $quote->classified[2]->class);
        $this->assertCount(1, $quote->shipments);
        $this->assertSame(ShippingClass::Paket, $quote->shipments[0]->shipment->class);
        $this->assertSame('de-01', $quote->shipments[0]->shipment->zoneId);
        $this->assertFalse($quote->shipments[0]->shipment->indoor);
        $this->assertCount(3, $quote->shipments[0]->shipment->pieces);
        $this->assertCount(3, $quote->trace);
        $this->assertSame(PriceRuleId::Base, $quote->trace[0]->ruleId);
        $this->assertSame(PriceRuleId::Base, $quote->trace[1]->ruleId);
        $this->assertSame(PriceRuleId::Base, $quote->trace[2]->ruleId);
        foreach ($quote->trace as $line) {
            $this->assertNotSame(PriceRuleId::Indoor, $line->ruleId);
        }
        $this->assertNotSame(
            $quote->classified[0]->piece->pieceIndex,
            $quote->classified[1]->piece->pieceIndex,
        );
        $this->assertNotSame(
            $quote->classified[1]->piece->pieceIndex,
            $quote->classified[2]->piece->pieceIndex,
        );
    }

    public function testFieldOrderDoesNotChangeMeasuredNumbersThroughTheEngine(): void
    {
        $a = $this->quoteLine(lengthMm: 20, widthMm: 100, heightMm: 10)->classified[0]->piece;
        $b = $this->quoteLine(lengthMm: 100, widthMm: 20, heightMm: 10)->classified[0]->piece;

        $this->assertSame($a->dimensions->lengthMm, $b->dimensions->lengthMm);
        $this->assertSame($a->dimensions->widthMm, $b->dimensions->widthMm);
        $this->assertSame($a->dimensions->heightMm, $b->dimensions->heightMm);
        $this->assertSame($a->dimensions->girthMm(), $b->dimensions->girthMm());
        $this->assertSame($a->volumetricGrams, $b->volumetricGrams);
        $this->assertSame($a->billableGrams, $b->billableGrams);
        $this->assertSame(100, $a->dimensions->lengthMm);
        $this->assertSame(160, $a->dimensions->girthMm());
    }

    private function quoteLine(
        int $lengthMm = 100,
        int $widthMm = 100,
        int $heightMm = 100,
        int $weightGrams = 1,
        int $quantity = 1,
    ): Quote {
        $result = $this->engine->quote(new QuoteRequest(
            lines: [
                new OrderLine('line-1', $lengthMm, $widthMm, $heightMm, $weightGrams, $quantity),
            ],
            postalCode: '01067',
            country: 'DE',
            indoor: false,
            config: TestConfig::tariff(),
        ));

        $this->assertInstanceOf(Quote::class, $result);

        return $result;
    }
}
