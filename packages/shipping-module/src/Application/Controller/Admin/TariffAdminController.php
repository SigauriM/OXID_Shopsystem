<?php

declare(strict_types=1);

namespace OxidShipping\Module\Application\Controller\Admin;

use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidShipping\Module\Admin\TariffAdminService;

abstract class TariffAdminController extends AdminDetailsController
{
    private const MENU_CLASS = 'oxidshipping_rules';

    /**
     * @var list<string>
     */
    private const TAB_CLASSES = [
        'oxidshipping_rules',
        'oxidshipping_zones',
        'oxidshipping_rates',
        'oxidshipping_surcharges',
        'oxidshipping_versions',
        'oxidshipping_sandbox',
    ];

    public function render()
    {
        parent::render();

        $act = array_search($this->getClassKey(), self::TAB_CLASSES, true);
        if (!is_int($act)) {
            $act = 0;
        }

        // getTabs looks up SUBMENU[@cl], not the current tab class.
        $this->_aViewData['editnavi'] = $this->getNavigation()->getTabs(self::MENU_CLASS, $act);
        $this->_aViewData['noOXIDCheck'] = true;

        return $this->getTemplateName();
    }

    protected function isMutatingPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST';
    }

    protected function adminService(): TariffAdminService
    {
        $service = ContainerFacade::get(TariffAdminService::class);
        if (!$service instanceof TariffAdminService) {
            throw new \RuntimeException('TariffAdminService is not in the shop container.');
        }

        return $service;
    }

    protected function authorId(): ?string
    {
        $user = $this->getUser();
        if (!is_object($user) || !method_exists($user, 'getId')) {
            return null;
        }
        $id = $user->getId();

        return is_string($id) && $id !== '' ? $id : null;
    }
}
