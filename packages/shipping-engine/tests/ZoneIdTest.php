<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\ZoneId;
use PHPUnit\Framework\TestCase;

final class ZoneIdTest extends TestCase
{
    public function testAcceptedExamplesPass(): void
    {
        $this->expectNotToPerformAssertions();
        ZoneId::assert('de-01');
        ZoneId::assert('de-hh');
        ZoneId::assert('de-forbidden');
        ZoneId::assert('at-w');
    }

    public function testEmptyIdIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone id length must be between 1 and 32.');
        ZoneId::assert('');
    }

    public function testUppercaseIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone id does not match the required pattern.');
        ZoneId::assert('DE-01');
    }

    public function testTooLongIdIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone id length must be between 1 and 32.');
        ZoneId::assert(str_repeat('a', 33));
    }
}
