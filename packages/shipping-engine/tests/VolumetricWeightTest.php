<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Measurement\Dimensions;
use OxidShipping\Engine\Measurement\VolumetricWeight;
use PHPUnit\Framework\TestCase;

final class VolumetricWeightTest extends TestCase
{
    public function test500x400x300AtFiveMillionIs12000GramsEquals12Kilograms(): void
    {
        $grams = VolumetricWeight::grams(
            Dimensions::canonical(500, 400, 300),
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );

        $this->assertSame(12_000, $grams);
        $this->assertSame(12, intdiv($grams, 1000));
    }

    public function testUnsorted300x500x400AtFiveMillionIsAlso12000GramsEquals12Kilograms(): void
    {
        $grams = VolumetricWeight::grams(
            Dimensions::canonical(300, 500, 400),
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );

        $this->assertSame(12_000, $grams);
        $this->assertSame(12, intdiv($grams, 1000));
    }

    public function test1000mmCubeAtFiveMillionIs200000GramsEquals200Kilograms(): void
    {
        $grams = VolumetricWeight::grams(
            Dimensions::canonical(1000, 1000, 1000),
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );

        $this->assertSame(200_000, $grams);
        $this->assertSame(200, intdiv($grams, 1000));
    }

    public function test1000mmCubeAtSixMillionIs166667GramsEquals166Point667Kilograms(): void
    {
        $grams = VolumetricWeight::grams(
            Dimensions::canonical(1000, 1000, 1000),
            VolumetricDivisor::fromDimFactorCmKg(6000),
        );

        $this->assertSame(166_667, $grams);
        $this->assertNotSame(166_666, $grams);
        $this->assertSame(166, intdiv($grams, 1000));
        $this->assertSame(667, $grams % 1000);
    }

    public function testCeilStepsAt5000And5001CubicMillimetres(): void
    {
        // Sides chosen so L×W×H is 5000 or 5001 mm³;
        // that is the smallest ceil step (remainder 1000), not a real parcel.
        $this->assertSame(1, VolumetricWeight::grams(
            Dimensions::canonical(5, 4, 250),
            VolumetricDivisor::fromDimFactorCmKg(5000),
        ));
        $this->assertSame(2, VolumetricWeight::grams(
            Dimensions::canonical(1667, 3, 1),
            VolumetricDivisor::fromDimFactorCmKg(5000),
        ));
    }

    public function testThreeMillionMillimetreCubeIsOverflowExceptionNotTypeError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Side exceeds the 64-bit safe range for volumetric weight.');
        VolumetricWeight::grams(
            Dimensions::canonical(3_000_000, 3_000_000, 3_000_000),
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
    }
}
