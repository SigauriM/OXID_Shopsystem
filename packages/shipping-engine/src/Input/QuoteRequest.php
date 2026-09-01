<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Input;

final readonly class QuoteRequest
{
    /**
     * @var list<OrderLine>
     */
    public array $lines;

    /**
     * @param array<mixed> $lines
     */
    public function __construct(
        array $lines,
        /**
         * Raw postal code string (not int). Leading zeroes are significant.
         * Empty, whitespace-only or malformed values fail validation; they are not a zone reject.
         */
        public string $postalCode,
        /**
         * Raw country. After successful validation this is ISO 3166-1 alpha-2
         * (exactly two letters A–Z). Alpha-2 is a shape check, not a shipping list:
         * CH passes this gate; refusing CH is a later business reject.
         * DEU (alpha-3), GERMANY, D, DE1 are broken input (country_invalid / country_empty), not Rejected.
         */
        public string $country,
        public bool $indoor,
        public TariffConfig $config,
    ) {
        if (!array_is_list($lines)) {
            throw new \InvalidArgumentException('QuoteRequest lines must be a list.');
        }

        foreach ($lines as $index => $line) {
            if (!$line instanceof OrderLine) {
                throw new \InvalidArgumentException(
                    'QuoteRequest lines[' . $index . '] must be an OrderLine.',
                );
            }
        }

        $this->lines = $lines;
    }
}
