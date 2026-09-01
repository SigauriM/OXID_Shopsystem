<?php

declare(strict_types=1);

namespace OxidShipping\Module\Extension;

use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidShipping\Module\Adapter\ArticleCartLine;
use OxidShipping\Module\Adapter\ArticleSeoSource;
use OxidShipping\Module\Quote\StorefrontCountry;
use OxidShipping\Module\Seo\ProductSchemaBuilder;

trait ProductSchemaView
{
    public function getOxidShippingCountryCode(): string
    {
        return StorefrontCountry::default();
    }

    public function getOxidShippingSchemaJson(): string
    {
        $article = $this->getProduct();
        if (!$article instanceof Article) {
            return '';
        }

        try {
            $builder = ContainerFacade::get(ProductSchemaBuilder::class);
            if (!$builder instanceof ProductSchemaBuilder) {
                throw new \RuntimeException('ProductSchemaBuilder is not in the shop container.');
            }

            return $builder->json(
                ArticleCartLine::from($article, 1.0),
                ArticleSeoSource::fields($article, $this->shippingCanonicalUrl($article)),
            );
        } catch (\Throwable $exception) {
            Registry::getLogger()->error('Shipping product schema could not be built.', ['exception' => $exception]);

            return '';
        }
    }

    private function shippingCanonicalUrl(Article $article): string
    {
        $fromView = (string) $this->getCanonicalUrl();
        if ($fromView !== '') {
            return $fromView;
        }

        if ($article->oxarticles__oxparentid->value) {
            $parent = $this->getParentProduct($article->oxarticles__oxparentid->value);
            if ($parent instanceof Article) {
                $article = $parent;
            }
        }

        $utilsUrl = Registry::getUtilsUrl();
        if (Registry::getUtils()->seoIsActive()) {
            return (string) $utilsUrl->prepareCanonicalUrl(
                $article->getBaseSeoLink($article->getLanguage(), true),
            );
        }

        return (string) $utilsUrl->prepareCanonicalUrl(
            $article->getBaseStdLink($article->getLanguage()),
        );
    }
}
