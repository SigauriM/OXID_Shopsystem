<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\WeightRateStep;
use OxidShipping\Engine\Domain\WeightRateTable;
use PHPUnit\Framework\TestCase;

final class WeightRateTableTest extends TestCase
{
    public function testBillableEqualToUpToTakesThatStep(): void
    {
        $table = WeightRateTable::fromEntries([
            new WeightRateStep(1000, 400),
            new WeightRateStep(20000, 600),
        ]);

        $this->assertSame(400, $table->rate(1000));
        $this->assertSame(600, $table->rate(1001));
        $this->assertSame(600, $table->rate(20000));
    }

    public function testBillableAboveLastUpToIsProgrammerError(): void
    {
        $table = WeightRateTable::fromEntries([
            new WeightRateStep(1000, 400),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No weight rate covers the billable grams.');
        $table->rate(1001);
    }

    public function testBillableGramsZeroIsProgrammerError(): void
    {
        $table = WeightRateTable::fromEntries([
            new WeightRateStep(1000, 400),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billable grams must be 1 or greater.');
        $table->rate(0);
    }

    public function testBillableGramsNegativeIsProgrammerError(): void
    {
        $table = WeightRateTable::fromEntries([
            new WeightRateStep(1000, 400),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billable grams must be 1 or greater.');
        $table->rate(-1);
    }

    public function testReversedEntriesYieldTheSameRate(): void
    {
        $forward = WeightRateTable::fromEntries([
            new WeightRateStep(1000, 400),
            new WeightRateStep(20000, 600),
        ]);
        $reversed = WeightRateTable::fromEntries([
            new WeightRateStep(20000, 600),
            new WeightRateStep(1000, 400),
        ]);

        $this->assertSame($forward->rate(1), $reversed->rate(1));
        $this->assertSame($forward->rate(1000), $reversed->rate(1000));
        $this->assertSame($forward->rate(1001), $reversed->rate(1001));
        $this->assertSame($forward->rate(20000), $reversed->rate(20000));
    }

    public function testReversedEntriesYieldTheSameStepsOrder(): void
    {
        $forward = WeightRateTable::fromEntries([
            new WeightRateStep(1000, 400),
            new WeightRateStep(20000, 600),
        ]);
        $reversed = WeightRateTable::fromEntries([
            new WeightRateStep(20000, 600),
            new WeightRateStep(1000, 400),
        ]);

        $this->assertSame(
            array_map(static fn (WeightRateStep $step): int => $step->upTo, $forward->steps()),
            array_map(static fn (WeightRateStep $step): int => $step->upTo, $reversed->steps()),
        );
        $this->assertSame([1000, 20000], array_map(
            static fn (WeightRateStep $step): int => $step->upTo,
            $forward->steps(),
        ));
    }

    public function testEmptyEntriesAreProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight rate table must not be empty.');
        WeightRateTable::fromEntries([]);
    }

    public function testUpToBelowOneIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight rate upTo must be 1 or greater.');
        new WeightRateStep(0, 400);
    }

    public function testCentsBelowOneIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight rate cents must be 1 or greater.');
        new WeightRateStep(1000, 0);
    }

    public function testDuplicateUpToIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight rate upTo values must be unique.');
        WeightRateTable::fromEntries([
            new WeightRateStep(1000, 400),
            new WeightRateStep(1000, 600),
        ]);
    }
}
