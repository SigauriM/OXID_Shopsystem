<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class Rejected implements PieceOutcome
{
    public function __construct(
        public RejectReason $reason,
    ) {
    }
}
