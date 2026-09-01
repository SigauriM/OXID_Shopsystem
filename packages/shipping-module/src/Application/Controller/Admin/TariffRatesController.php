<?php

declare(strict_types=1);

namespace OxidShipping\Module\Application\Controller\Admin;

final class TariffRatesController extends TariffAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplateName('@oxidshipping/admin/tariff_rates.html.twig');
    }

    public function render()
    {
        $template = parent::render();
        $loaded = $this->adminService()->load();

        $this->_aViewData['tariffDocument'] = $loaded['document'] ?? null;
        $this->_aViewData['baseHash'] = $loaded['baseHash'] ?? '';
        $this->_aViewData['rateClasses'] = ['paket', 'sperrgut', 'spedition'];

        return $template;
    }
}
