<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Tests\Support\TestConfig;
use OxidShipping\Module\Tariff\TariffLoadFailed;
use OxidShipping\Module\Tariff\TariffProvider;
use OxidShipping\Module\Tests\Support\FakeTariffRepository;
use OxidShipping\Module\Tests\Support\FixtureTariff;
use PHPUnit\Framework\TestCase;

final class TariffProviderTest extends TestCase
{
    public function testProviderReturnsTariffConfigAndGoldenQuoteIs6600Cents(): void
    {
        $provider = new TariffProvider(new FakeTariffRepository(FixtureTariff::config()));
        $config = $provider->get();

        $this->assertInstanceOf(TariffConfig::class, $config);
        $this->assertSame(
            TariffDocument::hash(TestConfig::tariff()),
            TariffDocument::hash($config),
        );
        $this->assertSame($config, $provider->get());

        $result = (new QuoteEngine())->quote(new QuoteRequest(
            [
                new OrderLine('LAD-200', 2000, 340, 80, 5600, 1),
                new OrderLine('LAD-500', 5000, 440, 110, 14200, 1),
            ],
            '01067',
            'DE',
            false,
            $config,
        ));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertSame(6600, $result->totalCents);
    }

    public function testNoActiveRowThrowsTariffLoadFailed(): void
    {
        $provider = new TariffProvider(new FakeTariffRepository());

        $this->expectException(TariffLoadFailed::class);
        $this->expectExceptionMessage('No active shipping tariff.');
        $provider->get();
    }

    public function testBrokenPayloadThrowsTariffLoadFailed(): void
    {
        $provider = new TariffProvider(FakeTariffRepository::withBrokenActivePayload());

        $this->expectException(TariffLoadFailed::class);
        $this->expectExceptionMessage('Shop tariff document is invalid.');
        $provider->get();
    }

    public function testFixtureKeepsDresdenPostalCodeAsString(): void
    {
        $path = dirname(__DIR__) . '/config/shop-tariff.json';
        $json = file_get_contents($path);
        $this->assertNotFalse($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);

        $found = null;
        foreach ($decoded['zones']['directory']['postalEntries'] as $entry) {
            $this->assertIsArray($entry);
            if (($entry['postalCode'] ?? null) === '01067' || ($entry['postalCode'] ?? null) === 1067) {
                $found = $entry['postalCode'];
                break;
            }
        }

        $this->assertSame('01067', $found);
        $this->assertIsString($found);
    }
}
