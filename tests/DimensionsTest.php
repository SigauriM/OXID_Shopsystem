<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Measurement\Dimensions;
use PHPUnit\Framework\TestCase;

final class DimensionsTest extends TestCase
{
    public function testFieldOrderDoesNotChangeCanonicalSides(): void
    {
        foreach ([[20, 100, 10], [100, 20, 10], [10, 20, 100]] as $sides) {
            $dimensions = Dimensions::canonical($sides[0], $sides[1], $sides[2]);

            $this->assertSame(100, $dimensions->lengthMm);
            $this->assertSame(20, $dimensions->widthMm);
            $this->assertSame(10, $dimensions->heightMm);
        }
    }

    public function testFieldOrderDoesNotChangeGirth(): void
    {
        foreach ([[20, 100, 10], [100, 20, 10], [10, 20, 100]] as $sides) {
            $dimensions = Dimensions::canonical($sides[0], $sides[1], $sides[2]);

            $this->assertSame(160, $dimensions->girthMm());
        }
    }

    public function testTwoEqualSidesSortLongestFirst(): void
    {
        foreach ([[100, 100, 50], [100, 50, 100], [50, 100, 100]] as $sides) {
            $dimensions = Dimensions::canonical($sides[0], $sides[1], $sides[2]);

            $this->assertSame(100, $dimensions->lengthMm);
            $this->assertSame(100, $dimensions->widthMm);
            $this->assertSame(50, $dimensions->heightMm);
            $this->assertSame(400, $dimensions->girthMm());
        }
    }

    public function testCubeKeepsEqualSides(): void
    {
        $dimensions = Dimensions::canonical(10, 10, 10);

        $this->assertSame(10, $dimensions->lengthMm);
        $this->assertSame(10, $dimensions->widthMm);
        $this->assertSame(10, $dimensions->heightMm);
        $this->assertSame(50, $dimensions->girthMm());
    }

    public function testGirthAt3000MillimetresIsExactRegardlessOfFieldOrder(): void
    {
        foreach ([[1000, 500, 500], [500, 1000, 500], [500, 500, 1000]] as $sides) {
            $dimensions = Dimensions::canonical($sides[0], $sides[1], $sides[2]);

            $this->assertSame(1000, $dimensions->lengthMm);
            $this->assertSame(3000, $dimensions->girthMm());
        }
    }

    public function testGirthFormulaIs3001For1001By500By500(): void
    {
        $dimensions = Dimensions::canonical(1001, 500, 500);

        $this->assertSame(1001, $dimensions->lengthMm);
        $this->assertSame(3001, $dimensions->girthMm());
    }

    public function testCanonicalLengthAt2000MillimetresRegardlessOfField(): void
    {
        foreach ([[2000, 10, 10], [10, 2000, 10], [10, 10, 2000]] as $sides) {
            $dimensions = Dimensions::canonical($sides[0], $sides[1], $sides[2]);

            $this->assertSame(2000, $dimensions->lengthMm);
        }
    }

    public function testCanonicalLongestSideIs2001RegardlessOfField(): void
    {
        foreach ([[2001, 10, 10], [10, 2001, 10], [10, 10, 2001]] as $sides) {
            $dimensions = Dimensions::canonical($sides[0], $sides[1], $sides[2]);

            $this->assertSame(2001, $dimensions->lengthMm);
        }
    }

    public function testNegativeSidesAreProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Dimensions::canonical(-5, -5, -5);
    }

    public function testZeroSideIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Dimensions::canonical(0, 500, 400);
    }
}
