<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Validation\ValidationError;

final readonly class ValidationFailed implements QuoteResult
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(public array $errors)
    {
        if ($this->errors === []) {
            throw new \InvalidArgumentException('ValidationFailed requires at least one error.');
        }
    }
}