<?php

declare(strict_types=1);

namespace OxidShipping\Module;

use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Container\ContainerBuilderFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Module\Tariff\TariffRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class ModuleEvents
{
    public static function onActivate(): void
    {
        $logger = ContainerFacade::get(LoggerInterface::class);

        $container = (new ContainerBuilderFactory())->create()->getContainer();
        $container->compile();
        $engine = $container->get(QuoteEngine::class);
        if (!$engine instanceof QuoteEngine) {
            throw new RuntimeException('QuoteEngine is not in the module container.');
        }

        self::seedActiveTariff($logger);

        // Shop default sLogLevel is error; info is discarded from oxideshop.log.
        $logger->error('oxidshipping module activated; QuoteEngine is in the container.');
    }

    public static function onDeactivate(): void
    {
        $logger = ContainerFacade::get(LoggerInterface::class);
        $logger->error('oxidshipping module deactivated.');
    }

    private static function seedActiveTariff(LoggerInterface $logger): void
    {
        $repository = new TariffRepository(
            ContainerFacade::get(QueryBuilderFactoryInterface::class),
            ContainerFacade::get(ConnectionFactoryInterface::class),
            ContainerFacade::get(ContextInterface::class),
        );

        try {
            if ($repository->findActive() !== null) {
                return;
            }
        } catch (\InvalidArgumentException) {
            return;
        } catch (
            \Doctrine\DBAL\DBALException
            | \PDOException
            | \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
        ) {
            $logger->error(
                'Shipping tariff table is missing; run vendor/bin/oe-eshop-db_migrate migrations:migrate oxidshipping.',
            );

            return;
        }

        $path = dirname(__DIR__) . '/config/shop-tariff.json';
        if (!is_readable($path)) {
            throw new RuntimeException('Shop tariff seed file cannot be read.');
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Shop tariff seed file cannot be read.');
        }

        try {
            $repository->saveVersion(TariffDocument::fromJson($json), null);
        } catch (
            \Doctrine\DBAL\DBALException
            | \PDOException
            | \OxidEsales\Eshop\Core\Exception\DatabaseErrorException
        ) {
            $logger->error(
                'Shipping tariff table is missing; run vendor/bin/oe-eshop-db_migrate migrations:migrate oxidshipping.',
            );
        }
    }
}
