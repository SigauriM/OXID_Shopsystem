<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tariff;

final readonly class TariffVersion
{
    public function __construct(
        public string $id,
        public string $version,
        public string $hash,
        public bool $active,
        public ?string $authorId,
        public string $timestamp,
        public string $payload,
    ) {
    }
}
