<?php

declare(strict_types=1);

namespace OxidShipping\Module\Quote;

final readonly class ShopQuote
{
    /**
     * @param list<ShopShipment> $shipments
     * @param array<int, string> $lineLabels
     */
    public function __construct(
        public ShopQuoteStatus $status,
        public int $totalCents,
        public array $shipments,
        public string $messageLangKey,
        public array $lineLabels = [],
    ) {
    }

    public function isQuoted(): bool
    {
        return $this->status === ShopQuoteStatus::Quoted;
    }

    public function isNeedAddress(): bool
    {
        return $this->status === ShopQuoteStatus::NeedAddress;
    }

    public function isNotPossible(): bool
    {
        return $this->status === ShopQuoteStatus::NotPossible;
    }

    public function isInvalid(): bool
    {
        return $this->status === ShopQuoteStatus::Invalid;
    }

    public function allowsCheckout(): bool
    {
        return $this->isQuoted() || $this->isNeedAddress();
    }
}
