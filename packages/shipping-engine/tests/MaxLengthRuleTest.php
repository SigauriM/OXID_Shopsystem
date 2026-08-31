<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Classification\MaxLengthRule;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class MaxLengthRuleTest extends TestCase
{
    public function testFourThousandBoundaryIsIsolatedOnTheLengthRule(): void
    {
        $rule = new MaxLengthRule(TestConfig::classification()->maxLength);
        $factory = new PieceFactory();
        $divisor = VolumetricDivisor::fromDimFactorCmKg(5000);

        $atThreshold = $factory->expand(
            [new OrderLine('length-4000', 4000, 10, 10, 1, 1)],
            $divisor,
        )[0];
        $above = $factory->expand(
            [new OrderLine('length-4001', 4001, 10, 10, 1, 1)],
            $divisor,
        )[0];

        $this->assertSame(4000, $atThreshold->dimensions->lengthMm);
        $this->assertSame(ShippingClass::Sperrgut, $rule->floor($atThreshold));

        $this->assertSame(4001, $above->dimensions->lengthMm);
        $this->assertSame(ShippingClass::Spedition, $rule->floor($above));
    }
}
