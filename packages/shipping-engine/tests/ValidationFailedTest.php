<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Result\ValidationFailed;
use PHPUnit\Framework\TestCase;

final class ValidationFailedTest extends TestCase
{
    public function testEmptyErrorListIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ValidationFailed([]);
    }
}
