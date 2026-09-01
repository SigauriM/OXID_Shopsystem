<?php

declare(strict_types=1);

namespace OxidShipping\Module\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Registry;

final class TariffVersionsController extends TariffAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplateName('@oxidshipping/admin/tariff_versions.html.twig');
    }

    public function activate()
    {
        if (!$this->isMutatingPost()) {
            return;
        }

        $id = (string) Registry::getRequest()->getRequestParameter('oxid', '');
        try {
            $this->adminService()->activateVersion($id);
            parent::save();
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->_aViewData['kernelMessage'] = $exception->getMessage();
        }
    }

    public function render()
    {
        $template = parent::render();
        $this->_aViewData['tariffVersions'] = $this->adminService()->listVersions();
        if (!isset($this->_aViewData['kernelMessage'])) {
            $this->_aViewData['kernelMessage'] = '';
        }

        return $template;
    }
}
