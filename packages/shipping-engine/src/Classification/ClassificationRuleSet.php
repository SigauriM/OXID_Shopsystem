<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Classification;

use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\ShippingClass;

final readonly class ClassificationRuleSet
{
    /**
     * @param list<PieceClassificationRule> $rules
     */
    public function __construct(
        private array $rules,
    ) {
    }

    public function classFor(MeasuredPiece $piece): ShippingClass
    {
        $class = ShippingClass::Paket;
        foreach ($this->rules as $rule) {
            $vote = $rule->floor($piece);
            if ($vote !== null) {
                $class = $class->atLeast($vote);
            }
        }

        return $class;
    }
}
