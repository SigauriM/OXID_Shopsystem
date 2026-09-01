<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartMapper;
use OxidShipping\Module\Mapping\MappedCart;
use OxidShipping\Module\Mapping\MappingFailed;
use PHPUnit\Framework\TestCase;

final class CartMapperTest extends TestCase
{
    public function testMetersAndKilogramsRoundToMillimetresAndGrams(): void
    {
        $mapped = (new CartMapper())->map(
            [new CartLine('LAD-200', 2.00, 0.34, 0.08, 5.6, 1.0)],
            '01067',
            'DE',
        );

        $this->assertInstanceOf(MappedCart::class, $mapped);
        $this->assertCount(1, $mapped->lines);
        $this->assertSame(2000, $mapped->lines[0]->lengthMm);
        $this->assertSame(340, $mapped->lines[0]->widthMm);
        $this->assertSame(80, $mapped->lines[0]->heightMm);
        $this->assertSame(5600, $mapped->lines[0]->weightGrams);
        $this->assertSame(1, $mapped->lines[0]->quantity);
    }

    public function testNarrowWidthDoesNotLoseAMillimetreToFloatNoise(): void
    {
        $mapped = (new CartMapper())->map(
            [new CartLine('LAD-200', 2.00, 0.34, 0.08, 5.6, 1.0)],
            '01067',
            'DE',
        );

        $this->assertInstanceOf(MappedCart::class, $mapped);
        $this->assertSame(340, $mapped->lines[0]->widthMm);
    }

    public function testIntegerQuantityMapsAndFractionalQuantityFails(): void
    {
        $mapper = new CartMapper();
        $ok = $mapper->map(
            [new CartLine('LAD-200', 2.00, 0.34, 0.08, 5.6, 1.0)],
            '01067',
            'DE',
        );
        $this->assertInstanceOf(MappedCart::class, $ok);
        $this->assertSame(1, $ok->lines[0]->quantity);

        $failed = $mapper->map(
            [new CartLine('LAD-200', 2.00, 0.34, 0.08, 5.6, 0.5)],
            '01067',
            'DE',
        );
        $this->assertInstanceOf(MappingFailed::class, $failed);
        $this->assertSame('quantity', $failed->field);
    }

    public function testPostalCodeStaysAString(): void
    {
        $mapped = (new CartMapper())->map(
            [new CartLine('LAD-200', 2.00, 0.34, 0.08, 5.6, 1.0)],
            '01067',
            'DE',
        );

        $this->assertInstanceOf(MappedCart::class, $mapped);
        $this->assertSame('01067', $mapped->postalCode);
        $this->assertIsString($mapped->postalCode);
    }
}
