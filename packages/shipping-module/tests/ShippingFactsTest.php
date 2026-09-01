<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Module\Logging\QuoteTraceLogger;
use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartMapper;
use OxidShipping\Module\Quote\QuoteFacade;
use OxidShipping\Module\Seo\ShippingFacts;
use OxidShipping\Module\Tariff\TariffProvider;
use OxidShipping\Module\Tests\Support\FakeTariffRepository;
use OxidShipping\Module\Tests\Support\FixtureTariff;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ShippingFactsTest extends TestCase
{
    public function testLad500HasThreeLiveStorefrontFactsInStableOrder(): void
    {
        $facts = $this->facts();
        $line = $this->lad500();

        $result = $facts->for($line);

        $this->assertCount(3, $result);
        $this->assertSame('de-01', $result[0]->zoneId);
        $this->assertSame('DE', $result[0]->country);
        $this->assertSame(['01067'], $result[0]->postalCodes);
        $this->assertIsString($result[0]->postalCodes[0]);
        $this->assertSame(6000, $result[0]->cents);
        $this->assertSame(5, $result[0]->days);

        $this->assertSame('de-hh', $result[1]->zoneId);
        $this->assertSame(['20095'], $result[1]->postalCodes);
        $this->assertSame(6000, $result[1]->cents);
        $this->assertSame(5, $result[1]->days);

        $this->assertSame('de-island', $result[2]->zoneId);
        $this->assertSame(['27498'], $result[2]->postalCodes);
        $this->assertSame(6800, $result[2]->cents);
        $this->assertSame(7, $result[2]->days);

        $zoneIds = array_map(static fn ($fact): string => $fact->zoneId, $result);
        $this->assertSame(['de-01', 'de-hh', 'de-island'], $zoneIds);
        $this->assertNotContains('de-forbidden', $zoneIds);
        $this->assertNotContains('at-w', $zoneIds);

        $again = $facts->for($line);
        $this->assertSame($result, $again);
    }

    private function facts(): ShippingFacts
    {
        $tariff = new TariffProvider(new FakeTariffRepository(FixtureTariff::config()));

        return new ShippingFacts(
            new QuoteFacade(
                new QuoteEngine(),
                $tariff,
                new CartMapper(),
                new NullLogger(),
                new QuoteTraceLogger(new NullLogger(), '1.0.0', sys_get_temp_dir() . '/shipping-quotes-test.ndjson'),
            ),
            $tariff,
            new NullLogger(),
        );
    }

    private function lad500(): CartLine
    {
        return new CartLine('LAD-500', 5.00, 0.44, 0.11, 14.2, 1.0);
    }
}
