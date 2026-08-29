<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class ServedCountries
{
    /**
     * @param list<string> $codes
     */
    private function __construct(private array $codes)
    {
    }

    /**
     * @param list<string> $codes
     */
    public static function fromCodes(array $codes): self
    {
        if ($codes === []) {
            throw new \InvalidArgumentException('Served country list must not be empty.');
        }

        $seen = [];
        foreach ($codes as $code) {
            if (isset($seen[$code])) {
                throw new \InvalidArgumentException('Served country list must not contain duplicates.');
            }
            $seen[$code] = true;

            if (!ConfigPostalFormat::supports($code)) {
                throw new \InvalidArgumentException('Served country has no config postal form.');
            }
        }

        $sorted = $codes;
        sort($sorted, SORT_STRING);

        return new self($sorted);
    }

    public function has(string $country): bool
    {
        return in_array($country, $this->codes, true);
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->codes;
    }
}
