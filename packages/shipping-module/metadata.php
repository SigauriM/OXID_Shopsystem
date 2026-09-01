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
    ],
    'events' => [
        'onActivate' => \OxidShipping\Module\ModuleEvents::class . '::onActivate',
        'onDeactivate' => \OxidShipping\Module\ModuleEvents::class . '::onDeactivate',
    ],
];
