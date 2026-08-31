<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\BillableWeightRule;
use OxidShipping\Engine\Classification\ClassificationRuleSet;
use OxidShipping\Engine\Classification\GirthRule;
use OxidShipping\Engine\Classification\MaxLengthRule;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class ClassificationRuleSetTest extends TestCase
{
    public function testRuleOrderDoesNotChangeClass(): void
    {
        $config = TestConfig::classification();
        $piece = $this->piece(new OrderLine('mixed', 2001, 800, 10, 1, 1));
        $girth = new GirthRule($config->girth);
        $length = new MaxLengthRule($config->maxLength);
        $weight = new BillableWeightRule($config->billableWeight);

        $forward = (new ClassificationRuleSet([$girth, $length, $weight]))->classFor($piece);
        $reverse = (new ClassificationRuleSet([$weight, $length, $girth]))->classFor($piece);

        $this->assertSame($forward, $reverse);
        $this->assertSame(ShippingClass::Spedition, $forward);
    }

    public function testEmptyRuleListIsAlwaysPaket(): void
    {
        $piece = $this->piece(new OrderLine('cube', 2001, 800, 10, 1, 1));

        $this->assertSame(
            ShippingClass::Paket,
            (new ClassificationRuleSet([]))->classFor($piece),
        );
    }

    private function piece(OrderLine $line): MeasuredPiece
    {
        return (new PieceFactory())->expand(
            [$line],
            VolumetricDivisor::fromDimFactorCmKg(5000),
        )[0];
    }
}
