<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Module\Admin\DocumentDraft;
use OxidShipping\Module\Admin\SaveOutcome;
use OxidShipping\Module\Admin\TariffAdminService;
use OxidShipping\Module\Tests\Support\FakeTariffRepository;
use OxidShipping\Module\Tests\Support\FixtureTariff;
use PHPUnit\Framework\TestCase;

final class TariffAdminServiceTest extends TestCase
{
    public function testEditingAFloorSavesANewVersion(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::formFrom($config);
        $form['classification']['girth'][1]['above'] = '7000';

        $outcome = $service->saveClassification($form, 'admin1');

        $this->assertSame(SaveOutcome::SAVED, $outcome->kind);
        $this->assertCount(2, $repository->listVersions());
        $this->assertSame(7000, $repository->findActive()?->classification->girth->floors()[1]->above);
        $this->assertSame('shop-2026-test.2', $outcome->suggestedVersion);
    }

    public function testRankThatDoesNotIncreaseIsRejectedByTheKernel(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::formFrom($config);
        $form['classification']['girth'][1]['class'] = 'sperrgut';

        $outcome = $service->saveClassification($form, 'admin1');

        $this->assertSame(SaveOutcome::REJECTED, $outcome->kind);
        $this->assertSame(
            'Threshold class rank must strictly increase with above.',
            $outcome->kernelMessage,
        );
        $this->assertCount(1, $repository->listVersions());
        $this->assertSame($config, $repository->findActive());
    }

    public function testSameDocumentAndLabelIsUnchanged(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);

        $outcome = $service->saveClassification(self::formFrom($config), 'admin1');

        $this->assertSame(SaveOutcome::UNCHANGED, $outcome->kind);
        $this->assertCount(1, $repository->listVersions());
    }

    public function testSamePayloadDifferentLabelRenamesInPlace(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::formFrom($config);
        $form['version'] = 'shop-2026-test.2';

        $outcome = $service->saveClassification($form, 'admin1');

        $this->assertSame(SaveOutcome::RENAMED, $outcome->kind);
        $this->assertCount(1, $repository->listVersions());
        $this->assertSame('shop-2026-test.2', $repository->findActive()?->version);
        $this->assertSame(TariffDocument::hash($config), $repository->listVersions()[0]->hash);
    }

    public function testStaleBaseHashIsConflict(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::formFrom($config);
        $form['baseHash'] = str_repeat('a', 64);
        $form['classification']['girth'][1]['above'] = '7000';

        $outcome = $service->saveClassification($form, 'admin1');

        $this->assertSame(SaveOutcome::CONFLICT, $outcome->kind);
        $this->assertCount(1, $repository->listVersions());
        $this->assertSame($config, $repository->findActive());
    }

    public function testSpacedIntegerIsAFieldErrorAndDoesNotSaveThree(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::formFrom($config);
        $form['classification']['girth'][0]['above'] = '3 000';

        $outcome = $service->saveClassification($form, 'admin1');

        $this->assertSame(SaveOutcome::INVALID_FIELDS, $outcome->kind);
        $this->assertSame('not_integer', $outcome->fieldErrors['classification.girth.0.above']);
        $this->assertSame('3 000', $outcome->formDocument['classification']['girth'][0]['above']);
        $this->assertCount(1, $repository->listVersions());
        $this->assertSame(3000, $repository->findActive()?->classification->girth->floors()[0]->above);
    }

    public function testActivateArchivesThePreviousActiveRow(): void
    {
        $first = FixtureTariff::config();
        $repository = new FakeTariffRepository($first);
        $payload = TariffDocument::document($first);
        $payload['version'] = 'shop-2026-test.2';
        $payload['classification']['girth'][1]['above'] = 7000;
        $repository->saveVersion(TariffDocument::fromArray($payload), 'admin1');
        $firstId = null;
        foreach ($repository->listVersions() as $version) {
            if ($version->version === 'shop-2026-test') {
                $firstId = $version->id;
            }
        }
        $this->assertNotNull($firstId);

        $service = new TariffAdminService($repository);
        $service->activateVersion($firstId);

        $active = [];
        foreach ($repository->listVersions() as $version) {
            if ($version->active) {
                $active[] = $version->id;
            }
        }
        $this->assertSame([$firstId], $active);
        $this->assertSame('shop-2026-test', $repository->findActive()?->version);
        $this->assertCount(2, $repository->listVersions());
    }

    public function testPostalCode01067StaysAStringInThePayload(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::zonesFormFrom($config);
        $form['postalEntries'][] = [
            'country' => 'DE',
            'postalCode' => '99999',
            'zoneId' => 'de-01',
        ];

        $outcome = $service->saveZones($form, 'admin1');

        $this->assertSame(SaveOutcome::SAVED, $outcome->kind);
        $saved = TariffDocument::document($repository->findActive());
        $codes = [];
        foreach ($saved['zones']['directory']['postalEntries'] as $row) {
            $this->assertIsString($row['postalCode']);
            $codes[] = $row['postalCode'];
        }
        $this->assertContains('01067', $codes);
        $this->assertContains('99999', $codes);
    }

    public function testDeletingAnIslandZoneIsBlockedWithTheExactText(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::withoutZone(self::zonesFormFrom($config), 'de-island');

        $outcome = $service->saveZones($form, 'admin1');

        $this->assertSame(SaveOutcome::BLOCKED, $outcome->kind);
        $this->assertSame(
            'Die Zone „de-island“ wird in der Inselzuschlag-Liste verwendet.',
            $outcome->kernelMessage,
        );
        $this->assertCount(1, $repository->listVersions());
        $this->assertSame($config, $repository->findActive());
    }

    public function testDeletingAZoneStillUsedByPostalEntriesIsBlockedWithTheExactText(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::withoutZone(self::zonesFormFrom($config), 'de-01');

        $outcome = $service->saveZones($form, 'admin1');

        $this->assertSame(SaveOutcome::BLOCKED, $outcome->kind);
        $this->assertSame(
            'Die Zone „de-01“ wird in 1 PLZ-Einträgen verwendet.',
            $outcome->kernelMessage,
        );
        $this->assertCount(1, $repository->listVersions());
    }

    public function testFourDigitGermanPostalCodeIsRejectedByTheKernel(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::zonesFormFrom($config);
        $form['postalEntries'][1]['postalCode'] = '1067';

        $outcome = $service->saveZones($form, 'admin1');

        $this->assertSame(SaveOutcome::REJECTED, $outcome->kind);
        $this->assertSame(
            'Postal code does not match the config form for the country.',
            $outcome->kernelMessage,
        );
        $this->assertCount(1, $repository->listVersions());
    }

    public function testNewLiveZoneWithoutTransitDaysIsRejectedByTheKernel(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::zonesFormFrom($config);
        $form['definitions'][] = [
            'zoneId' => 'de-new',
            'forbidden' => '0',
        ];

        $outcome = $service->saveZones($form, 'admin1');

        $this->assertSame(SaveOutcome::REJECTED, $outcome->kind);
        $this->assertSame(
            'Transit days are required for every live zone and class.',
            $outcome->kernelMessage,
        );
        $this->assertCount(1, $repository->listVersions());
    }

    public function testSurchargeOrderRecomputesPrioritiesAndSavesANewVersion(): void
    {
        $config = FixtureTariff::config();
        $beforeHash = TariffDocument::hash($config);
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::surchargesFormFrom($config);
        $form['surchargeOrder'] = 'indoor,island';
        $form['island']['priority'] = '10';
        $form['indoor']['priority'] = '10';

        $outcome = $service->saveSurcharges($form, 'admin1');

        $this->assertSame(SaveOutcome::SAVED, $outcome->kind);
        $this->assertCount(2, $repository->listVersions());
        $active = $repository->findActive();
        $this->assertSame(10, $active?->rates->surcharges->indoor->priority);
        $this->assertSame(20, $active?->rates->surcharges->island->priority);
        $this->assertNotSame($beforeHash, TariffDocument::hash($active));
    }

    public function testDuplicatePostedPrioritiesAreRejected(): void
    {
        $config = FixtureTariff::config();
        $repository = new FakeTariffRepository($config);
        $service = new TariffAdminService($repository);
        $form = self::surchargesFormFrom($config);
        $form['surchargeOrder'] = 'island,island';
        $form['island']['priority'] = '10';
        $form['indoor']['priority'] = '10';

        $outcome = $service->saveSurcharges($form, 'admin1');

        $this->assertSame(SaveOutcome::REJECTED, $outcome->kind);
        $this->assertSame('Duplicate surcharge priority.', $outcome->kernelMessage);
        $this->assertCount(1, $repository->listVersions());
        $this->assertSame($config, $repository->findActive());
    }

    /**
     * @return array<string, mixed>
     */
    private static function formFrom(TariffConfig $config): array
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
    private static function zonesFormFrom(TariffConfig $config): array
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

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private static function withoutZone(array $form, string $zoneId): array
    {
        $definitions = [];
        foreach ($form['definitions'] as $row) {
            if ($row['zoneId'] === $zoneId) {
                continue;
            }
            $definitions[] = $row;
        }
        $form['definitions'] = $definitions;

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    private static function surchargesFormFrom(TariffConfig $config): array
    {
        $document = TariffDocument::document($config);

        return [
            'baseHash' => TariffDocument::hash($config),
            'version' => $document['version'],
            'surchargeOrder' => 'island,indoor',
            'island' => [
                'cents' => (string) $document['rates']['surcharges']['island']['cents'],
                'priority' => (string) $document['rates']['surcharges']['island']['priority'],
            ],
            'indoor' => [
                'cents' => (string) $document['rates']['surcharges']['indoor']['cents'],
                'priority' => (string) $document['rates']['surcharges']['indoor']['priority'],
            ],
        ];
    }
}
