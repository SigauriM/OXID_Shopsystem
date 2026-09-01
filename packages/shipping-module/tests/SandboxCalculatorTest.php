<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Module\Sandbox\SandboxCalculator;
use OxidShipping\Module\Sandbox\SandboxRequest;
use OxidShipping\Module\Sandbox\SandboxView;
use OxidShipping\Module\Tariff\TariffLoadFailed;
use OxidShipping\Module\Tariff\TariffProvider;
use OxidShipping\Module\Tests\Support\FakeTariffRepository;
use OxidShipping\Module\Tests\Support\FakeTariffSource;
use OxidShipping\Module\Tests\Support\FixtureTariff;
use PHPUnit\Framework\TestCase;

final class SandboxCalculatorTest extends TestCase
{
    public function testLad500CurbIsSpeditionSixThousandCents(): void
    {
        $view = $this->calculator()->calculate(self::lad500('01067', false));

        $this->assertSame(SandboxView::QUOTED, $view->kind);
        $this->assertSame('known', $view->destination['type'] ?? null);
        $this->assertSame('de-01', $view->destination['zoneId'] ?? null);
        $this->assertSame('spedition', $view->pieces[0]['class'] ?? null);
        $this->assertSame(6100, $view->pieces[0]['girthMm'] ?? null);
        $this->assertSame(48400, $view->pieces[0]['volumetricGrams'] ?? null);
        $this->assertSame(48400, $view->pieces[0]['billableGrams'] ?? null);
        $this->assertSame(6000, $view->shipments[0]['baseCents'] ?? null);
        $this->assertSame(6000, $view->shipments[0]['totalCents'] ?? null);
        $this->assertSame(5, $view->shipments[0]['transitDays'] ?? null);
        $this->assertSame(6000, $view->totalCents);
        $this->assertSame(64, strlen($view->configHash));
        $this->assertNotSame('', $view->configVersion);
        $this->assertStringContainsString('"totalCents":6000', $view->quoteJson);
    }

    public function testLad500IndoorAddsFifteenHundredOnce(): void
    {
        $view = $this->calculator()->calculate(self::lad500('01067', true));

        $this->assertSame(SandboxView::QUOTED, $view->kind);
        $this->assertSame(7500, $view->totalCents);
        $this->assertSame(6000, $view->shipments[0]['baseCents'] ?? null);
        $this->assertSame(7500, $view->shipments[0]['totalCents'] ?? null);
        $ruleIds = array_column($view->trace, 'ruleId');
        $this->assertSame(1, substr_count(implode(',', $ruleIds), 'indoor'));
        $this->assertContains('indoor', $ruleIds);
        $this->assertSame(1500, self::deltaFor($view->trace, 'indoor'));
    }

    public function testZeroLengthIsValidationFailed(): void
    {
        $view = $this->calculator()->calculate(new SandboxRequest(
            0,
            440,
            110,
            14200,
            1,
            '01067',
            'DE',
            false,
        ));

        $this->assertSame(SandboxView::VALIDATION, $view->kind);
        $codes = array_column($view->validationErrors, 'code');
        $this->assertContains('dimension_not_positive', $codes);
        $this->assertSame('', $view->quoteJson);
    }

    public function testSwitzerlandIsCountryNotServed(): void
    {
        $view = $this->calculator()->calculate(self::lad500('8000', false, 'CH'));

        $this->assertSame(SandboxView::QUOTED, $view->kind);
        $this->assertSame('rejected', $view->destination['type'] ?? null);
        $this->assertSame('country_not_served', $view->destination['reason'] ?? null);
        $this->assertSame(0, $view->totalCents);
    }

    public function testForbiddenPostalIsZoneForbidden(): void
    {
        $view = $this->calculator()->calculate(self::lad500('18565', false));

        $this->assertSame(SandboxView::QUOTED, $view->kind);
        $this->assertSame('rejected', $view->destination['type'] ?? null);
        $this->assertSame('zone_forbidden', $view->destination['reason'] ?? null);
    }

    public function testSandboxDoesNotWriteNdjson(): void
    {
        $path = dirname(__DIR__, 3) . '/source/log/shipping-quotes.ndjson';
        $before = is_file($path) ? (string) file_get_contents($path) : '';

        $this->calculator()->calculate(self::lad500('01067', false));

        $after = is_file($path) ? (string) file_get_contents($path) : '';
        $this->assertSame($before, $after);
    }

    public function testIslandIndoorTraceFollowsPriorityAndTotalStaysEightyThreeHundred(): void
    {
        $seed = $this->calculator()->calculate(self::lad500('27498', true));
        $this->assertSame(8300, $seed->totalCents);
        $this->assertSame(7, $seed->shipments[0]['transitDays'] ?? null);
        $this->assertSame(['island', 'indoor'], self::surchargeOrder($seed->trace));
        $this->assertSame(800, self::deltaFor($seed->trace, 'island'));
        $this->assertSame(1500, self::deltaFor($seed->trace, 'indoor'));

        $document = TariffDocument::document(FixtureTariff::config());
        $document['rates']['surcharges']['indoor']['priority'] = 10;
        $document['rates']['surcharges']['island']['priority'] = 20;
        $swapped = new SandboxCalculator(
            new QuoteEngine(),
            new TariffProvider(new FakeTariffRepository(TariffDocument::fromArray($document))),
        );
        $view = $swapped->calculate(self::lad500('27498', true));

        $this->assertSame(8300, $view->totalCents);
        $this->assertSame(7, $view->shipments[0]['transitDays'] ?? null);
        $this->assertSame(['indoor', 'island'], self::surchargeOrder($view->trace));
        $this->assertSame(800, self::deltaFor($view->trace, 'island'));
        $this->assertSame(1500, self::deltaFor($view->trace, 'indoor'));
    }

    public function testMissingTariffIsUnavailableNotAnException(): void
    {
        $calculator = new SandboxCalculator(
            new QuoteEngine(),
            new FakeTariffSource(new TariffLoadFailed('No active shipping tariff.')),
        );

        $view = $calculator->calculate(self::lad500('01067', false));

        $this->assertSame(SandboxView::UNAVAILABLE, $view->kind);
        $this->assertSame('', $view->quoteJson);
    }

    public function testSpacedIntegerIsAFieldErrorNotThree(): void
    {
        $parsed = SandboxRequest::parse([
            'lengthMm' => '3 000',
            'widthMm' => '440',
            'heightMm' => '110',
            'weightGrams' => '14200',
            'quantity' => '1',
            'postalCode' => '01067',
            'country' => 'DE',
            'indoor' => '0',
        ]);

        $this->assertNull($parsed['request']);
        $this->assertSame('not_integer', $parsed['fieldErrors']['lengthMm']);
        $this->assertSame('3 000', $parsed['input']['lengthMm']);
    }

    private function calculator(): SandboxCalculator
    {
        return new SandboxCalculator(
            new QuoteEngine(),
            new TariffProvider(new FakeTariffRepository(FixtureTariff::config())),
        );
    }

    private static function lad500(string $postal, bool $indoor, string $country = 'DE'): SandboxRequest
    {
        return new SandboxRequest(5000, 440, 110, 14200, 1, $postal, $country, $indoor);
    }

    /**
     * @param list<array<string, mixed>> $trace
     * @return list<string>
     */
    private static function surchargeOrder(array $trace): array
    {
        $order = [];
        foreach ($trace as $line) {
            $ruleId = $line['ruleId'] ?? '';
            if ($ruleId === 'island' || $ruleId === 'indoor') {
                $order[] = $ruleId;
            }
        }

        return $order;
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function deltaFor(array $trace, string $ruleId): ?int
    {
        foreach ($trace as $line) {
            if (($line['ruleId'] ?? null) === $ruleId) {
                return is_int($line['deltaCents'] ?? null) ? $line['deltaCents'] : null;
            }
        }

        return null;
    }
}
