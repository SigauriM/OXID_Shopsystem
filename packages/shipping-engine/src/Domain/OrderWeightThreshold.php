<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class OrderWeightThreshold
{
    public function __construct(public int $grams)
    {
        if ($grams < 1) {
            throw new \InvalidArgumentException(
                'Order weight threshold must be 1 or greater.',
            );
        }
    }
}
