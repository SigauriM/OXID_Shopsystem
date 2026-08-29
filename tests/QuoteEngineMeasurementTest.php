<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Tests\Support\TestConfig;
use OxidShipping\Engine\Result\Quote;
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

        $this->assertCount(3, $quote->pieces);
        $this->assertSame(0, $quote->pieces[0]->pieceIndex);
        $this->assertSame(1, $quote->pieces[1]->pieceIndex);
        $this->assertSame(2, $quote->pieces[2]->pieceIndex);
    }

    public function testFieldOrderDoesNotChangeMeasuredNumbersThroughTheEngine(): void
    {
        $a = $this->quoteLine(lengthMm: 20, widthMm: 100, heightMm: 10)->pieces[0];
        $b = $this->quoteLine(lengthMm: 100, widthMm: 20, heightMm: 10)->pieces[0];

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
