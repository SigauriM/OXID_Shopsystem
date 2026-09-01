<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Module\Logging\QuoteTraceLogger;
use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartMapper;
use OxidShipping\Module\Quote\QuoteFacade;
use OxidShipping\Module\Tariff\TariffProvider;
use OxidShipping\Module\Tests\Support\FakeCartSource;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class QuoteTraceLoggerTest extends TestCase
{
    public function testQuotedWriteContainsHashAndSnapshotWithoutEmailAndDedupsInOneRequest(): void
    {
        $path = sys_get_temp_dir() . '/shipping-quotes-' . bin2hex(random_bytes(8)) . '.ndjson';
        @unlink($path);

        $facade = new QuoteFacade(
            new QuoteEngine(),
            new TariffProvider(),
            new CartMapper(),
            new NullLogger(),
            new QuoteTraceLogger(new NullLogger(), '1.0.0', $path),
        );

        $source = new FakeCartSource(
            [
                new CartLine('LAD-200', 2.00, 0.34, 0.08, 5.6, 1.0),
                new CartLine('LAD-500', 5.00, 0.44, 0.11, 14.2, 1.0),
            ],
            '01067',
            'DE',
        );

        $facade->quote($source);
        $facade->quote($source);

        $contents = (string) file_get_contents($path);
        $this->assertSame(1, substr_count($contents, "\n"));

        $row = json_decode(trim($contents), true);
        $this->assertIsArray($row);
        $this->assertSame('Quoted', $row['status']);
        $this->assertSame('1.0.0', $row['moduleVersion']);
        $this->assertIsString($row['configHash']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row['configHash']);
        $this->assertIsArray($row['quote']);
        $this->assertSame('01067', $row['quote']['snapshot']['postalCode']);
        $this->assertSame(6600, $row['quote']['totalCents']);

        $encoded = json_encode($row);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('@', $encoded);
        $this->assertStringNotContainsString('email', $encoded);

        @unlink($path);
    }
}
