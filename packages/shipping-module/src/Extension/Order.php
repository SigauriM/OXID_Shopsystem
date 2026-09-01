<?php

declare(strict_types=1);

namespace OxidShipping\Module\Extension;

use OxidShipping\Module\Quote\ShopQuote;

/**
 * @eshopExtension
 * @mixin \OxidEsales\Eshop\Application\Model\Order
 */
class Order extends Order_parent
{
    public function validateDelivery($oBasket)
    {
        $parentState = parent::validateDelivery($oBasket);
        if ($parentState !== null) {
            return $parentState;
        }

        $quote = $oBasket->getOxidShippingQuote();
        if (!$quote instanceof ShopQuote || !$quote->isQuoted()) {
            return self::ORDER_STATE_INVALIDDELIVERY;
        }
    }
}
