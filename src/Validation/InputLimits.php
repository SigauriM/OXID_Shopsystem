<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Validation;

final class InputLimits
{
    public const MAX_SIDE_MM = 15000;

    public const MAX_WEIGHT_G = 1_000_000;

    public const MAX_QUANTITY = 999;

    public const MAX_LINES = 100;

    public const POSTAL_CODE_PATTERN = '/^[0-9A-Za-z][0-9A-Za-z \-]{1,9}$/';

    /** ISO 3166-1 alpha-2 shape after normalisation. Not membership in {DE, AT}. */
    public const COUNTRY_PATTERN = '/^[A-Z]{2}$/';

    private function __construct()
    {
    }
}