<?php

declare(strict_types=1);

namespace OxidShipping\Module\Adapter;

use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\Registry;
use OxidShipping\Module\Seo\ProductFields;

final class ArticleSeoSource
{
    private function __construct()
    {
    }

    public static function fields(Article $article, string $canonicalUrl): ProductFields
    {
        return new ProductFields(
            trim((string) $article->oxarticles__oxtitle->value),
            trim((string) $article->oxarticles__oxartnum->value),
            self::absoluteImage($article),
            self::jsonUrl($canonicalUrl),
            self::formattedBrutto($article),
            self::activeCurrency(),
            $article->isBuyable()
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
        );
    }

    private static function jsonUrl(string $canonicalUrl): ?string
    {
        $url = html_entity_decode(trim($canonicalUrl), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $url === '' ? null : $url;
    }

    private static function absoluteImage(Article $article): ?string
    {
        $pic = trim((string) $article->oxarticles__oxpic1->value);
        if ($pic === '') {
            return null;
        }

        $url = trim((string) $article->getPictureUrl(1));
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        return $url;
    }

    private static function formattedBrutto(Article $article): ?string
    {
        $price = $article->getPrice();
        if (!$price instanceof Price) {
            return null;
        }

        $brutto = $price->getBruttoPrice();
        if (!is_numeric($brutto)) {
            return null;
        }

        return number_format((float) $brutto, 2, '.', '');
    }

    private static function activeCurrency(): string
    {
        $currency = Registry::getConfig()->getActShopCurrencyObject();
        if (!is_object($currency) || !isset($currency->name)) {
            return '';
        }

        return trim((string) $currency->name);
    }
}
