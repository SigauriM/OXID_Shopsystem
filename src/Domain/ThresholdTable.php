<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

use OxidShipping\Engine\ShippingClass;

final readonly class ThresholdTable
{
    /**
     * @param list<ClassFloor> $floors
     */
    private function __construct(
        private array $floors,
    ) {
    }

    /**
     * @param list<ClassFloor> $entries
     */
    public static function fromEntries(array $entries): self
    {
        if ($entries === []) {
            throw new \InvalidArgumentException('Threshold table must not be empty.');
        }

        $byAbove = [];
        foreach ($entries as $floor) {
            if (isset($byAbove[$floor->above])) {
                throw new \InvalidArgumentException('Threshold above values must be unique.');
            }
            $byAbove[$floor->above] = $floor;
        }

        ksort($byAbove, SORT_NUMERIC);
        $previousRank = null;
        foreach ($byAbove as $floor) {
            $rank = $floor->class->rank();
            if ($previousRank !== null && $rank <= $previousRank) {
                throw new \InvalidArgumentException(
                    'Threshold class rank must strictly increase with above.',
                );
            }
            $previousRank = $rank;
        }

        return new self($entries);
    }

    public function floor(int $measurement): ?ShippingClass
    {
        $result = null;
        foreach ($this->floors as $floor) {
            if ($measurement > $floor->above) {
                $result = $result === null ? $floor->class : $result->atLeast($floor->class);
            }
        }

        return $result;
    }
}
