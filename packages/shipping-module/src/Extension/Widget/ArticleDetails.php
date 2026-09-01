<?php

declare(strict_types=1);

namespace OxidShipping\Module\Extension\Widget;

use OxidShipping\Module\Extension\ProductSchemaView;

/**
 * Apex renders productmain through this widget, not only the details controller.
 * Lives in its own namespace so ArticleDetails_parent is not the controller chain.
 *
 * @eshopExtension
 * @mixin \OxidEsales\Eshop\Application\Component\Widget\ArticleDetails
 */
class ArticleDetails extends ArticleDetails_parent
{
    use ProductSchemaView;
}
