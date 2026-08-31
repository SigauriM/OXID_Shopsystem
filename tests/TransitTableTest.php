<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\TransitEntry;
use OxidShipping\Engine\Domain\TransitTable;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class TransitTableTest extends TestCase
{
    public function testDaysReturnsTheCellForClassAndZone(): void
    {
        $table = TransitTable::fromEntries([
            new TransitEntry(ShippingClass::Paket, 'de-01', 2),
            new TransitEntry(ShippingClass::Spedition, 'de-island', 7),
        ]);

        $this->assertSame(2, $table->days(ShippingClass::Paket, 'de-01'));
        $this->assertSame(7, $table->days(ShippingClass::Spedition, 'de-island'));
        $this->assertTrue($table->has(ShippingClass::Paket, 'de-01'));
        $this->assertFalse($table->has(ShippingClass::Spedition, 'de-01'));
    }

    public function testEntriesAreSortedByClassRankThenZoneId(): void
    {
        $table = TransitTable::fromEntries([
            new TransitEntry(ShippingClass::Spedition, 'de-01', 5),
            new TransitEntry(ShippingClass::Paket, 'de-hh', 2),
        ]);

        $entries = $table->entries();
        $this->assertCount(2, $entries);
        $this->assertSame(ShippingClass::Paket, $entries[0]->class);
        $this->assertSame('de-hh', $entries[0]->zoneId);
        $this->assertSame(ShippingClass::Spedition, $entries[1]->class);
        $this->assertSame('de-01', $entries[1]->zoneId);
    }

    public function testDuplicateClassAndZoneIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transit class and zone pairs must be unique.');
        TransitTable::fromEntries([
            new TransitEntry(ShippingClass::Paket, 'de-01', 2),
            new TransitEntry(ShippingClass::Paket, 'de-01', 4),
        ]);
    }

    public function testMissingCellIsProgrammerError(): void
    {
        $table = TransitTable::fromEntries([
            new TransitEntry(ShippingClass::Paket, 'de-01', 2),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No transit days for this class and zone.');
        $table->days(ShippingClass::Spedition, 'de-01');
    }

    public function testDaysBelowOneIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transit days must be 1 or greater.');
        new TransitEntry(ShippingClass::Paket, 'de-01', 0);
    }
}
