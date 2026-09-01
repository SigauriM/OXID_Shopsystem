<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Module\Adapter\SingleArticleSource;
use OxidShipping\Module\Mapping\CartLine;
use PHPUnit\Framework\TestCase;

final class SingleArticleSourceTest extends TestCase
{
    public function testExposesOneLineInMetersAndKilograms(): void
    {
        $line = new CartLine('LAD-500', 5.00, 0.44, 0.11, 14.2, 1.0);
        $source = new SingleArticleSource($line, '01067', 'DE');

        $this->assertSame([$line], $source->lines());
        $this->assertSame('01067', $source->postalCode());
        $this->assertSame('DE', $source->countryIso());
        $this->assertSame(5.00, $source->lines()[0]->lengthMeters);
        $this->assertSame(14.2, $source->lines()[0]->weightKg);
        $this->assertSame([0 => 'LAD-500'], $source->lineLabels());
    }
}
