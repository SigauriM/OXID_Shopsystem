<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Validation;

enum ValidationErrorCode: string
{
    case CartEmpty = 'cart_empty';
    case CartTooManyLines = 'cart_too_many_lines';
    case QuantityTooSmall = 'quantity_too_small';
    case QuantityTooLarge = 'quantity_too_large';
    case DimensionNotPositive = 'dimension_not_positive';
    case DimensionTooLong = 'dimension_too_long';
    case WeightNotPositive = 'weight_not_positive';
    case WeightTooHeavy = 'weight_too_heavy';
    case PostalCodeEmpty = 'postal_code_empty';
    case PostalCodeInvalid = 'postal_code_invalid';
    case CountryEmpty = 'country_empty';
    case CountryInvalid = 'country_invalid';
}
