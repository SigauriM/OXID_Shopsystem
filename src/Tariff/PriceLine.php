<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tariff;

final readonly class PriceLine
{
    public function __construct(
        public PriceRuleId $ruleId,
        public PriceStage $stage,
        public int $deltaCents,
        public string $explanation,
        public ?int $lineIndex,
        public ?int $pieceIndex,
    ) {
        if ($deltaCents < 1) {
            throw new \InvalidArgumentException('Price line deltaCents must be 1 or greater.');
        }
        if ($explanation === '') {
            throw new \InvalidArgumentException('Price line explanation must not be empty.');
        }

        $indicesSet = $lineIndex !== null && $pieceIndex !== null;
        $indicesNull = $lineIndex === null && $pieceIndex === null;
        if (!$indicesSet && !$indicesNull) {
            throw new \InvalidArgumentException('Price line indices must both be set or both be null.');
        }

        if ($ruleId === PriceRuleId::Base) {
            if ($stage !== PriceStage::Base) {
                throw new \InvalidArgumentException('Base price line stage must be base.');
            }
            if (!$indicesSet) {
                throw new \InvalidArgumentException('Base price line must carry coordinates.');
            }
            if ($lineIndex < 0 || $pieceIndex < 0) {
                throw new \InvalidArgumentException('Price line indices must be 0 or greater.');
            }

            return;
        }

        if ($stage !== PriceStage::Surcharge) {
            throw new \InvalidArgumentException('Surcharge price line stage must be surcharge.');
        }
        if (!$indicesNull) {
            throw new \InvalidArgumentException('Surcharge price line must not carry coordinates.');
        }
    }
}
