<?php

/**
 * Metadata version
 */
$sMetadataVersion = '2.1';

/**
 * Module information
 */
$aModule = [
    'id' => 'oxidshipping',
    'title' => [
        'de' => 'Versandrechnung',
        'en' => 'Shipping calculation',
    ],
    'description' => [
        'de' => 'Stellt die Versand-Engine im Shop-Container bereit.',
        'en' => 'Exposes the shipping engine in the shop container.',
    ],
    'version' => '1.0.0',
    'author' => 'OXID Shopsystem',
    'extend' => [
        \OxidEsales\Eshop\Application\Model\Basket::class => \OxidShipping\Module\Extension\Basket::class,
        \OxidEsales\Eshop\Application\Model\Order::class => \OxidShipping\Module\Extension\Order::class,
        \OxidEsales\Eshop\Application\Controller\ArticleDetailsController::class => \OxidShipping\Module\Extension\ArticleDetails::class,
        \OxidEsales\Eshop\Application\Component\Widget\ArticleDetails::class => \OxidShipping\Module\Extension\Widget\ArticleDetails::class,
    ],
    'events' => [
        'onActivate' => \OxidShipping\Module\ModuleEvents::class . '::onActivate',
        'onDeactivate' => \OxidShipping\Module\ModuleEvents::class . '::onDeactivate',
    ],
    'controllers' => [
        'oxidshipping_rules' => \OxidShipping\Module\Application\Controller\Admin\TariffRulesController::class,
        'oxidshipping_zones' => \OxidShipping\Module\Application\Controller\Admin\TariffZonesController::class,
        'oxidshipping_rates' => \OxidShipping\Module\Application\Controller\Admin\TariffRatesController::class,
        'oxidshipping_surcharges' => \OxidShipping\Module\Application\Controller\Admin\TariffSurchargesController::class,
        'oxidshipping_versions' => \OxidShipping\Module\Application\Controller\Admin\TariffVersionsController::class,
        'oxidshipping_sandbox' => \OxidShipping\Module\Application\Controller\Admin\TariffSandboxController::class,
        'oxwshippingbox' => \OxidShipping\Module\Application\Component\Widget\ShippingBox::class,
    ],
];
