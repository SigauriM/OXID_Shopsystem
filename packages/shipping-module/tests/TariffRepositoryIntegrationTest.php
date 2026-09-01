<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tests;

use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Database\Id;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Module\Tariff\TariffRepository;
use OxidShipping\Module\Tests\Support\FixtureTariff;
use PHPUnit\Framework\TestCase;

final class TariffRepositoryIntegrationTest extends TestCase
{
    private const TEST_VERSION = 'step1test';

    private ?TariffRepository $repository = null;

    private ?QueryBuilderFactoryInterface $queryBuilderFactory = null;

    private int $shopId = 0;

    private ?string $originalActiveId = null;

    private bool $live = false;

    protected function setUp(): void
    {
        if (!defined('OX_BASE_PATH')) {
            $bootstrap = dirname(__DIR__, 3) . '/source/bootstrap.php';
            if (!is_file($bootstrap)) {
                $this->markTestSkipped('Shop bootstrap is not available.');
            }
            require $bootstrap;
            restore_exception_handler();
        }

        try {
            $queryBuilderFactory = ContainerFacade::get(QueryBuilderFactoryInterface::class);
            $repository = new TariffRepository(
                $queryBuilderFactory,
                ContainerFacade::get(ConnectionFactoryInterface::class),
                ContainerFacade::get(ContextInterface::class),
            );
            $shopId = ContainerFacade::get(ContextInterface::class)->getCurrentShopId();
            $repository->findActive();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Shop database is not available: ' . $exception->getMessage());
        }

        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->repository = $repository;
        $this->shopId = $shopId;
        $this->live = true;

        foreach ($this->repository->listVersions() as $version) {
            if ($version->active) {
                $this->originalActiveId = $version->id;
                break;
            }
        }

        $this->deleteTestRows();
    }

    protected function tearDown(): void
    {
        if (!$this->live || $this->repository === null || $this->queryBuilderFactory === null) {
            return;
        }

        $this->deleteTestRows();
        if ($this->originalActiveId !== null) {
            $this->repository->activate($this->originalActiveId);
        }
    }

    public function testSaveVersionArchivesPreviousAndLeavesOneActive(): void
    {
        $config = $this->testConfig();
        $this->repository->saveVersion($config, null);

        $active = [];
        foreach ($this->repository->listVersions() as $version) {
            if ($version->active) {
                $active[] = $version;
            }
        }

        $this->assertCount(1, $active);
        $this->assertSame(self::TEST_VERSION, $active[0]->version);
        $this->assertNull($active[0]->authorId);
        $this->assertSame(TariffDocument::hash($config), $active[0]->hash);

        $loaded = $this->repository->findActive();
        $this->assertNotNull($loaded);
        $this->assertSame(TariffDocument::hash($config), TariffDocument::hash($loaded));
    }

    public function testActivateSwitchesActiveRowWithoutInserting(): void
    {
        $first = $this->testConfig();
        $this->repository->saveVersion($first, null);
        $firstId = $this->activeId();

        $payload = TariffDocument::document($first);
        $payload['version'] = 'step1test.2';
        $second = TariffDocument::fromArray($payload);
        $this->repository->saveVersion($second, null);
        $secondId = $this->activeId();

        $this->assertNotSame($firstId, $secondId);

        $before = count($this->repository->listVersions());
        $this->repository->activate($firstId);
        $after = count($this->repository->listVersions());

        $this->assertSame($before, $after);
        $this->assertSame($firstId, $this->activeId());
        $this->assertSame(self::TEST_VERSION, $this->repository->findActive()?->version);
    }

    public function testUniqueIndexRejectsSecondActiveRow(): void
    {
        $this->repository->saveVersion($this->testConfig(), null);

        try {
            $this->queryBuilderFactory->create()
                ->insert('oxidshipping_tariff')
                ->values([
                    'OXID' => ':id',
                    'OXSHOPID' => ':shopId',
                    'OXVERSION' => ':version',
                    'OXPAYLOAD' => ':payload',
                    'OXHASH' => ':hash',
                    'OXACTIVEFLAG' => ':active',
                ])
                ->setParameter('id', (string) Id::generate())
                ->setParameter('shopId', $this->shopId)
                ->setParameter('version', self::TEST_VERSION)
                ->setParameter('payload', '{}')
                ->setParameter('hash', str_repeat('a', 64))
                ->setParameter('active', 1)
                ->execute();
            $this->fail('Expected a unique constraint violation for a second active row.');
        } catch (
            \Doctrine\DBAL\DBALException
            | \PDOException
            | \OxidEsales\Eshop\Core\Exception\DatabaseErrorException $exception
        ) {
            $message = $exception->getMessage();
            $this->assertTrue(
                str_contains($message, 'Duplicate')
                || str_contains($message, '1062')
                || str_contains($message, 'UNIQUE'),
                $message,
            );
        }
    }

    private function testConfig(): TariffConfig
    {
        $payload = TariffDocument::document(FixtureTariff::config());
        $payload['version'] = self::TEST_VERSION;

        return TariffDocument::fromArray($payload);
    }

    private function activeId(): string
    {
        foreach ($this->repository->listVersions() as $version) {
            if ($version->active) {
                return $version->id;
            }
        }

        self::fail('Expected an active tariff version.');
    }

    private function deleteTestRows(): void
    {
        $this->queryBuilderFactory->create()
            ->delete('oxidshipping_tariff')
            ->where('OXSHOPID = :shopId')
            ->andWhere('OXVERSION LIKE :version')
            ->setParameter('shopId', $this->shopId)
            ->setParameter('version', self::TEST_VERSION . '%')
            ->execute();
    }
}
