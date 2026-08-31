<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests\Support;

use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\Tariff\PricedShipment;
use OxidShipping\Engine\Tariff\ShipmentPricer;

final class TestPricing
{
    /**
     * @param list<Shipment> $shipments
     * @return list<PricedShipment>
     */
    public static function priceAll(array $shipments): array
    {
        return (new ShipmentPricer())->priceAll($shipments, TestConfig::rates());
    }
}
