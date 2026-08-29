<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\VolumetricDivisor;
use PHPUnit\Framework\TestCase;

final class VolumetricDivisorTest extends TestCase
{
    public function testFactorFiveThousandYieldsFiveMillionMmG(): void
    {
        $divisor = VolumetricDivisor::fromDimFactorCmKg(5000);

        $this->assertSame(5000, $divisor->dimFactorCmKg());
        $this->assertSame(5_000_000, $divisor->mmG());
    }

    public function testFactorSixThousandYieldsSixMillionMmG(): void
    {
        $divisor = VolumetricDivisor::fromDimFactorCmKg(6000);

        $this->assertSame(6000, $divisor->dimFactorCmKg());
        $this->assertSame(6_000_000, $divisor->mmG());
    }

    public function testFactorFourThousandIsAccepted(): void
    {
        $divisor = VolumetricDivisor::fromDimFactorCmKg(4000);

        $this->assertSame(4000, $divisor->dimFactorCmKg());
        $this->assertSame(4_000_000, $divisor->mmG());
    }

    public function testFactorZeroIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'DIM factor must be between 1000 and 10000 (cm³/kg from the tariff).',
        );
        VolumetricDivisor::fromDimFactorCmKg(0);
    }

    public function testNegativeFactorIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VolumetricDivisor::fromDimFactorCmKg(-1);
    }

    public function testFactorFiveHundredIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VolumetricDivisor::fromDimFactorCmKg(500);
    }

    public function testScaledFiveMillionAsFactorIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VolumetricDivisor::fromDimFactorCmKg(5_000_000);
    }
}
