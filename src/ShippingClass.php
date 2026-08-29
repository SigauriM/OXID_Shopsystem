<?php

declare(strict_types=1);

namespace OxidShipping\Engine;

enum ShippingClass
{
    case Paket;
    case Sperrgut;
    case Spedition;

    public function rank(): int
    {
        return match ($this) {
            self::Paket => 0,
            self::Sperrgut => 1,
            self::Spedition => 2,
        };
    }

    public function atLeast(self $other): self
    {
        return $this->rank() >= $other->rank() ? $this : $other;
    }
}