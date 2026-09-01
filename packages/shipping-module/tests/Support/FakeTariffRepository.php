<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests\Support;

use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Module\Tariff\TariffRepositoryInterface;
use OxidShipping\Module\Tariff\TariffVersion;

final class FakeTariffRepository implements TariffRepositoryInterface
{
    /**
     * @var list<array{
     *     id: string,
     *     config: ?TariffConfig,
     *     version: string,
     *     hash: string,
     *     active: bool,
     *     authorId: ?string,
     *     timestamp: string,
     *     payload: string,
     *     brokenPayload: ?string
     * }>
     */
    private array $rows = [];

    private int $nextId = 1;

    public function __construct(?TariffConfig $active = null)
    {
        if ($active !== null) {
            $this->rows[] = $this->makeRow($active, true, null, null);
        }
    }

    public static function withBrokenActivePayload(): self
    {
        $repository = new self();
        $repository->rows[] = $repository->makeRow(null, true, '{', 'broken');

        return $repository;
    }

    public function findActive(): ?TariffConfig
    {
        foreach ($this->rows as $row) {
            if (!$row['active']) {
                continue;
            }
            if ($row['brokenPayload'] !== null) {
                return TariffDocument::fromJson($row['brokenPayload']);
            }

            return $row['config'];
        }

        return null;
    }

    public function listVersions(): array
    {
        $versions = [];
        foreach (array_reverse($this->rows) as $row) {
            $versions[] = new TariffVersion(
                $row['id'],
                $row['version'],
                $row['hash'],
                $row['active'],
                $row['authorId'],
                $row['timestamp'],
                $row['payload'],
            );
        }

        return $versions;
    }

    public function saveVersion(TariffConfig $config, ?string $authorId): void
    {
        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['active'] = false;
        }
        $this->rows[] = $this->makeRow($config, true, null, $authorId);
    }

    public function renameActive(TariffConfig $config): void
    {
        foreach ($this->rows as $index => $row) {
            if (!$row['active']) {
                continue;
            }
            $this->rows[$index]['config'] = $config;
            $this->rows[$index]['version'] = $config->version;
            $this->rows[$index]['hash'] = TariffDocument::hash($config);
            $this->rows[$index]['payload'] = json_encode(
                TariffDocument::document($config),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

            return;
        }

        throw new \RuntimeException('Shipping tariff version was not found for this shop.');
    }

    public function activate(string $id): void
    {
        $found = false;
        foreach ($this->rows as $row) {
            if ($row['id'] === $id) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new \RuntimeException('Shipping tariff version was not found for this shop.');
        }

        foreach ($this->rows as $index => $row) {
            $this->rows[$index]['active'] = $row['id'] === $id;
        }
    }

    /**
     * @return array{
     *     id: string,
     *     config: ?TariffConfig,
     *     version: string,
     *     hash: string,
     *     active: bool,
     *     authorId: ?string,
     *     timestamp: string,
     *     payload: string,
     *     brokenPayload: ?string
     * }
     */
    private function makeRow(
        ?TariffConfig $config,
        bool $active,
        ?string $brokenPayload,
        ?string $authorId,
    ): array {
        $id = 'fake' . $this->nextId;
        $this->nextId++;

        return [
            'id' => $id,
            'config' => $config,
            'version' => $config?->version ?? 'broken',
            'hash' => $config !== null ? TariffDocument::hash($config) : str_repeat('0', 64),
            'active' => $active,
            'authorId' => $authorId,
            'timestamp' => '2026-09-01 00:00:00',
            'payload' => $config !== null
                ? json_encode(
                    TariffDocument::document($config),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                )
                : (string) $brokenPayload,
            'brokenPayload' => $brokenPayload,
        ];
    }
}
