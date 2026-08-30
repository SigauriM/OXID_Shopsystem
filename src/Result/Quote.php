<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Measurement\MeasuredPiece;

final readonly class Quote implements QuoteResult
{
    /**
     * @param list<MeasuredPiece> $pieces
     * @param list<PieceRejection> $rejections
     * @param list<ClassifiedPiece> $classified
     */
    public static function fromPipeline(
        array $pieces,
        KnownZone|Rejected $destination,
        array $rejections,
        array $classified,
        InputSnapshot $snapshot,
        string $configVersion,
    ): self {
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

        $sortedPieces = $pieces;
        usort(
            $sortedPieces,
            static fn (MeasuredPiece $a, MeasuredPiece $b): int => [$a->lineIndex, $a->pieceIndex] <=> [$b->lineIndex, $b->pieceIndex],
        );

        $sortedRejections = $rejections;
        usort(
            $sortedRejections,
            static fn (PieceRejection $a, PieceRejection $b): int => [$a->lineIndex, $a->pieceIndex] <=> [$b->lineIndex, $b->pieceIndex],
        );

        $sortedClassified = $classified;
        usort(
            $sortedClassified,
            static fn (ClassifiedPiece $a, ClassifiedPiece $b): int => [$a->piece->lineIndex, $a->piece->pieceIndex]
                <=> [$b->piece->lineIndex, $b->piece->pieceIndex],
        );

        return new self(
            $sortedPieces,
            $destination,
            $sortedRejections,
            $sortedClassified,
            $snapshot,
            $configVersion,
        );
    }

    /**
     * @param list<MeasuredPiece> $pieces
     * @param list<PieceRejection> $rejections
     * @param list<ClassifiedPiece> $classified
     */
    private function __construct(
        public array $pieces,
        /** Request-level address decision, not a piece outcome. */
        public KnownZone|Rejected $destination,
        public array $rejections,
        public array $classified,
        public InputSnapshot $snapshot,
        public string $configVersion,
    ) {
    }
}
