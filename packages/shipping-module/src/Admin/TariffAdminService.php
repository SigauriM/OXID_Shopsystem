<?php

declare(strict_types=1);

namespace OxidShipping\Module\Admin;

use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Module\Tariff\TariffRepositoryInterface;
use OxidShipping\Module\Tariff\TariffVersion;

final class TariffAdminService
{
    public function __construct(
        private TariffRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array{document: array<string, mixed>, baseHash: string}|null
     */
    public function load(): ?array
    {
        $config = $this->repository->findActive();
        if ($config === null) {
            return null;
        }

        return [
            'document' => TariffDocument::document($config),
            'baseHash' => TariffDocument::hash($config),
        ];
    }

    /**
     * @return list<TariffVersion>
     */
    public function listVersions(int $limit = 50): array
    {
        if ($limit < 1) {
            $limit = 50;
        }

        return array_slice($this->repository->listVersions(), 0, $limit);
    }

    /**
     * @param array<string, mixed> $form
     */
    public function saveClassification(array $form, ?string $authorId): SaveOutcome
    {
        try {
            $active = $this->repository->findActive();
        } catch (\InvalidArgumentException $exception) {
            return SaveOutcome::rejected($exception->getMessage(), []);
        }
        if ($active === null) {
            return SaveOutcome::rejected('No active shipping tariff.', []);
        }

        $parsed = (new DocumentDraft(TariffDocument::document($active)))->applyClassification($form);
        $document = $parsed['document'];
        if ($parsed['fieldErrors'] !== []) {
            return SaveOutcome::invalidFields($parsed['fieldErrors'], $document);
        }

        $baseHash = is_string($form['baseHash'] ?? null) ? $form['baseHash'] : '';
        $currentHash = TariffDocument::hash($active);
        if ($baseHash !== $currentHash) {
            return SaveOutcome::conflict($document);
        }

        return $this->commit($document, $active, $authorId, $document);
    }

    /**
     * @param array<string, mixed> $form
     */
    public function saveZones(array $form, ?string $authorId): SaveOutcome
    {
        try {
            $active = $this->repository->findActive();
        } catch (\InvalidArgumentException $exception) {
            return SaveOutcome::rejected($exception->getMessage(), []);
        }
        if ($active === null) {
            return SaveOutcome::rejected('No active shipping tariff.', []);
        }

        $parsed = (new DocumentDraft(TariffDocument::document($active)))->applyZones($form);
        $document = $parsed['document'];
        if ($parsed['fieldErrors'] !== []) {
            return SaveOutcome::invalidFields($parsed['fieldErrors'], $document);
        }

        $baseHash = is_string($form['baseHash'] ?? null) ? $form['baseHash'] : '';
        $currentHash = TariffDocument::hash($active);
        if ($baseHash !== $currentHash) {
            return SaveOutcome::conflict($document);
        }

        $blocked = self::zoneInUseMessage($document);
        if ($blocked !== null) {
            return SaveOutcome::blocked($blocked, $document);
        }

        return $this->commit(
            DocumentDraft::kernelZonesDocument($document),
            $active,
            $authorId,
            $document,
        );
    }

    /**
     * @param array<string, mixed> $form
     */
    public function saveSurcharges(array $form, ?string $authorId): SaveOutcome
    {
        try {
            $active = $this->repository->findActive();
        } catch (\InvalidArgumentException $exception) {
            return SaveOutcome::rejected($exception->getMessage(), []);
        }
        if ($active === null) {
            return SaveOutcome::rejected('No active shipping tariff.', []);
        }

        $parsed = (new DocumentDraft(TariffDocument::document($active)))->applySurcharges($form);
        $document = $parsed['document'];
        if ($parsed['fieldErrors'] !== []) {
            return SaveOutcome::invalidFields($parsed['fieldErrors'], $document);
        }

        $baseHash = is_string($form['baseHash'] ?? null) ? $form['baseHash'] : '';
        $currentHash = TariffDocument::hash($active);
        if ($baseHash !== $currentHash) {
            return SaveOutcome::conflict($document);
        }

        return $this->commit($document, $active, $authorId, $document);
    }

    public function activateVersion(string $id): void
    {
        if (preg_match('/^[a-zA-Z0-9]{1,32}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Shipping tariff version was not found for this shop.');
        }

        $this->repository->activate($id);
    }

    /**
     * @param array<string, mixed> $kernelDocument
     * @param array<string, mixed> $formDocument
     */
    private function commit(
        array $kernelDocument,
        TariffConfig $active,
        ?string $authorId,
        array $formDocument,
    ): SaveOutcome {
        try {
            $config = TariffDocument::fromArray($kernelDocument);
        } catch (\InvalidArgumentException $exception) {
            return SaveOutcome::rejected($exception->getMessage(), $formDocument);
        }

        $currentHash = TariffDocument::hash($active);
        $newHash = TariffDocument::hash($config);
        if ($newHash === $currentHash && $config->version === $active->version) {
            return SaveOutcome::unchanged();
        }
        if ($newHash === $currentHash) {
            $this->repository->renameActive($config);

            return SaveOutcome::renamed();
        }

        $this->repository->saveVersion($config, $authorId);
        $suggested = $config->version === $active->version
            ? self::suggestVersion($active->version)
            : null;

        return SaveOutcome::saved($suggested);
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function zoneInUseMessage(array $document): ?string
    {
        $defined = [];
        $definitions = $document['zones']['directory']['definitions'] ?? [];
        if (is_array($definitions)) {
            foreach ($definitions as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $zoneId = $row['zoneId'] ?? '';
                if (is_string($zoneId) && $zoneId !== '') {
                    $defined[$zoneId] = true;
                }
            }
        }

        $islandIds = $document['rates']['surcharges']['island']['zoneIds'] ?? [];
        if (is_array($islandIds)) {
            foreach ($islandIds as $zoneId) {
                if (is_string($zoneId) && $zoneId !== '' && !isset($defined[$zoneId])) {
                    return 'Die Zone „' . $zoneId . '“ wird in der Inselzuschlag-Liste verwendet.';
                }
            }
        }

        $counts = [];
        $postalEntries = $document['zones']['directory']['postalEntries'] ?? [];
        if (is_array($postalEntries)) {
            foreach ($postalEntries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $zoneId = $entry['zoneId'] ?? '';
                if (!is_string($zoneId) || $zoneId === '' || isset($defined[$zoneId])) {
                    continue;
                }
                $counts[$zoneId] = ($counts[$zoneId] ?? 0) + 1;
            }
        }
        foreach ($counts as $zoneId => $count) {
            return 'Die Zone „' . $zoneId . '“ wird in ' . $count . ' PLZ-Einträgen verwendet.';
        }

        return null;
    }

    private static function suggestVersion(string $current): string
    {
        if (preg_match('/^(.*)\.(\d+)$/', $current, $matches) === 1) {
            return $matches[1] . '.' . ((int) $matches[2] + 1);
        }

        return $current . '.2';
    }
}
