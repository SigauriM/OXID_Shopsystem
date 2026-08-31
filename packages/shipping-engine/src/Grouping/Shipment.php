<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Grouping;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\ZoneId;
use OxidShipping\Engine\ShippingClass;

final readonly class Shipment
{
    /**
     * @var list<ClassifiedPiece>
     */
    public array $pieces;

    /**
     * @param list<ClassifiedPiece> $pieces non-empty
     */
    public function __construct(
        public ShippingClass $class,
        public string $zoneId,
        public bool $indoor,
        array $pieces,
    ) {
        ZoneId::assert($zoneId);

        if ($pieces === []) {
            throw new \InvalidArgumentException('Shipment must contain at least one piece.');
        }

        $seen = [];
        foreach ($pieces as $item) {
            if ($item->class !== $class) {
                throw new \InvalidArgumentException(
                    'Shipment piece class does not match the shipment class.',
                );
            }

            $key = $item->piece->lineIndex . "\0" . $item->piece->pieceIndex;
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException('Duplicate piece coordinate.');
            }
            $seen[$key] = true;
        }

        usort(
            $pieces,
            static fn (ClassifiedPiece $a, ClassifiedPiece $b): int => [$a->piece->lineIndex, $a->piece->pieceIndex]
                <=> [$b->piece->lineIndex, $b->piece->pieceIndex],
        );

        $this->pieces = $pieces;
    }
}
