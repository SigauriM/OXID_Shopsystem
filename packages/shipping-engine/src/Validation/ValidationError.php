<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Validation;

final readonly class ValidationError
{
    public function __construct(
        public string $field,
        public ValidationErrorCode $code,
        public string $message,
    ) {
    }
}