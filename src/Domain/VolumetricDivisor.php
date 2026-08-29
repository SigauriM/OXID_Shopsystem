<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Domain;

final readonly class VolumetricDivisor
{
    /**
     * @param int<1000, 10000> $dimFactorCmKg
     */
    private function __construct(private int $dimFactorCmKg)
    {
    }

    public static function fromDimFactorCmKg(int $dimFactorCmKg): self
    {
        if ($dimFactorCmKg < 1000 || $dimFactorCmKg > 10_000) {
            throw new \InvalidArgumentException(
                'DIM factor must be between 1000 and 10000 (cm³/kg from the tariff).',
            );
        }

        return new self($dimFactorCmKg);
    }

    /**
     * Carrier DIM is cm³/kg; this returns that factor times 1000.
     */
    public function mmG(): int
    {
        return $this->dimFactorCmKg * 1000;
    }

    public function dimFactorCmKg(): int
    {
        return $this->dimFactorCmKg;
    }
}
