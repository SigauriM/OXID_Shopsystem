<?php

declare(strict_types=1);

namespace OxidShipping\Module\Admin;

final readonly class SaveOutcome
{
    public const SAVED = 'saved';

    public const RENAMED = 'renamed';

    public const UNCHANGED = 'unchanged';

    public const CONFLICT = 'conflict';

    public const REJECTED = 'rejected';

    public const INVALID_FIELDS = 'invalid_fields';

    public const BLOCKED = 'blocked';

    /**
     * @param array<string, string> $fieldErrors
     * @param array<string, mixed>|null $formDocument
     */
    private function __construct(
        public string $kind,
        public string $kernelMessage = '',
        public array $fieldErrors = [],
        public ?string $suggestedVersion = null,
        public ?array $formDocument = null,
    ) {
    }

    public static function saved(?string $suggestedVersion = null): self
    {
        return new self(self::SAVED, suggestedVersion: $suggestedVersion);
    }

    public static function renamed(): self
    {
        return new self(self::RENAMED);
    }

    public static function unchanged(): self
    {
        return new self(self::UNCHANGED);
    }

    /**
     * @param array<string, mixed> $formDocument
     */
    public static function conflict(array $formDocument): self
    {
        return new self(self::CONFLICT, formDocument: $formDocument);
    }

    /**
     * @param array<string, mixed> $formDocument
     */
    public static function rejected(string $kernelMessage, array $formDocument): self
    {
        return new self(self::REJECTED, kernelMessage: $kernelMessage, formDocument: $formDocument);
    }

    /**
     * @param array<string, string> $fieldErrors
     * @param array<string, mixed> $formDocument
     */
    public static function invalidFields(array $fieldErrors, array $formDocument): self
    {
        return new self(self::INVALID_FIELDS, fieldErrors: $fieldErrors, formDocument: $formDocument);
    }

    /**
     * @param array<string, mixed> $formDocument
     */
    public static function blocked(string $message, array $formDocument): self
    {
        return new self(self::BLOCKED, kernelMessage: $message, formDocument: $formDocument);
    }

    public function wrote(): bool
    {
        return $this->kind === self::SAVED || $this->kind === self::RENAMED;
    }
}
