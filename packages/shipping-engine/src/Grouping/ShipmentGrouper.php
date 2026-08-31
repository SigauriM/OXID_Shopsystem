<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Grouping;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\ShippingClass;

final readonly class ShipmentGrouper
{
    /**
     * @param list<GroupablePiece> $pieces
     * @return list<Shipment>
     */
    public function group(array $pieces): array
    {
        $seen = [];
        /** @var array<string, list<ClassifiedPiece>> $buckets */
        $buckets = [];
        /** @var array<string, array{class: ShippingClass, zoneId: string, indoor: bool}> $meta */
        $meta = [];

        foreach ($pieces as $item) {
            $coord = $item->piece->piece->lineIndex . "\0" . $item->piece->piece->pieceIndex;
            if (isset($seen[$coord])) {
                throw new \InvalidArgumentException('Duplicate piece coordinate.');
            }
            $seen[$coord] = true;

            $bucketKey = $item->piece->class->value . "\0" . $item->zoneId . "\0" . ($item->indoor ? '1' : '0');
            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [];
                $meta[$bucketKey] = [
                    'class' => $item->piece->class,
                    'zoneId' => $item->zoneId,
                    'indoor' => $item->indoor,
                ];
            }
            $buckets[$bucketKey][] = $item->piece;
        }

        $shipments = [];
        foreach ($buckets as $bucketKey => $bucketPieces) {
            $shipments[] = new Shipment(
                $meta[$bucketKey]['class'],
                $meta[$bucketKey]['zoneId'],
                $meta[$bucketKey]['indoor'],
                $bucketPieces,
            );
        }

        usort(
            $shipments,
            static fn (Shipment $a, Shipment $b): int => [$a->class->rank(), $a->zoneId, $a->indoor]
                <=> [$b->class->rank(), $b->zoneId, $b->indoor],
        );

        return $shipments;
    }
}
