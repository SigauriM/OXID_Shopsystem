<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\Measurement\PieceFactory;
use PHPUnit\Framework\TestCase;

final class PieceFactoryTest extends TestCase
{
    public function testBillableFollowsActualWhenActualIsHigher(): void
    {
        $pieces = $this->expand([
            new OrderLine('line-1', 100, 100, 100, 20_000, 1),
        ]);

        $this->assertCount(1, $pieces);
        $this->assertSame(20_000, $pieces[0]->actualGrams);
        $this->assertSame(200, $pieces[0]->volumetricGrams);
        $this->assertSame(20_000, $pieces[0]->billableGrams);
    }

    public function testBillableSwitchAroundTwelveKilogramsVolume(): void
    {
        $cases = [
            [11_999, 12_000, 12_000],
            [12_000, 12_000, 12_000],
            [12_001, 12_000, 12_001],
        ];

        foreach ($cases as [$actualGrams, $volumetricGrams, $billableGrams]) {
            $pieces = $this->expand([
                new OrderLine('line-1', 500, 400, 300, $actualGrams, 1),
            ]);

            $this->assertCount(1, $pieces);
            $this->assertSame($actualGrams, $pieces[0]->actualGrams);
            $this->assertSame($volumetricGrams, $pieces[0]->volumetricGrams);
            $this->assertSame($billableGrams, $pieces[0]->billableGrams);
        }
    }

    public function testBillableFollowsVolumeWhenPieceIsLightAndBulky(): void
    {
        $pieces = $this->expand([
            new OrderLine('line-1', 500, 400, 300, 1, 1),
        ]);

        $this->assertCount(1, $pieces);
        $this->assertSame(1, $pieces[0]->actualGrams);
        $this->assertSame(12_000, $pieces[0]->volumetricGrams);
        $this->assertSame(12_000, $pieces[0]->billableGrams);
    }

    public function testQuantityTwoYieldsTwoIdenticalPiecesNotDoubledGeometry(): void
    {
        $pieces = $this->expand([
            new OrderLine('line-1', 20, 100, 10, 1000, 2),
        ]);

        $this->assertCount(2, $pieces);

        $this->assertSame('line-1', $pieces[0]->lineId);
        $this->assertSame('line-1', $pieces[1]->lineId);
        $this->assertSame(0, $pieces[0]->lineIndex);
        $this->assertSame(0, $pieces[1]->lineIndex);
        $this->assertSame(0, $pieces[0]->pieceIndex);
        $this->assertSame(1, $pieces[1]->pieceIndex);

        foreach ($pieces as $piece) {
            $this->assertSame(100, $piece->dimensions->lengthMm);
            $this->assertSame(20, $piece->dimensions->widthMm);
            $this->assertSame(10, $piece->dimensions->heightMm);
            $this->assertSame(160, $piece->dimensions->girthMm());
            $this->assertSame(1000, $piece->actualGrams);
            $this->assertSame(1000, $piece->billableGrams);
        }
    }

    public function testTwoLinesBecomeTwoPiecesWithStableLineIndexes(): void
    {
        $pieces = $this->expand([
            new OrderLine('line-a', 100, 100, 100, 1, 1),
            new OrderLine('line-b', 200, 50, 50, 1, 1),
        ]);

        $this->assertCount(2, $pieces);
        $this->assertSame('line-a', $pieces[0]->lineId);
        $this->assertSame(0, $pieces[0]->lineIndex);
        $this->assertSame(0, $pieces[0]->pieceIndex);
        $this->assertSame('line-b', $pieces[1]->lineId);
        $this->assertSame(1, $pieces[1]->lineIndex);
        $this->assertSame(0, $pieces[1]->pieceIndex);
    }

    /**
     * @param list<OrderLine> $lines
     * @return list<MeasuredPiece>
     */
    private function expand(array $lines): array
    {
        return (new PieceFactory())->expand(
            $lines,
            VolumetricDivisor::fromDimFactorCmKg(5000),
        );
    }
}
