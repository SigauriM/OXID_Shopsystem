<?php

declare(strict_types=1);

namespace OxidShipping\Module\Sandbox;

final readonly class SandboxRequest
{
    public function __construct(
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
        public int $weightGrams,
        public int $quantity,
        public string $postalCode,
        public string $country,
        public bool $indoor,
    ) {
    }

    /**
     * @param array<string, mixed> $form
     * @return array{
     *     request: ?self,
     *     fieldErrors: array<string, string>,
     *     input: array<string, string>
     * }
     */
    public static function parse(array $form): array
    {
        $errors = [];
        $length = self::parseInt($form['lengthMm'] ?? '', 'lengthMm', $errors);
        $width = self::parseInt($form['widthMm'] ?? '', 'widthMm', $errors);
        $height = self::parseInt($form['heightMm'] ?? '', 'heightMm', $errors);
        $weight = self::parseInt($form['weightGrams'] ?? '', 'weightGrams', $errors);
        $quantity = self::parseInt($form['quantity'] ?? '', 'quantity', $errors);
        $postalCode = self::parsePostalCode($form['postalCode'] ?? '', $errors);
        $country = self::parseCountry($form['country'] ?? '', $errors);
        $indoor = self::parseBool($form['indoor'] ?? '0');

        $input = [
            'lengthMm' => self::rawString($form['lengthMm'] ?? ''),
            'widthMm' => self::rawString($form['widthMm'] ?? ''),
            'heightMm' => self::rawString($form['heightMm'] ?? ''),
            'weightGrams' => self::rawString($form['weightGrams'] ?? ''),
            'quantity' => self::rawString($form['quantity'] ?? ''),
            'postalCode' => $postalCode,
            'country' => $country,
            'indoor' => $indoor ? '1' : '0',
        ];

        if (
            $errors !== [] || $length === null || $width === null || $height === null
            || $weight === null || $quantity === null
        ) {
            return [
                'request' => null,
                'fieldErrors' => $errors,
                'input' => $input,
            ];
        }

        return [
            'request' => new self(
                $length,
                $width,
                $height,
                $weight,
                $quantity,
                $postalCode,
                $country,
                $indoor,
            ),
            'fieldErrors' => [],
            'input' => $input,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function input(): array
    {
        return [
            'lengthMm' => (string) $this->lengthMm,
            'widthMm' => (string) $this->widthMm,
            'heightMm' => (string) $this->heightMm,
            'weightGrams' => (string) $this->weightGrams,
            'quantity' => (string) $this->quantity,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'indoor' => $this->indoor ? '1' : '0',
        ];
    }

    /**
     * @param array<string, string> $errors
     */
    private static function parseInt(mixed $raw, string $field, array &$errors): ?int
    {
        if (is_int($raw)) {
            if ($raw < 0) {
                $errors[$field] = 'negative';

                return null;
            }

            return $raw;
        }
        if (!is_string($raw)) {
            $errors[$field] = 'not_integer';

            return null;
        }
        $value = trim($raw);
        if ($value === '') {
            $errors[$field] = 'empty';

            return null;
        }
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            $errors[$field] = 'not_integer';

            return null;
        }
        if (str_starts_with($value, '-')) {
            $errors[$field] = 'negative';

            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function parsePostalCode(mixed $raw, array &$errors): string
    {
        if (is_int($raw)) {
            $errors['postalCode'] = 'not_string';

            return (string) $raw;
        }
        if (!is_string($raw)) {
            $errors['postalCode'] = 'empty';

            return '';
        }
        $value = trim($raw);
        if ($value === '') {
            $errors['postalCode'] = 'empty';
        }
        if (strlen($value) > 10) {
            $errors['postalCode'] = 'too_long';
        }

        return $value;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function parseCountry(mixed $raw, array &$errors): string
    {
        if (!is_string($raw) && !is_int($raw)) {
            $errors['country'] = 'empty';

            return '';
        }
        $value = strtoupper(trim((string) $raw));
        if ($value === '') {
            $errors['country'] = 'empty';

            return $value;
        }
        if (strlen($value) > 2) {
            $errors['country'] = 'too_long';
        }

        return $value;
    }

    private static function parseBool(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw)) {
            return $raw === 1;
        }
        if (!is_string($raw)) {
            return false;
        }

        return $raw === '1' || strtolower($raw) === 'true' || $raw === 'on';
    }

    private static function rawString(mixed $raw): string
    {
        if (is_string($raw) || is_int($raw)) {
            return trim((string) $raw);
        }

        return '';
    }
}
