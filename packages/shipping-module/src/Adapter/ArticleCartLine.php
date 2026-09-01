<?php

declare(strict_types=1);

namespace OxidShipping\Module\Adapter;

use OxidEsales\Eshop\Application\Model\Article;
use OxidShipping\Module\Mapping\CartLine;

final class ArticleCartLine
{
    private function __construct()
    {
    }

    public static function from(Article $article, float $quantity): CartLine
    {
        $articleNumber = trim((string) $article->oxarticles__oxartnum->value);
        $lineId = $articleNumber !== '' ? $articleNumber : (string) $article->getId();

        return new CartLine(
            $lineId,
            (float) $article->oxarticles__oxlength->value,
            (float) $article->oxarticles__oxwidth->value,
            (float) $article->oxarticles__oxheight->value,
            (float) $article->oxarticles__oxweight->value,
            $quantity,
        );
    }
}
