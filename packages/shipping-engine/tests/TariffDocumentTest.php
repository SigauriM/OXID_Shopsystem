<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\BaseRateConfig;
use OxidShipping\Engine\Domain\IndoorSurchargeRate;
use OxidShipping\Engine\Domain\IslandSurchargeRate;
use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\OrderWeightThreshold;
use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\ServedCountries;
use OxidShipping\Engine\Domain\SurchargeConfig;
use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Domain\TransitEntry;
use OxidShipping\Engine\Domain\TransitTable;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Domain\WeightRateStep;
use OxidShipping\Engine\Domain\WeightRateTable;
use OxidShipping\Engine\Domain\ZoneConfig;
use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Domain\ZoneDirectory;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class TariffDocumentTest extends TestCase
{
    public function testTwoIndependentTestConfigsShareAHash(): void
    {
        $this->assertSame(
            TariffDocument::hash(TestConfig::tariff()),
            TariffDocument::hash(TestConfig::tariff()),
        );
    }

    public function testPrettyPrintAndKeyPermutationDoNotChangeHash(): void
    {
        $config = TestConfig::tariff();
        $expected = TariffDocument::hash($config);
        $document = TariffDocument::document($config);

        $compact = json_encode($document, JSON_THROW_ON_ERROR);
        $pretty = json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $this->assertNotFalse($compact);
        $this->assertNotFalse($pretty);

        $this->assertSame($expected, TariffDocument::hash(TariffDocument::fromJson($compact)));
        $this->assertSame($expected, TariffDocument::hash(TariffDocument::fromJson($pretty)));

        $shuffled = $this->reverseObjectKeys($document);
        $shuffled['rates'] = $this->reverseObjectKeys($shuffled['rates']);
        $shuffledJson = json_encode($shuffled, JSON_THROW_ON_ERROR);
        $this->assertNotFalse($shuffledJson);
        $this->assertSame($expected, TariffDocument::hash(TariffDocument::fromJson($shuffledJson)));
    }

    public function testInsertionOrderOfConfigPartsDoesNotChangeHash(): void
    {
        $expected = TariffDocument::hash(TestConfig::tariff());

        $reversedZones = new ZoneConfig(
            ServedCountries::fromCodes(['AT', 'DE']),
            ZoneDirectory::fromEntries(
                [
                    new ZoneDefinition('de-island', false),
                    new ZoneDefinition('at-w', false),
                    new ZoneDefinition('de-forbidden', true),
                    new ZoneDefinition('de-hh', false),
                    new ZoneDefinition('de-01', false),
                ],
                [
                    new PostalZoneEntry('DE', '27498', 'de-island'),
                    new PostalZoneEntry('AT', '1010', 'at-w'),
                    new PostalZoneEntry('DE', '18565', 'de-forbidden'),
                    new PostalZoneEntry('DE', '20095', 'de-hh'),
                    new PostalZoneEntry('DE', '01067', 'de-01'),
                ],
            ),
        );

        $rates = TestConfig::rates();
        $reversedRates = new TariffRates(
            new BaseRateConfig(
                WeightRateTable::fromEntries([
                    new WeightRateStep(3_375_000_000, 900),
                    new WeightRateStep(20000, 600),
                    new WeightRateStep(1000, 400),
                ]),
                $rates->base->sperrgut,
                $rates->base->spedition,
            ),
            $rates->surcharges,
            TransitTable::fromEntries(array_reverse($rates->transit->entries())),
        );

        $reversedServed = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            new ZoneConfig(
                ServedCountries::fromCodes(['DE', 'AT']),
                TestConfig::zones()->directory,
            ),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            TestConfig::rates(),
        );

        $reversedAssembly = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            $reversedZones,
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            $reversedRates,
        );

        $this->assertSame($expected, TariffDocument::hash($reversedServed));
        $this->assertSame($expected, TariffDocument::hash($reversedAssembly));

        $islandA = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            new TariffRates(
                $rates->base,
                new SurchargeConfig(
                    new IslandSurchargeRate(10, 800, ['de-island', 'de-hh']),
                    $rates->surcharges->indoor,
                ),
                $rates->transit,
            ),
        );
        $islandB = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            new TariffRates(
                $rates->base,
                new SurchargeConfig(
                    new IslandSurchargeRate(10, 800, ['de-hh', 'de-island']),
                    $rates->surcharges->indoor,
                ),
                $rates->transit,
            ),
        );
        $this->assertSame(TariffDocument::hash($islandA), TariffDocument::hash($islandB));
    }

    public function testDimFactorFiveThousandIsNotScaledInPayload(): void
    {
        $config = TestConfig::tariff();
        $payload = TariffDocument::payload($config);
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertNotFalse($json);

        $this->assertSame(5000, $payload['dimFactorCmKg']);
        $this->assertStringContainsString('"dimFactorCmKg":5000', $json);
        $this->assertStringNotContainsString('"dimFactorCmKg":5000000', $json);
        $this->assertFalse($this->hasKey($payload, 'mmG'));

        $this->assertNotSame(
            TariffDocument::hash($config),
            TariffDocument::hash(TestConfig::tariff(dimFactorCmKg: 6000)),
        );
    }

    public function testVersionIsInDocumentNotInPayloadOrHash(): void
    {
        $a = TestConfig::tariff('test-2026');
        $b = TestConfig::tariff('test-2026-b');

        $this->assertSame(TariffDocument::hash($a), TariffDocument::hash($b));
        $this->assertArrayNotHasKey('version', TariffDocument::payload($a));
        $this->assertSame('test-2026', TariffDocument::document($a)['version']);
        $this->assertSame('test-2026-b', TariffDocument::document($b)['version']);
    }

    public function testRateAndThresholdChangesChangeTheHash(): void
    {
        $base = TestConfig::tariff();
        $rates = TestConfig::rates();

        $indoor = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            new TariffRates(
                $rates->base,
                new SurchargeConfig(
                    $rates->surcharges->island,
                    new IndoorSurchargeRate(20, 1501),
                ),
                $rates->transit,
            ),
        );
        $island = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            new TariffRates(
                $rates->base,
                new SurchargeConfig(
                    new IslandSurchargeRate(10, 801, ['de-island']),
                    $rates->surcharges->indoor,
                ),
                $rates->transit,
            ),
        );
        $threshold = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            new OrderWeightThreshold(40001),
            $rates,
        );

        $droppedPostal = new ZoneConfig(
            ServedCountries::fromCodes(['DE', 'AT']),
            ZoneDirectory::fromEntries(
                [
                    new ZoneDefinition('de-01', false),
                    new ZoneDefinition('de-hh', false),
                    new ZoneDefinition('de-forbidden', true),
                    new ZoneDefinition('at-w', false),
                    new ZoneDefinition('de-island', false),
                ],
                [
                    new PostalZoneEntry('DE', '01067', 'de-01'),
                    new PostalZoneEntry('DE', '18565', 'de-forbidden'),
                    new PostalZoneEntry('AT', '1010', 'at-w'),
                    new PostalZoneEntry('DE', '27498', 'de-island'),
                ],
            ),
        );
        $postal = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            $droppedPostal,
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            TestConfig::rates(),
        );

        $baseHash = TariffDocument::hash($base);
        $this->assertNotSame($baseHash, TariffDocument::hash($indoor));
        $this->assertNotSame($baseHash, TariffDocument::hash($island));
        $this->assertNotSame($baseHash, TariffDocument::hash($threshold));
        $this->assertNotSame($baseHash, TariffDocument::hash($postal));
    }

    public function testDresdenPostalCodeStaysAString(): void
    {
        $payload = TariffDocument::payload(TestConfig::tariff());
        $found = false;
        foreach ($payload['zones']['directory']['postalEntries'] as $entry) {
            if ($entry['zoneId'] === 'de-01') {
                $this->assertSame('01067', $entry['postalCode']);
                $this->assertIsString($entry['postalCode']);
                $found = true;
            }
        }
        $this->assertTrue($found);

        $document = TariffDocument::document(TestConfig::tariff());
        foreach ($document['zones']['directory']['postalEntries'] as $index => $entry) {
            if ($entry['postalCode'] === '01067') {
                $document['zones']['directory']['postalEntries'][$index]['postalCode'] = 1067;
                break;
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        TariffDocument::fromArray($document);
    }

    public function testFromJsonOfDresdenPostalResolvesTheDirectory(): void
    {
        $json = json_encode(TariffDocument::document(TestConfig::tariff()), JSON_THROW_ON_ERROR);
        $this->assertNotFalse($json);
        $config = TariffDocument::fromJson($json);
        $lookup = $config->zones->directory->lookup('01067', 'DE');
        $this->assertInstanceOf(KnownZone::class, $lookup);
        $this->assertSame('de-01', $lookup->zoneId);
    }

    public function testUnknownRootKeyIsRejected(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        $document['defaultZone'] = 'de-01';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown document key: defaultZone');
        TariffDocument::fromArray($document);
    }

    public function testUnknownZonesKeyIsRejected(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        $document['zones']['catchAll'] = true;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown document key: catchAll');
        TariffDocument::fromArray($document);
    }

    public function testUnknownMmGKeyIsRejected(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        $document['mmG'] = 5_000_000;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown document key: mmG');
        TariffDocument::fromArray($document);
    }

    public function testUnknownSurchargeKeyIsRejected(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        $document['rates']['surcharges']['fuel'] = ['cents' => 1, 'priority' => 30];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown document key: fuel');
        TariffDocument::fromArray($document);
    }

    public function testUnknownIndoorKeyIsRejected(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        $document['rates']['surcharges']['indoor']['classes'] = ['paket'];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown document key: classes');
        TariffDocument::fromArray($document);
    }

    public function testMissingDimFactorKeyIsRejected(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        unset($document['dimFactorCmKg']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing document key: dimFactorCmKg');
        TariffDocument::fromArray($document);
    }

    public function testPostalEntryCapIsEnforced(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        $document['zones']['directory']['postalEntries'] = array_fill(0, 100_001, [
            'country' => 'DE',
            'postalCode' => '01067',
            'zoneId' => 'de-01',
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Postal entries exceed the document cap.');
        TariffDocument::fromArray($document);
    }

    public function testRoundTripPreservesHashIncludingDimFactorOneThousand(): void
    {
        $a = TestConfig::tariff();
        $b = TestConfig::tariff(dimFactorCmKg: 1000);
        $this->assertSame(
            TariffDocument::hash($a),
            TariffDocument::hash(TariffDocument::fromArray(TariffDocument::document($a))),
        );
        $this->assertSame(
            TariffDocument::hash($b),
            TariffDocument::hash(TariffDocument::fromArray(TariffDocument::document($b))),
        );
        $this->assertSame('test-2026', TariffDocument::fromArray(TariffDocument::document($a))->version);
    }

    public function testHashFormatIsLowercaseSha256AndStable(): void
    {
        $config = TestConfig::tariff();
        $hash = TariffDocument::hash($config);
        $this->assertSame(1, preg_match('/^[a-f0-9]{64}$/', $hash));
        $this->assertSame($hash, TariffDocument::hash($config));
        $this->assertNotSame($hash, strtoupper($hash));
    }

    public function testEmptyIslandListHashesDifferentlyFromDefault(): void
    {
        $withIsland = TestConfig::tariff();
        $emptyIsland = new TariffConfig(
            'test-2026',
            VolumetricDivisor::fromDimFactorCmKg(5000),
            TestConfig::zones(),
            TestConfig::classification(),
            TestConfig::orderWeightThreshold(),
            TestConfig::rates([]),
        );

        $this->assertSame([], TariffDocument::payload($emptyIsland)['rates']['surcharges']['island']['zoneIds']);
        $this->assertNotSame(TariffDocument::hash($withIsland), TariffDocument::hash($emptyIsland));
    }

    public function testFromArrayRejectsFloatCents(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        $document['rates']['surcharges']['indoor']['cents'] = 1500.0;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document number fields must be int.');
        TariffDocument::fromArray($document);
    }

    public function testFromArrayRejectsNumericStringCents(): void
    {
        $document = TariffDocument::document(TestConfig::tariff());
        $document['rates']['surcharges']['indoor']['cents'] = '1500';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document number fields must be int.');
        TariffDocument::fromArray($document);
    }

    public function testTransitPayloadIsSortedByClassRankThenZoneId(): void
    {
        $transit = TariffDocument::payload(TestConfig::tariff())['rates']['transit'];
        $classes = array_map(static fn (array $row): string => $row['class'], $transit);
        $paket = array_keys($classes, 'paket', true);
        $sperrgut = array_keys($classes, 'sperrgut', true);
        $spedition = array_keys($classes, 'spedition', true);
        $this->assertNotSame([], $paket);
        $this->assertNotSame([], $sperrgut);
        $this->assertNotSame([], $spedition);
        $this->assertLessThan(min($sperrgut), max($paket));
        $this->assertLessThan(min($spedition), max($sperrgut));
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function reverseObjectKeys(array $value): array
    {
        $keys = array_reverse(array_keys($value));
        $reversed = [];
        foreach ($keys as $key) {
            $reversed[$key] = $value[$key];
        }

        return $reversed;
    }

    /**
     * @param array<mixed> $value
     */
    private function hasKey(array $value, string $needle): bool
    {
        foreach ($value as $key => $item) {
            if ($key === $needle) {
                return true;
            }
            if (is_array($item) && $this->hasKey($item, $needle)) {
                return true;
            }
        }

        return false;
    }
}
