<?php

declare(strict_types=1);

namespace OxidShipping\Module;

use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Container\ContainerBuilderFactory;
use OxidShipping\Engine\QuoteEngine;
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

        // Shop default sLogLevel is error; info is discarded from oxideshop.log.
        $logger->error('oxidshipping module activated; QuoteEngine is in the container.');
    }

    public static function onDeactivate(): void
    {
        $logger = ContainerFacade::get(LoggerInterface::class);
        $logger->error('oxidshipping module deactivated.');
    }
}
