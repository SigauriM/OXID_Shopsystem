<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Module\Admin\DocumentDraft;
use OxidShipping\Module\Tests\Support\FixtureTariff;
use PHPUnit\Framework\TestCase;

final class DocumentDraftTest extends TestCase
{
    public function testChangingAFloorProducesANewDocument(): void
    {
        $config = FixtureTariff::config();
        $draft = new DocumentDraft(TariffDocument::document($config));
        $form = self::formFrom($config);
        $form['classification']['girth'][1]['above'] = '7000';

        $parsed = $draft->applyClassification($form);

        $this->assertSame([], $parsed['fieldErrors']);
        $this->assertSame(7000, $parsed['document']['classification']['girth'][1]['above']);
        $this->assertNotSame(
            TariffDocument::hash($config),
            TariffDocument::hash(TariffDocument::fromArray($parsed['document'])),
        );
    }

    public function testSpacedIntegerIsAFieldErrorNotThree(): void
    {
        $config = FixtureTariff::config();
        $draft = new DocumentDraft(TariffDocument::document($config));
        $form = self::formFrom($config);
        $form['classification']['girth'][0]['above'] = '3 000';

        $parsed = $draft->applyClassification($form);

        $this->assertSame('not_integer', $parsed['fieldErrors']['classification.girth.0.above']);
        $this->assertSame('3 000', $parsed['document']['classification']['girth'][0]['above']);
        $this->assertNotSame(3, $parsed['document']['classification']['girth'][0]['above']);
    }

    public function testPostalCode01067StaysAString(): void
    {
        $config = FixtureTariff::config();
        $draft = new DocumentDraft(TariffDocument::document($config));
        $form = self::zonesFormFrom($config);

        $parsed = $draft->applyZones($form);
        $kernel = DocumentDraft::kernelZonesDocument($parsed['document']);

        $this->assertSame([], $parsed['fieldErrors']);
        $codes = [];
        foreach ($kernel['zones']['directory']['postalEntries'] as $row) {
            $codes[] = $row['postalCode'];
            if ($row['postalCode'] === '01067') {
                $this->assertIsString($row['postalCode']);
            }
        }
        $this->assertContains('01067', $codes);

        $saved = TariffDocument::document(TariffDocument::fromArray($kernel));
        foreach ($saved['zones']['directory']['postalEntries'] as $row) {
            if ($row['postalCode'] === '01067') {
                $this->assertIsString($row['postalCode']);
                $this->assertSame('01067', $row['postalCode']);
            }
        }
    }

    public function testIntegerPostalCodeIsAFieldError(): void
    {
        $config = FixtureTariff::config();
        $draft = new DocumentDraft(TariffDocument::document($config));
        $form = self::zonesFormFrom($config);
        $form['postalEntries'][1]['postalCode'] = 1067;

        $parsed = $draft->applyZones($form);

        $this->assertSame('not_string', $parsed['fieldErrors']['postalEntries.1.postalCode']);
    }

    public function testSurchargeOrderAssignsPrioritiesAndIgnoresPostedNumbers(): void
    {
        $config = FixtureTariff::config();
        $draft = new DocumentDraft(TariffDocument::document($config));
        $form = [
            'version' => 'shop-2026-test',
            'surchargeOrder' => 'indoor,island',
            'island' => ['cents' => '800', 'priority' => '10'],
            'indoor' => ['cents' => '1500', 'priority' => '10'],
        ];

        $parsed = $draft->applySurcharges($form);

        $this->assertSame([], $parsed['fieldErrors']);
        $this->assertSame(10, $parsed['document']['rates']['surcharges']['indoor']['priority']);
        $this->assertSame(20, $parsed['document']['rates']['surcharges']['island']['priority']);
        $this->assertSame(['de-island'], $parsed['document']['rates']['surcharges']['island']['zoneIds']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function formFrom(\OxidShipping\Engine\Input\TariffConfig $config): array
    {
        $document = TariffDocument::document($config);

        return [
            'baseHash' => TariffDocument::hash($config),
            'version' => $document['version'],
            'dimFactorCmKg' => (string) $document['dimFactorCmKg'],
            'orderWeightSpeditionGrams' => (string) $document['orderWeightSpeditionGrams'],
            'classification' => [
                'girth' => self::stringifyFloors($document['classification']['girth']),
                'maxLength' => self::stringifyFloors($document['classification']['maxLength']),
                'billableWeight' => self::stringifyFloors($document['classification']['billableWeight']),
            ],
        ];
    }

    /**
     * @param list<array{above: int, class: string}> $floors
     * @return list<array{above: string, class: string}>
     */
    private static function stringifyFloors(array $floors): array
    {
        $rows = [];
        foreach ($floors as $floor) {
            $rows[] = [
                'above' => (string) $floor['above'],
                'class' => $floor['class'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function zonesFormFrom(\OxidShipping\Engine\Input\TariffConfig $config): array
    {
        $document = DocumentDraft::attachTransitDays(TariffDocument::document($config));
        $definitions = [];
        foreach ($document['zones']['directory']['definitions'] as $row) {
            $days = [];
            foreach (['paket', 'sperrgut', 'spedition'] as $class) {
                $value = $row['days'][$class] ?? '';
                $days[$class] = $value === '' ? '' : (string) $value;
            }
            $definitions[] = [
                'zoneId' => $row['zoneId'],
                'forbidden' => !empty($row['forbidden']) ? '1' : '0',
                'days' => $days,
            ];
        }
        $postal = [];
        foreach ($document['zones']['directory']['postalEntries'] as $row) {
            $postal[] = [
                'country' => $row['country'],
                'postalCode' => $row['postalCode'],
                'zoneId' => $row['zoneId'],
            ];
        }

        return [
            'baseHash' => TariffDocument::hash($config),
            'version' => $document['version'],
            'servedCountries' => $document['zones']['servedCountries'],
            'definitions' => $definitions,
            'postalEntries' => $postal,
        ];
    }
}
