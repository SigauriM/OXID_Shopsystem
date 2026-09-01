<?php

declare(strict_types=1);

namespace OxidShipping\Module\Extension;

use OxidEsales\Eshop\Core\Price;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidShipping\Module\Adapter\OxBasketSource;
use OxidShipping\Module\Quote\QuoteFacade;
use OxidShipping\Module\Quote\ShopQuote;
use OxidShipping\Module\Quote\ShopQuoteStatus;

/**
 * @eshopExtension
 * @mixin \OxidEsales\Eshop\Application\Model\Basket
 */
class Basket extends Basket_parent
{
    public function getOxidShippingQuote(): ShopQuote
    {
        if ($this->getProductsCount() === 0) {
            return new ShopQuote(
                ShopQuoteStatus::NeedAddress,
                0,
                [],
                'OXIDSHIPPING_NEED_ADDRESS',
            );
        }

        $facade = ContainerFacade::get(QuoteFacade::class);
        if (!$facade instanceof QuoteFacade) {
            throw new \RuntimeException('QuoteFacade is not in the shop container.');
        }

        return $facade->quote(new OxBasketSource($this));
    }

    protected function calcDeliveryCost()
    {
        if ($this->_oDeliveryPrice !== null) {
            return $this->_oDeliveryPrice;
        }

        $price = oxNew(Price::class);
        $price->setBruttoPriceMode();

        if ($this->getProductsCount() === 0) {
            return $price;
        }

        try {
            $quote = $this->getOxidShippingQuote();
            if ($quote->isQuoted()) {
                $price->setVat($this->getAdditionalServicesVatPercent());
                $price->setPrice($quote->totalCents / 100);
            }
        } catch (\Throwable) {
            return $price;
        }

        return $price;
    }
}
