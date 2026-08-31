<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

use OxidShipping\Engine\ShippingClass;

final readonly class TransitTable
{
    /**
     * @param array<string, int> $daysByKey
     */
    private function __construct(
        private array $daysByKey,
    ) {
    }

    /**
     * @param list<TransitEntry> $entries
     */
    public static function fromEntries(array $entries): self
    {
        $daysByKey = [];
        foreach ($entries as $entry) {
            $key = self::key($entry->class, $entry->zoneId);
            if (isset($daysByKey[$key])) {
                throw new \InvalidArgumentException('Transit class and zone pairs must be unique.');
            }
            $daysByKey[$key] = $entry->days;
        }

        return new self($daysByKey);
    }

    /**
     * @return list<TransitEntry>
     */
    public function entries(): array
    {
        $entries = [];
        foreach ($this->daysByKey as $key => $days) {
            [$classValue, $zoneId] = explode("\0", $key, 2);
            $entries[] = new TransitEntry(ShippingClass::from($classValue), $zoneId, $days);
        }

        usort(
            $entries,
            static fn (TransitEntry $a, TransitEntry $b): int => [$a->class->rank(), $a->zoneId]
                <=> [$b->class->rank(), $b->zoneId],
        );

        return $entries;
    }

    public function has(ShippingClass $class, string $zoneId): bool
    {
        return isset($this->daysByKey[self::key($class, $zoneId)]);
    }

    public function days(ShippingClass $class, string $zoneId): int
    {
        $key = self::key($class, $zoneId);
        if (!isset($this->daysByKey[$key])) {
            throw new \InvalidArgumentException('No transit days for this class and zone.');
        }

        return $this->daysByKey[$key];
    }

    private static function key(ShippingClass $class, string $zoneId): string
    {
        return $class->value . "\0" . $zoneId;
    }
}
