<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class IslandSurchargeRate
{
    /**
     * @var list<string>
     */
    public array $zoneIds;

    /**
     * @param list<string> $zoneIds unique; empty is valid
     */
    public function __construct(
        public int $priority,
        public int $cents,
        array $zoneIds,
    ) {
        if ($cents < 1) {
            throw new \InvalidArgumentException('Island surcharge cents must be 1 or greater.');
        }

        $seen = [];
        foreach ($zoneIds as $zoneId) {
            ZoneId::assert($zoneId);
            if (isset($seen[$zoneId])) {
                throw new \InvalidArgumentException('Island surcharge zone ids must be unique.');
            }
            $seen[$zoneId] = true;
        }

        $sorted = array_keys($seen);
        sort($sorted, SORT_STRING);

        $this->zoneIds = $sorted;
    }
}
