<?php

declare(strict_types=1);

namespace OxidShipping\Module\Admin;

/**
 * Mutable tariff document array. Classification edits stay here; the kernel validates.
 */
final class DocumentDraft
{
    /**
     * @param array<string, mixed> $document
     */
    public function __construct(
        private array $document,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->document;
    }

    /**
     * @param array<string, mixed> $form
     * @return array{document: array<string, mixed>, fieldErrors: array<string, string>}
     */
    public function applyClassification(array $form): array
    {
        $errors = [];
        $document = $this->document;

        $document['version'] = self::parseVersion($form['version'] ?? '', $errors);
        $dimFactor = self::parseInt($form['dimFactorCmKg'] ?? '', 'dimFactorCmKg', $errors);
        $document['dimFactorCmKg'] = $dimFactor ?? $form['dimFactorCmKg'] ?? '';
        $orderWeight = self::parseInt(
            $form['orderWeightSpeditionGrams'] ?? '',
            'orderWeightSpeditionGrams',
            $errors,
        );
        $document['orderWeightSpeditionGrams'] = $orderWeight ?? $form['orderWeightSpeditionGrams'] ?? '';

        $classification = is_array($form['classification'] ?? null) ? $form['classification'] : [];
        $document['classification'] = [
            'girth' => self::parseFloors($classification['girth'] ?? [], 'girth', $errors),
            'maxLength' => self::parseFloors($classification['maxLength'] ?? [], 'maxLength', $errors),
            'billableWeight' => self::parseFloors(
                $classification['billableWeight'] ?? [],
                'billableWeight',
                $errors,
            ),
        ];

        return [
            'document' => $document,
            'fieldErrors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array{document: array<string, mixed>, fieldErrors: array<string, string>}
     */
    public function applyZones(array $form): array
    {
        $errors = [];
        $document = $this->document;

        $document['version'] = self::parseVersion($form['version'] ?? '', $errors);
        $definitions = self::parseDefinitions($form['definitions'] ?? [], $errors);
        $document['zones'] = [
            'servedCountries' => self::parseServedCountries($form['servedCountries'] ?? [], $errors),
            'directory' => [
                'definitions' => $definitions,
                'postalEntries' => self::parsePostalEntries($form['postalEntries'] ?? [], $errors),
            ],
        ];
        $document['rates']['transit'] = self::transitFromDefinitions($definitions);

        return [
            'document' => $document,
            'fieldErrors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array{document: array<string, mixed>, fieldErrors: array<string, string>}
     */
    public function applySurcharges(array $form): array
    {
        $errors = [];
        $document = $this->document;
        $document['version'] = self::parseVersion($form['version'] ?? '', $errors);

        $island = is_array($document['rates']['surcharges']['island'] ?? null)
            ? $document['rates']['surcharges']['island']
            : ['cents' => '', 'priority' => '', 'zoneIds' => []];
        $indoor = is_array($document['rates']['surcharges']['indoor'] ?? null)
            ? $document['rates']['surcharges']['indoor']
            : ['cents' => '', 'priority' => ''];

        $islandForm = is_array($form['island'] ?? null) ? $form['island'] : [];
        $indoorForm = is_array($form['indoor'] ?? null) ? $form['indoor'] : [];

        $islandCents = self::parseInt($islandForm['cents'] ?? '', 'island.cents', $errors);
        $island['cents'] = $islandCents ?? ($islandForm['cents'] ?? '');
        $indoorCents = self::parseInt($indoorForm['cents'] ?? '', 'indoor.cents', $errors);
        $indoor['cents'] = $indoorCents ?? ($indoorForm['cents'] ?? '');

        $order = self::parseSurchargeOrder($form['surchargeOrder'] ?? '');
        if ($order !== null) {
            foreach ($order as $index => $name) {
                $priority = ($index + 1) * 10;
                if ($name === 'island') {
                    $island['priority'] = $priority;
                } else {
                    $indoor['priority'] = $priority;
                }
            }
        } else {
            $islandPriority = self::parseInt($islandForm['priority'] ?? '', 'island.priority', $errors);
            $island['priority'] = $islandPriority ?? ($islandForm['priority'] ?? '');
            $indoorPriority = self::parseInt($indoorForm['priority'] ?? '', 'indoor.priority', $errors);
            $indoor['priority'] = $indoorPriority ?? ($indoorForm['priority'] ?? '');
        }

        $document['rates']['surcharges']['island'] = $island;
        $document['rates']['surcharges']['indoor'] = $indoor;

        return [
            'document' => $document,
            'fieldErrors' => $errors,
        ];
    }

    /**
     * Surcharge rows in priority order for the admin list.
     *
     * @param array<string, mixed> $document
     * @return list<array{id: string, cents: mixed, priority: mixed, zoneIds: list<mixed>}>
     */
    public static function surchargesByPriority(array $document): array
    {
        $surcharges = is_array($document['rates']['surcharges'] ?? null)
            ? $document['rates']['surcharges']
            : [];
        $rows = [];
        foreach (['island', 'indoor'] as $id) {
            $row = $surcharges[$id] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $zoneIds = $row['zoneIds'] ?? [];
            $rows[] = [
                'id' => $id,
                'cents' => $row['cents'] ?? '',
                'priority' => $row['priority'] ?? '',
                'zoneIds' => is_array($zoneIds) ? array_values($zoneIds) : [],
            ];
        }
        usort(
            $rows,
            static fn (array $left, array $right): int => (int) $left['priority'] <=> (int) $right['priority'],
        );

        return $rows;
    }

    /**
     * Kernel definitions have no per-row days; those live in rates.transit.
     *
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public static function kernelZonesDocument(array $document): array
    {
        $definitions = $document['zones']['directory']['definitions'] ?? [];
        if (!is_array($definitions)) {
            return $document;
        }
        $stripped = [];
        foreach ($definitions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $stripped[] = [
                'forbidden' => $row['forbidden'] ?? false,
                'zoneId' => $row['zoneId'] ?? '',
            ];
        }
        $document['zones']['directory']['definitions'] = $stripped;

        return $document;
    }

    /**
     * Copy transit days onto definition rows for the zone form.
     *
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public static function attachTransitDays(array $document): array
    {
        $byZone = [];
        $transit = $document['rates']['transit'] ?? [];
        if (is_array($transit)) {
            foreach ($transit as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $zoneId = is_string($row['zoneId'] ?? null) ? $row['zoneId'] : '';
                $class = is_string($row['class'] ?? null) ? $row['class'] : '';
                if ($zoneId === '' || $class === '') {
                    continue;
                }
                $byZone[$zoneId][$class] = $row['days'] ?? '';
            }
        }
        $definitions = $document['zones']['directory']['definitions'] ?? [];
        if (!is_array($definitions)) {
            return $document;
        }
        foreach ($definitions as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (isset($row['days']) && is_array($row['days'])) {
                continue;
            }
            $zoneId = is_string($row['zoneId'] ?? null) ? $row['zoneId'] : '';
            $definitions[$index]['days'] = [
                'paket' => $byZone[$zoneId]['paket'] ?? '',
                'sperrgut' => $byZone[$zoneId]['sperrgut'] ?? '',
                'spedition' => $byZone[$zoneId]['spedition'] ?? '',
            ];
        }
        $document['zones']['directory']['definitions'] = $definitions;

        return $document;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function parseVersion(mixed $raw, array &$errors): string
    {
        if (!is_string($raw) && !is_int($raw)) {
            $errors['version'] = 'empty';

            return '';
        }
        $value = trim((string) $raw);
        if ($value === '') {
            $errors['version'] = 'empty';

            return $value;
        }
        if (strlen($value) > 64) {
            $errors['version'] = 'too_long';
        }

        return $value;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function parseInt(mixed $raw, string $field, array &$errors): ?int
    {
        if (is_int($raw)) {
            if ($raw < 0) {
                $errors[$field] = 'negative';

                return null;
            }

            return $raw;
        }
        if (!is_string($raw)) {
            $errors[$field] = 'not_integer';

            return null;
        }
        $value = trim($raw);
        if ($value === '') {
            $errors[$field] = 'empty';

            return null;
        }
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            $errors[$field] = 'not_integer';

            return null;
        }
        if (str_starts_with($value, '-')) {
            $errors[$field] = 'negative';

            return null;
        }

        return (int) $value;
    }

    /**
     * @param mixed $rows
     * @param array<string, string> $errors
     * @return list<array{above: mixed, class: mixed}>
     */
    private static function parseFloors(mixed $rows, string $table, array &$errors): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $floors = [];
        $index = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $index++;
                continue;
            }
            $aboveRaw = $row['above'] ?? '';
            $classRaw = $row['class'] ?? '';
            $above = self::parseInt($aboveRaw, 'classification.' . $table . '.' . $index . '.above', $errors);
            $class = self::parseClass($classRaw, 'classification.' . $table . '.' . $index . '.class', $errors);
            $floors[] = [
                'above' => $above ?? $aboveRaw,
                'class' => $class,
            ];
            $index++;
        }

        return $floors;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function parseClass(mixed $raw, string $field, array &$errors): mixed
    {
        if (!is_string($raw)) {
            $errors[$field] = 'invalid_class';

            return $raw;
        }
        $value = trim($raw);
        if ($value === '') {
            $errors[$field] = 'empty';

            return $value;
        }
        if ($value !== 'sperrgut' && $value !== 'spedition') {
            $errors[$field] = 'invalid_class';
        }

        return $value;
    }

    /**
     * @param mixed $rows
     * @param array<string, string> $errors
     * @return list<string>
     */
    private static function parseServedCountries(mixed $rows, array &$errors): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $codes = [];
        foreach ($rows as $index => $raw) {
            if (!is_string($raw) && !is_int($raw)) {
                $errors['servedCountries.' . $index] = 'invalid_country';
                continue;
            }
            $code = strtoupper(trim((string) $raw));
            if ($code !== 'DE' && $code !== 'AT') {
                $errors['servedCountries.' . $index] = 'invalid_country';
                continue;
            }
            if (!in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @param mixed $rows
     * @param array<string, string> $errors
     * @return list<array<string, mixed>>
     */
    private static function parseDefinitions(mixed $rows, array &$errors): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $definitions = [];
        $index = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $index++;
                continue;
            }
            $zoneId = self::parseString($row['zoneId'] ?? '', 'definitions.' . $index . '.zoneId', $errors, 32);
            $forbidden = self::parseBool($row['forbidden'] ?? '0');
            $daysRaw = is_array($row['days'] ?? null) ? $row['days'] : [];
            $days = [];
            foreach (['paket', 'sperrgut', 'spedition'] as $class) {
                $field = 'definitions.' . $index . '.days.' . $class;
                $rawDay = $daysRaw[$class] ?? '';
                if ($forbidden) {
                    $days[$class] = $rawDay;
                    continue;
                }
                if ($rawDay === '') {
                    $days[$class] = '';
                    continue;
                }
                $parsed = self::parseInt($rawDay, $field, $errors);
                $days[$class] = $parsed ?? $rawDay;
            }
            $definitions[] = [
                'forbidden' => $forbidden,
                'zoneId' => $zoneId,
                'days' => $days,
            ];
            $index++;
        }

        return $definitions;
    }

    /**
     * @param mixed $rows
     * @param array<string, string> $errors
     * @return list<array{country: string, postalCode: string, zoneId: string}>
     */
    private static function parsePostalEntries(mixed $rows, array &$errors): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $entries = [];
        $index = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $index++;
                continue;
            }
            $country = self::parseString($row['country'] ?? '', 'postalEntries.' . $index . '.country', $errors, 2);
            if ($country !== '' && $country !== 'DE' && $country !== 'AT') {
                $errors['postalEntries.' . $index . '.country'] = 'invalid_country';
            }
            $postalCode = self::parsePostalCode(
                $row['postalCode'] ?? '',
                'postalEntries.' . $index . '.postalCode',
                $errors,
            );
            $zoneId = self::parseString($row['zoneId'] ?? '', 'postalEntries.' . $index . '.zoneId', $errors, 32);
            $entries[] = [
                'country' => $country,
                'postalCode' => $postalCode,
                'zoneId' => $zoneId,
            ];
            $index++;
        }

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @return list<array{class: string, days: mixed, zoneId: mixed}>
     */
    private static function transitFromDefinitions(array $definitions): array
    {
        $transit = [];
        foreach ($definitions as $row) {
            if (($row['forbidden'] ?? false) === true) {
                continue;
            }
            $zoneId = $row['zoneId'] ?? '';
            $days = is_array($row['days'] ?? null) ? $row['days'] : [];
            foreach (['paket', 'sperrgut', 'spedition'] as $class) {
                $value = $days[$class] ?? null;
                if (!is_int($value)) {
                    continue;
                }
                $transit[] = [
                    'class' => $class,
                    'days' => $value,
                    'zoneId' => $zoneId,
                ];
            }
        }

        return $transit;
    }

    /**
     * @return list<string>|null
     */
    private static function parseSurchargeOrder(mixed $raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }
        $parts = [];
        foreach (explode(',', $raw) as $part) {
            $name = trim($part);
            if ($name === '') {
                continue;
            }
            $parts[] = $name;
        }
        if (count($parts) !== 2) {
            return null;
        }
        $allowed = ['island' => true, 'indoor' => true];
        $seen = [];
        foreach ($parts as $name) {
            if (!isset($allowed[$name]) || isset($seen[$name])) {
                return null;
            }
            $seen[$name] = true;
        }

        return $parts;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function parseString(mixed $raw, string $field, array &$errors, int $maxLength): string
    {
        if (!is_string($raw) && !is_int($raw)) {
            $errors[$field] = 'empty';

            return '';
        }
        $value = trim((string) $raw);
        if ($value === '') {
            $errors[$field] = 'empty';

            return $value;
        }
        if (strlen($value) > $maxLength) {
            $errors[$field] = 'too_long';
        }

        return $value;
    }

    /**
     * PLZ stays a string. Never cast through int (01067 must not become 1067).
     *
     * @param array<string, string> $errors
     */
    private static function parsePostalCode(mixed $raw, string $field, array &$errors): string
    {
        if (is_int($raw)) {
            $errors[$field] = 'not_string';

            return (string) $raw;
        }
        if (!is_string($raw)) {
            $errors[$field] = 'empty';

            return '';
        }
        $value = trim($raw);
        if ($value === '') {
            $errors[$field] = 'empty';

            return $value;
        }
        if (strlen($value) > 10) {
            $errors[$field] = 'too_long';
        }

        return $value;
    }

    private static function parseBool(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw)) {
            return $raw === 1;
        }
        if (!is_string($raw)) {
            return false;
        }

        return $raw === '1' || strtolower($raw) === 'true' || $raw === 'on';
    }
}
