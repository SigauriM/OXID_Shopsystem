<?php

declare(strict_types=1);

namespace OxidShipping\Module\Mapping;

final readonly class MappingFailed
{
    public function __construct(public string $field)
    {
    }
}
