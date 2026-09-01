<?php

declare(strict_types=1);

namespace OxidShipping\Module\Sandbox;

final readonly class SandboxView
{
    public const IDLE = 'idle';

    public const FIELDS = 'fields';

    public const VALIDATION = 'validation';

    public const QUOTED = 'quoted';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @param array<string, string> $input
     * @param array<string, string> $fieldErrors
     * @param list<array{field: string, code: string}> $validationErrors
     * @param array{type: string, zoneId?: string, reason?: string}|null $destination
     * @param list<array<string, mixed>> $pieces
     * @param list<array<string, mixed>> $shipments
     * @param list<array<string, mixed>> $trace
     */
    public function __construct(
        public string $kind,
        public array $input,
        public array $fieldErrors = [],
        public array $validationErrors = [],
        public ?array $destination = null,
        public array $pieces = [],
        public array $shipments = [],
        public array $trace = [],
        public int $totalCents = 0,
        public string $configVersion = '',
        public string $configHash = '',
        public string $quoteJson = '',
    ) {
    }

    public static function idle(): self
    {
        return new self(self::IDLE, self::emptyInput());
    }

    /**
     * @param array<string, string> $input
     * @param array<string, string> $fieldErrors
     */
    public static function fields(array $input, array $fieldErrors): self
    {
        return new self(self::FIELDS, $input, fieldErrors: $fieldErrors);
    }

    /**
     * @param array<string, string> $input
     * @param list<array{field: string, code: string}> $errors
     */
    public static function validation(array $input, array $errors): self
    {
        return new self(self::VALIDATION, $input, validationErrors: $errors);
    }

    /**
     * @param array<string, string> $input
     * @param array{type: string, zoneId?: string, reason?: string} $destination
     * @param list<array<string, mixed>> $pieces
     * @param list<array<string, mixed>> $shipments
     * @param list<array<string, mixed>> $trace
     */
    public static function quoted(
        array $input,
        array $destination,
        array $pieces,
        array $shipments,
        array $trace,
        int $totalCents,
        string $configVersion,
        string $configHash,
        string $quoteJson,
    ): self {
        return new self(
            self::QUOTED,
            $input,
            destination: $destination,
            pieces: $pieces,
            shipments: $shipments,
            trace: $trace,
            totalCents: $totalCents,
            configVersion: $configVersion,
            configHash: $configHash,
            quoteJson: $quoteJson,
        );
    }

    /**
     * @param array<string, string> $input
     */
    public static function unavailable(array $input): self
    {
        return new self(self::UNAVAILABLE, $input);
    }

    /**
     * @return array<string, string>
     */
    public static function emptyInput(): array
    {
        return [
            'lengthMm' => '',
            'widthMm' => '',
            'heightMm' => '',
            'weightGrams' => '',
            'quantity' => '1',
            'postalCode' => '',
            'country' => 'DE',
            'indoor' => '0',
        ];
    }
}
