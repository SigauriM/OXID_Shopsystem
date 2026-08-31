<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Input;

use OxidShipping\Engine\Domain\BaseRateConfig;
use OxidShipping\Engine\Domain\ClassFloor;
use OxidShipping\Engine\Domain\ClassificationConfig;
use OxidShipping\Engine\Domain\IndoorSurchargeRate;
use OxidShipping\Engine\Domain\IslandSurchargeRate;
use OxidShipping\Engine\Domain\OrderWeightThreshold;
use OxidShipping\Engine\Domain\PostalZoneEntry;
use OxidShipping\Engine\Domain\ServedCountries;
use OxidShipping\Engine\Domain\SurchargeConfig;
use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Domain\ThresholdTable;
use OxidShipping\Engine\Domain\TransitEntry;
use OxidShipping\Engine\Domain\TransitTable;
use OxidShipping\Engine\Domain\VolumetricDivisor;
use OxidShipping\Engine\Domain\WeightRateStep;
use OxidShipping\Engine\Domain\WeightRateTable;
use OxidShipping\Engine\Domain\ZoneConfig;
use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Domain\ZoneDirectory;
use OxidShipping\Engine\ShippingClass;

final class TariffDocument
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private const MAX_POSTAL_ENTRIES = 100_000;

    private const MAX_DEFINITIONS = 2_048;

    private const MAX_WEIGHT_STEPS = 64;

    private const MAX_THRESHOLD_FLOORS = 16;

    private const MAX_TRANSIT_ENTRIES = 8_192;

    private const MAX_ISLAND_ZONE_IDS = 2_048;

    private const MAX_SERVED_COUNTRIES = 8;

    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(TariffConfig $config): array
    {
        $definitions = $config->zones->directory->definitions();
        usort(
            $definitions,
            static fn (ZoneDefinition $a, ZoneDefinition $b): int => $a->zoneId <=> $b->zoneId,
        );

        $transit = [];
        foreach ($config->rates->transit->entries() as $entry) {
            $transit[] = [
                'class' => $entry->class->value,
                'days' => $entry->days,
                'zoneId' => $entry->zoneId,
            ];
        }

        $postalEntries = [];
        foreach ($config->zones->directory->postalEntries() as $entry) {
            $postalEntries[] = [
                'country' => $entry->country,
                'postalCode' => $entry->postalCode,
                'zoneId' => $entry->zoneId,
            ];
        }

        $definitionRows = [];
        foreach ($definitions as $definition) {
            $definitionRows[] = [
                'forbidden' => $definition->forbidden,
                'zoneId' => $definition->zoneId,
            ];
        }

        return [
            'classification' => [
                'billableWeight' => self::floorRows($config->classification->billableWeight),
                'girth' => self::floorRows($config->classification->girth),
                'maxLength' => self::floorRows($config->classification->maxLength),
            ],
            'dimFactorCmKg' => $config->volumetricDivisor->dimFactorCmKg(),
            'orderWeightSpeditionGrams' => $config->orderWeightSpeditionThreshold->grams,
            'rates' => [
                'base' => [
                    'paket' => self::stepRows($config->rates->base->paket),
                    'spedition' => self::stepRows($config->rates->base->spedition),
                    'sperrgut' => self::stepRows($config->rates->base->sperrgut),
                ],
                'surcharges' => [
                    'indoor' => [
                        'cents' => $config->rates->surcharges->indoor->cents,
                        'priority' => $config->rates->surcharges->indoor->priority,
                    ],
                    'island' => [
                        'cents' => $config->rates->surcharges->island->cents,
                        'priority' => $config->rates->surcharges->island->priority,
                        'zoneIds' => $config->rates->surcharges->island->zoneIds,
                    ],
                ],
                'transit' => $transit,
            ],
            'zones' => [
                'directory' => [
                    'definitions' => $definitionRows,
                    'postalEntries' => $postalEntries,
                ],
                'servedCountries' => $config->zones->served->codes(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(TariffConfig $config): array
    {
        $document = self::payload($config);
        $document['version'] = $config->version;

        return $document;
    }

    public static function hash(TariffConfig $config): string
    {
        return hash('sha256', self::encode(self::payload($config)));
    }

    /**
     * @param array<mixed> $document
     */
    public static function fromArray(array $document): TariffConfig
    {
        if (array_is_list($document)) {
            throw new \InvalidArgumentException('Tariff document root must be an object.');
        }

        $root = $document;
        self::closed($root, [
            'classification',
            'dimFactorCmKg',
            'orderWeightSpeditionGrams',
            'rates',
            'version',
            'zones',
        ]);

        $classification = self::object($root['classification']);
        self::closed($classification, ['billableWeight', 'girth', 'maxLength']);

        $rates = self::object($root['rates']);
        self::closed($rates, ['base', 'surcharges', 'transit']);

        $base = self::object($rates['base']);
        self::closed($base, ['paket', 'spedition', 'sperrgut']);

        $surcharges = self::object($rates['surcharges']);
        self::closed($surcharges, ['indoor', 'island']);

        $indoor = self::object($surcharges['indoor']);
        self::closed($indoor, ['cents', 'priority']);

        $island = self::object($surcharges['island']);
        self::closed($island, ['cents', 'priority', 'zoneIds']);

        $zones = self::object($root['zones']);
        self::closed($zones, ['directory', 'servedCountries']);

        $directory = self::object($zones['directory']);
        self::closed($directory, ['definitions', 'postalEntries']);

        $servedCountries = self::stringList($zones['servedCountries'], self::MAX_SERVED_COUNTRIES, 'Served countries');
        $definitions = self::list($directory['definitions'], self::MAX_DEFINITIONS, 'Zone definitions');
        $postalEntries = self::list($directory['postalEntries'], self::MAX_POSTAL_ENTRIES, 'Postal entries');
        $transit = self::list($rates['transit'], self::MAX_TRANSIT_ENTRIES, 'Transit entries');
        $islandZoneIds = self::stringList($island['zoneIds'], self::MAX_ISLAND_ZONE_IDS, 'Island zone ids');

        $zoneDefinitions = [];
        foreach ($definitions as $row) {
            $map = self::object($row);
            self::closed($map, ['forbidden', 'zoneId']);
            $zoneDefinitions[] = new ZoneDefinition(self::string($map['zoneId']), self::bool($map['forbidden']));
        }

        $postal = [];
        foreach ($postalEntries as $row) {
            $map = self::object($row);
            self::closed($map, ['country', 'postalCode', 'zoneId']);
            $postal[] = new PostalZoneEntry(
                self::string($map['country']),
                self::string($map['postalCode']),
                self::string($map['zoneId']),
            );
        }

        $transitEntries = [];
        foreach ($transit as $row) {
            $map = self::object($row);
            self::closed($map, ['class', 'days', 'zoneId']);
            $transitEntries[] = new TransitEntry(
                self::shippingClass($map['class']),
                self::string($map['zoneId']),
                self::int($map['days']),
            );
        }

        return new TariffConfig(
            self::string($root['version']),
            VolumetricDivisor::fromDimFactorCmKg(self::int($root['dimFactorCmKg'])),
            new ZoneConfig(
                ServedCountries::fromCodes($servedCountries),
                ZoneDirectory::fromEntries($zoneDefinitions, $postal),
            ),
            new ClassificationConfig(
                self::thresholdTable($classification['girth']),
                self::thresholdTable($classification['maxLength']),
                self::thresholdTable($classification['billableWeight']),
            ),
            new OrderWeightThreshold(self::int($root['orderWeightSpeditionGrams'])),
            new TariffRates(
                new BaseRateConfig(
                    self::weightTable($base['paket']),
                    self::weightTable($base['sperrgut']),
                    self::weightTable($base['spedition']),
                ),
                new SurchargeConfig(
                    new IslandSurchargeRate(
                        self::int($island['priority']),
                        self::int($island['cents']),
                        $islandZoneIds,
                    ),
                    new IndoorSurchargeRate(self::int($indoor['priority']), self::int($indoor['cents'])),
                ),
                TransitTable::fromEntries($transitEntries),
            ),
        );
    }

    public static function fromJson(string $json): TariffConfig
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Tariff document JSON is invalid.', 0, $e);
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Tariff document root must be an object.');
        }

        return self::fromArray($decoded);
    }

    /**
     * @return list<array{above: int, class: string}>
     */
    private static function floorRows(ThresholdTable $table): array
    {
        $rows = [];
        foreach ($table->floors() as $floor) {
            $rows[] = [
                'above' => $floor->above,
                'class' => $floor->class->value,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{cents: int, upTo: int}>
     */
    private static function stepRows(WeightRateTable $table): array
    {
        $rows = [];
        foreach ($table->steps() as $step) {
            $rows[] = [
                'cents' => $step->cents,
                'upTo' => $step->upTo,
            ];
        }

        return $rows;
    }

    private static function thresholdTable(mixed $value): ThresholdTable
    {
        $floors = [];
        foreach (self::list($value, self::MAX_THRESHOLD_FLOORS, 'Threshold floors') as $row) {
            $map = self::object($row);
            self::closed($map, ['above', 'class']);
            $floors[] = new ClassFloor(self::int($map['above']), self::shippingClass($map['class']));
        }

        return ThresholdTable::fromEntries($floors);
    }

    private static function weightTable(mixed $value): WeightRateTable
    {
        $steps = [];
        foreach (self::list($value, self::MAX_WEIGHT_STEPS, 'Weight rate steps') as $row) {
            $map = self::object($row);
            self::closed($map, ['cents', 'upTo']);
            $steps[] = new WeightRateStep(self::int($map['upTo']), self::int($map['cents']));
        }

        return WeightRateTable::fromEntries($steps);
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new \InvalidArgumentException('Document value must be an object.');
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $map
     * @param list<string> $required
     */
    private static function closed(array $map, array $required): void
    {
        foreach (array_keys($map) as $key) {
            if (!is_string($key) || !in_array($key, $required, true)) {
                throw new \InvalidArgumentException(
                    'Unknown document key: ' . (is_string($key) ? $key : (string) $key),
                );
            }
        }

        foreach ($required as $key) {
            if (!array_key_exists($key, $map)) {
                throw new \InvalidArgumentException('Missing document key: ' . $key);
            }
        }
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value, int $max, string $label): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException($label . ' must be a list.');
        }
        if (count($value) > $max) {
            throw new \InvalidArgumentException($label . ' exceed the document cap.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, int $max, string $label): array
    {
        $list = self::list($value, $max, $label);
        $strings = [];
        foreach ($list as $item) {
            $strings[] = self::string($item);
        }

        return $strings;
    }

    private static function int(mixed $value): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException('Document number fields must be int.');
        }

        return $value;
    }

    private static function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Document string fields must be string.');
        }

        return $value;
    }

    private static function bool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new \InvalidArgumentException('Document boolean fields must be bool.');
        }

        return $value;
    }

    private static function shippingClass(mixed $value): ShippingClass
    {
        $backing = self::string($value);

        return match ($backing) {
            ShippingClass::Paket->value => ShippingClass::Paket,
            ShippingClass::Sperrgut->value => ShippingClass::Sperrgut,
            ShippingClass::Spedition->value => ShippingClass::Spedition,
            default => throw new \InvalidArgumentException('Shipping class is invalid.'),
        };
    }

    /**
     * @param array<mixed> $data
     */
    private static function encode(array $data): string
    {
        self::assertLeaves($data);

        return json_encode(self::sortKeys($data), self::JSON_FLAGS);
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function sortKeys(array $value): array
    {
        if (array_is_list($value)) {
            $sorted = [];
            foreach ($value as $item) {
                $sorted[] = is_array($item) ? self::sortKeys($item) : $item;
            }

            return $sorted;
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortKeys($item);
            }
        }

        return $value;
    }

    private static function assertLeaves(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertLeaves($item);
            }

            return;
        }

        if (!is_int($value) && !is_string($value) && !is_bool($value)) {
            throw new \InvalidArgumentException('Canonical document leaves must be int, string or bool.');
        }
    }
}
