<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tariff;

use OxidShipping\Engine\Input\TariffConfig;

interface TariffRepositoryInterface
{
    public function findActive(): ?TariffConfig;

    /**
     * @return list<TariffVersion>
     */
    public function listVersions(): array;

    public function saveVersion(TariffConfig $config, ?string $authorId): void;

    public function renameActive(TariffConfig $config): void;

    public function activate(string $id): void;
}
