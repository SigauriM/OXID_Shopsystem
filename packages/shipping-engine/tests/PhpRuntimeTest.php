<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use PHPUnit\Framework\TestCase;

final class PhpRuntimeTest extends TestCase
{
    public function testRuntimeIs64BitPhp(): void
    {
        $this->assertSame(8, PHP_INT_SIZE);
    }
}
