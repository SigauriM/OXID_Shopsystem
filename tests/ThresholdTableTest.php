<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\ClassFloor;
use OxidShipping\Engine\Domain\ThresholdTable;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class ThresholdTableTest extends TestCase
{
    public function testExactlyAboveIsSilenceAndOneAboveVotes(): void
    {
        $table = ThresholdTable::fromEntries([
            new ClassFloor(3000, ShippingClass::Sperrgut),
            new ClassFloor(3600, ShippingClass::Spedition),
        ]);

        $this->assertNull($table->floor(3000));
        $this->assertSame(ShippingClass::Sperrgut, $table->floor(3001));
        $this->assertSame(ShippingClass::Sperrgut, $table->floor(3600));
        $this->assertSame(ShippingClass::Spedition, $table->floor(3601));
    }

    public function testReversedEntriesYieldTheSameFloor(): void
    {
        $forward = ThresholdTable::fromEntries([
            new ClassFloor(3000, ShippingClass::Sperrgut),
            new ClassFloor(3600, ShippingClass::Spedition),
        ]);
        $reversed = ThresholdTable::fromEntries([
            new ClassFloor(3600, ShippingClass::Spedition),
            new ClassFloor(3000, ShippingClass::Sperrgut),
        ]);

        $this->assertSame($forward->floor(3000), $reversed->floor(3000));
        $this->assertSame($forward->floor(3001), $reversed->floor(3001));
        $this->assertSame($forward->floor(3600), $reversed->floor(3600));
        $this->assertSame($forward->floor(3601), $reversed->floor(3601));
    }

    public function testSingleSpeditionEntrySkipsSperrgut(): void
    {
        $table = ThresholdTable::fromEntries([
            new ClassFloor(20000, ShippingClass::Spedition),
        ]);

        $this->assertNull($table->floor(20000));
        $this->assertSame(ShippingClass::Spedition, $table->floor(20001));
    }

    public function testEmptyEntriesAreProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold table must not be empty.');
        ThresholdTable::fromEntries([]);
    }

    public function testDuplicateAboveIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold above values must be unique.');
        ThresholdTable::fromEntries([
            new ClassFloor(3000, ShippingClass::Sperrgut),
            new ClassFloor(3000, ShippingClass::Spedition),
        ]);
    }

    public function testNonIncreasingClassRankIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold class rank must strictly increase with above.');
        ThresholdTable::fromEntries([
            new ClassFloor(3000, ShippingClass::Spedition),
            new ClassFloor(3600, ShippingClass::Sperrgut),
        ]);
    }

    public function testRepeatedClassRankIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold class rank must strictly increase with above.');
        ThresholdTable::fromEntries([
            new ClassFloor(3000, ShippingClass::Sperrgut),
            new ClassFloor(3600, ShippingClass::Sperrgut),
        ]);
    }
}
