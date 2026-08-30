<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class ClassificationConfig
{
    public function __construct(
        public ThresholdTable $girth,
        public ThresholdTable $maxLength,
        public ThresholdTable $billableWeight,
    ) {
    }
}
