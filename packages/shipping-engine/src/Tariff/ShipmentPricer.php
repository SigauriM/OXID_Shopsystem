<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tariff;

use OxidShipping\Engine\Domain\TariffRates;
use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\ShippingClass;

final readonly class ShipmentPricer
{
    public function price(Shipment $shipment, TariffRates $rates): PricedShipment
    {
        $priced = $this->priceAll([$shipment], $rates);
        if (!isset($priced[0])) {
            throw new \LogicException('Pricing a shipment must yield one priced shipment.');
        }

        return $priced[0];
    }

    /**
     * @param list<Shipment> $shipments
     * @return list<PricedShipment>
     */
    public function priceAll(array $shipments, TariffRates $rates): array
    {
        $island = $rates->surcharges->island;
        $indoor = $rates->surcharges->indoor;
        $islandZones = array_fill_keys($island->zoneIds, true);
        $transit = $rates->transit;

        $priced = [];
        foreach ($shipments as $shipment) {
            $table = $rates->base->for($shipment->class);
            $lines = [];
            $baseCents = 0;
            foreach ($shipment->pieces as $item) {
                $cents = $table->rate($item->piece->billableGrams);
                $lines[] = new PriceLine(
                    PriceRuleId::Base,
                    PriceStage::Base,
                    $cents,
                    sprintf(
                        'Base rate for piece (%d, %d), billable %d g.',
                        $item->piece->lineIndex,
                        $item->piece->pieceIndex,
                        $item->piece->billableGrams,
                    ),
                    $item->piece->lineIndex,
                    $item->piece->pieceIndex,
                );
                $baseCents += $cents;
            }

            /** @var list<array{0: int, 1: PriceLine}> $fired */
            $fired = [];
            if (isset($islandZones[$shipment->zoneId])) {
                $fired[] = [
                    $island->priority,
                    new PriceLine(
                        PriceRuleId::Island,
                        PriceStage::Surcharge,
                        $island->cents,
                        'Island or foreign-zone surcharge (one stop).',
                        null,
                        null,
                    ),
                ];
            }
            if ($shipment->class === ShippingClass::Spedition && $shipment->indoor === true) {
                $fired[] = [
                    $indoor->priority,
                    new PriceLine(
                        PriceRuleId::Indoor,
                        PriceStage::Surcharge,
                        $indoor->cents,
                        'Indoor delivery surcharge (one stop).',
                        null,
                        null,
                    ),
                ];
            }

            usort(
                $fired,
                static fn (array $a, array $b): int => $a[0] <=> $b[0],
            );

            $totalCents = $baseCents;
            foreach ($fired as $entry) {
                $line = $entry[1];
                $lines[] = $line;
                $totalCents += $line->deltaCents;
            }

            $priced[] = new PricedShipment(
                $shipment,
                $baseCents,
                $totalCents,
                $transit->days($shipment->class, $shipment->zoneId),
                $lines,
            );
        }

        return $priced;
    }
}
