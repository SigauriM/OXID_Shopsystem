<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Classification;

use OxidShipping\Engine\Domain\ClassificationConfig;
use OxidShipping\Engine\Measurement\MeasuredPiece;

final readonly class PieceClassifier
{
    /**
     * @param list<MeasuredPiece> $pieces
     * @return list<ClassifiedPiece>
     */
    public function classifyAll(array $pieces, ClassificationConfig $config): array
    {
        $rules = new ClassificationRuleSet([
            new GirthRule($config->girth),
            new MaxLengthRule($config->maxLength),
            new BillableWeightRule($config->billableWeight),
        ]);

        $classified = [];
        foreach ($pieces as $piece) {
            $classified[] = new ClassifiedPiece($piece, $rules->classFor($piece));
        }

        return $classified;
    }
}
