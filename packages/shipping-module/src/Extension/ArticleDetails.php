<?php

declare(strict_types=1);

namespace OxidShipping\Module\Extension;

/**
 * @eshopExtension
 * @mixin \OxidEsales\Eshop\Application\Controller\ArticleDetailsController
 */
class ArticleDetails extends ArticleDetails_parent
{
    use ProductSchemaView;
}
