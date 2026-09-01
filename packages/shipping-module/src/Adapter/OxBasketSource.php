<?php

declare(strict_types=1);

namespace OxidShipping\Module\Adapter;

use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\BasketItem;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Registry;
use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Mapping\CartSource;

final class OxBasketSource implements CartSource
{
    public function __construct(private Basket $basket)
    {
    }

    public function lines(): array
    {
        $lines = [];
        foreach ($this->basket->getContents() as $item) {
            if (!$item instanceof BasketItem || $item->isBundle()) {
                continue;
            }

            $article = $item->getArticle();
            if (!$article instanceof Article) {
                throw new \RuntimeException('Basket item article is missing.');
            }

            $articleNumber = trim((string) $article->oxarticles__oxartnum->value);
            $lineId = $articleNumber !== '' ? $articleNumber : (string) $article->getId();

            $lines[] = new CartLine(
                $lineId,
                (float) $article->oxarticles__oxlength->value,
                (float) $article->oxarticles__oxwidth->value,
                (float) $article->oxarticles__oxheight->value,
                (float) $article->oxarticles__oxweight->value,
                (float) $item->getAmount(),
            );
        }

        return $lines;
    }

    public function postalCode(): string
    {
        return $this->destination()[0];
    }

    public function countryIso(): string
    {
        return $this->destination()[1];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function destination(): array
    {
        $user = $this->basket->getBasketUser();
        if (!$user instanceof User) {
            return ['', ''];
        }

        $deladrid = Registry::getSession()->getVariable('deladrid');
        if (is_string($deladrid) && $deladrid !== '') {
            $address = oxNew(Address::class);
            if ($address->load($deladrid)) {
                return [
                    trim((string) $address->oxaddress__oxzip->value),
                    $this->isoFromCountryId((string) $address->oxaddress__oxcountryid->value),
                ];
            }
        }

        return [
            trim((string) $user->oxuser__oxzip->value),
            $this->isoFromCountryId((string) $user->oxuser__oxcountryid->value),
        ];
    }

    private function isoFromCountryId(string $countryId): string
    {
        if ($countryId === '') {
            return '';
        }

        $country = oxNew(Country::class);
        if (!$country->load($countryId)) {
            return '';
        }

        return trim((string) $country->oxcountry__oxisoalpha2->value);
    }
}
