<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Validation;

final class InputLimits
{
    public const MAX_SIDE_MM = 15000;

    public const MAX_WEIGHT_G = 1_000_000;

    public const MAX_QUANTITY = 999;

    public const MAX_LINES = 100;

    private function __construct()
    {
    }
}
