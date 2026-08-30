<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Grouping\GroupablePiece;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\ShippingClass;
use PHPUnit\Framework\TestCase;

final class GroupablePieceTest extends TestCase
{
    public function testEmptyZoneIdIsProgrammerError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone id length must be between 1 and 32.');
        new GroupablePiece($this->classified(), '', false);
    }

    private function classified(): ClassifiedPiece
    {
        $piece = (new PieceFactory())->expand(
            [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        )[0];

        return new ClassifiedPiece($piece, ShippingClass::Paket);
    }
}
