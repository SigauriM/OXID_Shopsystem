<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Validation;

use OxidShipping\Engine\Input\InputNormalizer;
use OxidShipping\Engine\Input\QuoteRequest;

final class InputValidator
{
    /**
     * @return list<ValidationError>
     */
    public function validate(QuoteRequest $request): array
    {
        $errors = [];
        $lineCount = count($request->lines);

        if ($lineCount === 0) {
            $errors[] = new ValidationError(
                'lines',
                ValidationErrorCode::CartEmpty,
                'Cart has no lines.',
            );
        } else {
            if ($lineCount > InputLimits::MAX_LINES) {
                $errors[] = new ValidationError(
                    'lines',
                    ValidationErrorCode::CartTooManyLines,
                    'Cart must have at most ' . InputLimits::MAX_LINES . ' lines.',
                );
            }

            $linesToCheck = min($lineCount, InputLimits::MAX_LINES);
            for ($index = 0; $index < $linesToCheck; $index++) {
                $line = $request->lines[$index];
                $prefix = 'lines.' . $index;

                if ($line->quantity < 1) {
                    $errors[] = new ValidationError(
                        $prefix . '.quantity',
                        ValidationErrorCode::QuantityTooSmall,
                        'Quantity must be at least 1 (each unit is a separate piece).',
                    );
                } elseif ($line->quantity > InputLimits::MAX_QUANTITY) {
                    $errors[] = new ValidationError(
                        $prefix . '.quantity',
                        ValidationErrorCode::QuantityTooLarge,
                        'Quantity must be at most ' . InputLimits::MAX_QUANTITY . '.',
                    );
                }

                $sides = [
                    'lengthMm' => $line->lengthMm,
                    'widthMm' => $line->widthMm,
                    'heightMm' => $line->heightMm,
                ];
                foreach ($sides as $field => $millimetres) {
                    if ($millimetres <= 0) {
                        $errors[] = new ValidationError(
                            $prefix . '.' . $field,
                            ValidationErrorCode::DimensionNotPositive,
                            'Each dimension must be greater than 0 millimetres.',
                        );
                    }
                }

                $longest = max($line->lengthMm, $line->widthMm, $line->heightMm);
                if ($longest > InputLimits::MAX_SIDE_MM) {
                    $errors[] = new ValidationError(
                        $prefix . '.longestSideMm',
                        ValidationErrorCode::DimensionTooLong,
                        'Longest side must be at most ' . InputLimits::MAX_SIDE_MM . ' millimetres.',
                    );
                }

                if ($line->weightGrams <= 0) {
                    $errors[] = new ValidationError(
                        $prefix . '.weightGrams',
                        ValidationErrorCode::WeightNotPositive,
                        'Piece weight must be greater than 0 grams.',
                    );
                } elseif ($line->weightGrams > InputLimits::MAX_WEIGHT_G) {
                    $errors[] = new ValidationError(
                        $prefix . '.weightGrams',
                        ValidationErrorCode::WeightTooHeavy,
                        'Piece weight must be at most ' . InputLimits::MAX_WEIGHT_G . ' grams.',
                    );
                }
            }
        }

        $postalCode = InputNormalizer::postalCode($request->postalCode);
        if ($postalCode === '') {
            $errors[] = new ValidationError(
                'postalCode',
                ValidationErrorCode::PostalCodeEmpty,
                'Postal code is empty.',
            );
        } elseif (preg_match(InputLimits::POSTAL_CODE_PATTERN, $postalCode) !== 1) {
            $errors[] = new ValidationError(
                'postalCode',
                ValidationErrorCode::PostalCodeInvalid,
                'Postal code must be 2 to 10 letters, digits, spaces or hyphens.',
            );
        }

        $country = InputNormalizer::country($request->country);
        if ($country === '') {
            $errors[] = new ValidationError(
                'country',
                ValidationErrorCode::CountryEmpty,
                'Country is empty.',
            );
        } elseif (preg_match(InputLimits::COUNTRY_PATTERN, $country) !== 1) {
            $errors[] = new ValidationError(
                'country',
                ValidationErrorCode::CountryInvalid,
                'Country must be a 2-letter ISO-like code.',
            );
        }

        return $errors;
    }
}