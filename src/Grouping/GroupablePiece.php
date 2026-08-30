<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Grouping;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\ZoneId;

final readonly class GroupablePiece
{
    public function __construct(
        public ClassifiedPiece $piece,
        public string $zoneId,
        public bool $indoor,
    ) {
        ZoneId::assert($zoneId);
    }
}
