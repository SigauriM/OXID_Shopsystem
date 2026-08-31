<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tariff;

use OxidShipping\Engine\Grouping\Shipment;

final readonly class PricedShipment
{
    /**
     * @param list<PriceLine> $lines non-empty; sum(deltaCents) === totalCents
     */
    public function __construct(
        public Shipment $shipment,
        public int $baseCents,
        public int $totalCents,
        public int $transitDays,
        public array $lines,
    ) {
        if ($baseCents < 1) {
            throw new \InvalidArgumentException('Priced shipment baseCents must be 1 or greater.');
        }
        if ($totalCents < $baseCents) {
            throw new \InvalidArgumentException('Priced shipment totalCents must be at least baseCents.');
        }
        if ($transitDays < 1) {
            throw new \InvalidArgumentException('Priced shipment transitDays must be 1 or greater.');
        }
        if ($lines === []) {
            throw new \InvalidArgumentException('Priced shipment must contain at least one price line.');
        }

        $pieces = $shipment->pieces;
        if (count($lines) < count($pieces)) {
            throw new \InvalidArgumentException('Priced shipment must have one base line per piece.');
        }

        $baseSum = 0;
        foreach ($pieces as $index => $item) {
            $line = $lines[$index];
            if ($line->ruleId !== PriceRuleId::Base || $line->stage !== PriceStage::Base) {
                throw new \InvalidArgumentException('Priced shipment must have one base line per piece.');
            }
            if (
                $line->lineIndex !== $item->piece->lineIndex
                || $line->pieceIndex !== $item->piece->pieceIndex
            ) {
                throw new \InvalidArgumentException(
                    'Base price line coordinates must match the piece.',
                );
            }
            $baseSum += $line->deltaCents;
        }
        if ($baseSum !== $baseCents) {
            throw new \InvalidArgumentException('Priced shipment base lines must sum to baseCents.');
        }

        $sum = $baseSum;
        $seenSurcharge = [];
        for ($index = count($pieces); $index < count($lines); $index++) {
            $line = $lines[$index];
            if (
                $line->ruleId !== PriceRuleId::Island
                && $line->ruleId !== PriceRuleId::Indoor
            ) {
                throw new \InvalidArgumentException('Surcharge lines must be island or indoor.');
            }
            if ($line->stage !== PriceStage::Surcharge) {
                throw new \InvalidArgumentException('Surcharge lines must use the surcharge stage.');
            }
            if ($line->lineIndex !== null || $line->pieceIndex !== null) {
                throw new \InvalidArgumentException('Surcharge price line must not carry coordinates.');
            }
            if (isset($seenSurcharge[$line->ruleId->value])) {
                throw new \InvalidArgumentException('Each surcharge ruleId may appear at most once.');
            }
            $seenSurcharge[$line->ruleId->value] = true;
            $sum += $line->deltaCents;
        }

        if ($sum !== $totalCents) {
            throw new \InvalidArgumentException('Priced shipment lines must sum to totalCents.');
        }
    }
}
