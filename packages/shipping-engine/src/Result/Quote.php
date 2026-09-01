<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\Tariff\PricedShipment;
use OxidShipping\Engine\Tariff\PriceLine;

final readonly class Quote implements QuoteResult
{
    /**
     * @param list<MeasuredPiece> $pieces
     * @param list<PieceRejection> $rejections
     * @param list<ClassifiedPiece> $classified
     * @param list<PricedShipment> $shipments
     */
    public static function fromPipeline(
        array $pieces,
        KnownZone|Rejected $destination,
        array $rejections,
        array $classified,
        array $shipments,
        InputSnapshot $snapshot,
        string $configVersion,
        string $configHash,
    ): self {
        if (preg_match('/^[a-f0-9]{64}$/', $configHash) !== 1) {
            throw new \InvalidArgumentException(
                'Config hash must be 64 lowercase hexadecimal characters.',
            );
        }

        $pieceByCoordinate = [];
        foreach ($pieces as $piece) {
            $key = $piece->lineIndex . "\0" . $piece->pieceIndex;
            if (isset($pieceByCoordinate[$key])) {
                throw new \InvalidArgumentException('Duplicate piece coordinate.');
            }
            $pieceByCoordinate[$key] = $piece;
        }

        $rejectionKeys = [];
        foreach ($rejections as $rejection) {
            $key = $rejection->lineIndex . "\0" . $rejection->pieceIndex;
            if (isset($rejectionKeys[$key])) {
                throw new \InvalidArgumentException('Duplicate rejection coordinate.');
            }
            $rejectionKeys[$key] = true;

            $piece = $pieceByCoordinate[$key] ?? null;
            if ($piece === null) {
                throw new \InvalidArgumentException('Rejection does not refer to a piece.');
            }
            if ($piece->lineId !== $rejection->lineId) {
                throw new \InvalidArgumentException('Rejection lineId does not match the piece.');
            }
        }

        if ($destination instanceof Rejected) {
            foreach (array_keys($pieceByCoordinate) as $key) {
                if (!isset($rejectionKeys[$key])) {
                    throw new \InvalidArgumentException(
                        'Rejected destination requires a rejection for every piece.',
                    );
                }
            }
            if ($classified !== []) {
                throw new \InvalidArgumentException(
                    'Rejected destination must not include classified pieces.',
                );
            }
        }

        if ($destination instanceof KnownZone) {
            $classifiedKeys = [];
            foreach ($classified as $item) {
                $key = $item->piece->lineIndex . "\0" . $item->piece->pieceIndex;
                if (isset($classifiedKeys[$key])) {
                    throw new \InvalidArgumentException('Duplicate classified coordinate.');
                }
                $classifiedKeys[$key] = true;

                $piece = $pieceByCoordinate[$key] ?? null;
                if ($piece === null) {
                    throw new \InvalidArgumentException(
                        'Classified piece does not refer to a pipeline piece.',
                    );
                }
                if ($piece->lineId !== $item->piece->lineId) {
                    throw new \InvalidArgumentException(
                        'Classified piece lineId does not match the piece.',
                    );
                }
            }

            foreach (array_keys($pieceByCoordinate) as $key) {
                if (!isset($classifiedKeys[$key])) {
                    throw new \InvalidArgumentException(
                        'Known destination requires a classified piece for every piece.',
                    );
                }
            }
        }

        if ($destination instanceof Rejected && $shipments !== []) {
            throw new \InvalidArgumentException(
                'Rejected destination must not include shipments.',
            );
        }

        if ($destination instanceof KnownZone) {
            $classifiedByCoordinate = [];
            foreach ($classified as $item) {
                $classifiedByCoordinate[$item->piece->lineIndex . "\0" . $item->piece->pieceIndex] = $item;
            }

            $assigned = [];
            $shipmentKeys = [];
            foreach ($shipments as $priced) {
                $shipment = $priced->shipment;
                $shipmentKey = $shipment->class->value
                    . "\0"
                    . $shipment->zoneId
                    . "\0"
                    . ($shipment->indoor ? '1' : '0');
                if (isset($shipmentKeys[$shipmentKey])) {
                    throw new \InvalidArgumentException('Duplicate shipment key.');
                }
                $shipmentKeys[$shipmentKey] = true;

                foreach ($shipment->pieces as $item) {
                    $key = $item->piece->lineIndex . "\0" . $item->piece->pieceIndex;
                    $classifiedItem = $classifiedByCoordinate[$key] ?? null;
                    if ($classifiedItem === null) {
                        throw new \InvalidArgumentException(
                            'Shipment piece does not refer to a classified piece.',
                        );
                    }
                    if ($classifiedItem->piece->lineId !== $item->piece->lineId) {
                        throw new \InvalidArgumentException(
                            'Shipment piece lineId does not match the classified piece.',
                        );
                    }
                    if ($classifiedItem->class !== $item->class) {
                        throw new \InvalidArgumentException(
                            'Shipment piece class does not match the classified piece.',
                        );
                    }
                    if ($classifiedItem->piece->billableGrams !== $item->piece->billableGrams) {
                        throw new \InvalidArgumentException(
                            'Shipment piece billableGrams does not match the classified piece.',
                        );
                    }
                    if (isset($assigned[$key])) {
                        throw new \InvalidArgumentException(
                            'Classified piece belongs to more than one shipment.',
                        );
                    }
                    $assigned[$key] = true;
                }
            }

            foreach (array_keys($classifiedByCoordinate) as $key) {
                if (!isset($assigned[$key])) {
                    throw new \InvalidArgumentException(
                        'Known destination requires every classified piece in a shipment.',
                    );
                }
            }
        }

        $sortedRejections = $rejections;
        usort(
            $sortedRejections,
            static function (PieceRejection $a, PieceRejection $b): int {
                return [$a->lineIndex, $a->pieceIndex] <=> [$b->lineIndex, $b->pieceIndex];
            },
        );

        $sortedClassified = $classified;
        usort(
            $sortedClassified,
            static fn (ClassifiedPiece $a, ClassifiedPiece $b): int => [$a->piece->lineIndex, $a->piece->pieceIndex]
                <=> [$b->piece->lineIndex, $b->piece->pieceIndex],
        );

        $sortedShipments = $shipments;
        usort(
            $sortedShipments,
            static fn (PricedShipment $a, PricedShipment $b): int => [
                $a->shipment->class->rank(),
                $a->shipment->zoneId,
                $a->shipment->indoor,
            ] <=> [
                $b->shipment->class->rank(),
                $b->shipment->zoneId,
                $b->shipment->indoor,
            ],
        );

        $totalCents = 0;
        $trace = [];
        foreach ($sortedShipments as $priced) {
            $lineSum = 0;
            foreach ($priced->lines as $line) {
                $lineSum += $line->deltaCents;
                $trace[] = $line;
            }
            if ($lineSum !== $priced->totalCents) {
                throw new \InvalidArgumentException('Priced shipment lines must sum to totalCents.');
            }
            $totalCents += $priced->totalCents;
        }

        return new self(
            $destination,
            $sortedRejections,
            $sortedClassified,
            $sortedShipments,
            $totalCents,
            $trace,
            $snapshot,
            $configVersion,
            $configHash,
        );
    }

    /**
     * @param list<PieceRejection> $rejections
     * @param list<ClassifiedPiece> $classified
     * @param list<PricedShipment> $shipments
     * @param list<PriceLine> $trace
     */
    private function __construct(
        /** Request-level address decision, not a piece outcome. */
        public KnownZone|Rejected $destination,
        public array $rejections,
        /** Class after the order-weight rule, not after piece classification alone. */
        public array $classified,
        /** Priced stop; inner key is still (class, zoneId, indoor). */
        public array $shipments,
        /** Sum of priced shipments; rejections add 0. */
        public int $totalCents,
        public array $trace,
        public InputSnapshot $snapshot,
        public string $configVersion,
        public string $configHash,
    ) {
    }
}
