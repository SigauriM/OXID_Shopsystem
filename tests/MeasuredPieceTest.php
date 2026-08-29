<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Measurement\Dimensions;
use OxidShipping\Engine\Measurement\MeasuredPiece;
use PHPUnit\Framework\TestCase;

final class MeasuredPieceTest extends TestCase
{
    public function testZeroActualGramsIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('actualGrams must be greater than 0.');
        MeasuredPiece::from(
            'line-1',
            0,
            0,
            Dimensions::canonical(100, 100, 100),
            0,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
    }

    public function testNegativeLineIndexIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('lineIndex and pieceIndex must be 0 or greater.');
        MeasuredPiece::from(
            'line-1',
            -7,
            0,
            Dimensions::canonical(100, 100, 100),
            500,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
    }

    public function testNegativePieceIndexIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('lineIndex and pieceIndex must be 0 or greater.');
        MeasuredPiece::from(
            'line-1',
            0,
            -3,
            Dimensions::canonical(100, 100, 100),
            500,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
    }

    public function testZeroIndexesAreAccepted(): void
    {
        $piece = MeasuredPiece::from(
            'line-1',
            0,
            0,
            Dimensions::canonical(100, 100, 100),
            500,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );

        $this->assertSame(0, $piece->lineIndex);
        $this->assertSame(0, $piece->pieceIndex);
    }
}
