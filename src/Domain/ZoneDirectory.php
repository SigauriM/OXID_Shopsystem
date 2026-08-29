<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class ZoneDirectory
{
    /**
     * @param array<string, string> $entriesByKey
     * @param array<string, ZoneDefinition> $definitionsById
     */
    private function __construct(
        private array $entriesByKey,
        private array $definitionsById,
    ) {
    }

    /**
     * @param list<ZoneDefinition> $definitions
     * @param list<PostalZoneEntry> $entries
     */
    public static function fromEntries(array $definitions, array $entries): self
    {
        $definitionsById = [];
        foreach ($definitions as $definition) {
            if (isset($definitionsById[$definition->zoneId])) {
                throw new \InvalidArgumentException('Duplicate zone definition.');
            }
            $definitionsById[$definition->zoneId] = $definition;
        }

        $entriesByKey = [];
        foreach ($entries as $entry) {
            $key = $entry->country . "\0" . $entry->postalCode;
            if (isset($entriesByKey[$key])) {
                throw new \InvalidArgumentException('Duplicate postal zone entry.');
            }
            if (!isset($definitionsById[$entry->zoneId])) {
                throw new \InvalidArgumentException('Postal entry refers to an undefined zone.');
            }
            $entriesByKey[$key] = $entry->zoneId;
        }

        return new self($entriesByKey, $definitionsById);
    }

    public function lookup(string $postalCode, string $country): ZoneLookup
    {
        $zoneId = $this->entriesByKey[$country . "\0" . $postalCode] ?? null;
        if ($zoneId === null) {
            return new UnknownZone();
        }

        return new KnownZone($zoneId);
    }

    /**
     * Guaranteed present by fromEntries.
     */
    public function definition(string $zoneId): ZoneDefinition
    {
        if (!isset($this->definitionsById[$zoneId])) {
            throw new \LogicException('Zone definition is missing.');
        }

        return $this->definitionsById[$zoneId];
    }

    /**
     * @return list<string>
     */
    public function countries(): array
    {
        $countries = [];
        foreach (array_keys($this->entriesByKey) as $key) {
            $country = explode("\0", $key, 2)[0];
            $countries[$country] = $country;
        }

        $list = array_values($countries);
        sort($list, SORT_STRING);

        return $list;
    }
}
