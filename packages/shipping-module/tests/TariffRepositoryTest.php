<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Module\Tests\Support\FakeTariffRepository;
use OxidShipping\Module\Tests\Support\FixtureTariff;
use PHPUnit\Framework\TestCase;

final class TariffRepositoryTest extends TestCase
{
    public function testSaveVersionArchivesPreviousActiveRow(): void
    {
        $first = FixtureTariff::config();
        $repository = new FakeTariffRepository($first);

        $payload = TariffDocument::document($first);
        $payload['version'] = 'step1test.2';
        $second = TariffDocument::fromArray($payload);

        $repository->saveVersion($second, null);

        $versions = $repository->listVersions();
        $this->assertCount(2, $versions);

        $active = [];
        foreach ($versions as $version) {
            if ($version->active) {
                $active[] = $version->version;
            }
        }

        $this->assertSame(['step1test.2'], $active);
        $this->assertSame($second, $repository->findActive());
    }
}
