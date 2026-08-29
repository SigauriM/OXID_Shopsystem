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
        public string $postalCode,
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