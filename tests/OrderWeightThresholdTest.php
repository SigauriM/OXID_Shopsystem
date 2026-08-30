<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\OrderWeightThreshold;
use PHPUnit\Framework\TestCase;

final class OrderWeightThresholdTest extends TestCase
{
    public function testOneGramAssembles(): void
    {
        $threshold = new OrderWeightThreshold(1);

        $this->assertSame(1, $threshold->grams);
    }

    public function testZeroIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order weight threshold must be 1 or greater.');
        new OrderWeightThreshold(0);
    }

    public function testNegativeIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order weight threshold must be 1 or greater.');
        new OrderWeightThreshold(-1);
    }

    public function testValueIsStoredAsGivenGramsNotKilogramsTimesThousand(): void
    {
        $threshold = new OrderWeightThreshold(40000);

        $this->assertSame(40000, $threshold->grams);
        $this->assertNotSame(40, $threshold->grams);
    }
}
