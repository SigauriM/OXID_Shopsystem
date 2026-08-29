<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Measurement\MeasuredPiece;

final readonly class Quote implements QuoteResult
{
    /**
     * @param list<MeasuredPiece> $pieces
     * @param list<PieceRejection> $rejections
     */
    public static function fromPipeline(
        array $pieces,
        KnownZone|Rejected $destination,
        array $rejections,
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

        return new self($sortedPieces, $destination, $sortedRejections, $snapshot, $configVersion);
    }

    /**
     * @param list<MeasuredPiece> $pieces
     * @param list<PieceRejection> $rejections
     */
    private function __construct(
        public array $pieces,
        /** Request-level address decision, not a piece outcome. */
        public KnownZone|Rejected $destination,
        public array $rejections,
        public InputSnapshot $snapshot,
        public string $configVersion,
    ) {
    }
}
