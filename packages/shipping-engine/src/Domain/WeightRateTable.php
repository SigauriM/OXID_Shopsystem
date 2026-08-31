<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class WeightRateTable
{
    /**
     * @param list<WeightRateStep> $steps sorted by upTo ascending
     */
    private function __construct(
        private array $steps,
    ) {
    }

    /**
     * @param list<WeightRateStep> $entries
     */
    public static function fromEntries(array $entries): self
    {
        if ($entries === []) {
            throw new \InvalidArgumentException('Weight rate table must not be empty.');
        }

        $byUpTo = [];
        foreach ($entries as $step) {
            if (isset($byUpTo[$step->upTo])) {
                throw new \InvalidArgumentException('Weight rate upTo values must be unique.');
            }
            $byUpTo[$step->upTo] = $step;
        }

        ksort($byUpTo, SORT_NUMERIC);

        return new self(array_values($byUpTo));
    }

    /**
     * @return list<WeightRateStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function rate(int $billableGrams): int
    {
        if ($billableGrams < 1) {
            throw new \InvalidArgumentException('Billable grams must be 1 or greater.');
        }

        foreach ($this->steps as $step) {
            if ($billableGrams <= $step->upTo) {
                return $step->cents;
            }
        }

        throw new \InvalidArgumentException('No weight rate covers the billable grams.');
    }
}
