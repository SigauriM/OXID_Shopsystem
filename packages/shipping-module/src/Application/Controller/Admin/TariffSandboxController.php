<?php

declare(strict_types=1);

namespace OxidShipping\Module\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidShipping\Module\Sandbox\SandboxCalculator;
use OxidShipping\Module\Sandbox\SandboxRequest;
use OxidShipping\Module\Sandbox\SandboxView;

final class TariffSandboxController extends TariffAdminController
{
    private ?SandboxView $sandboxView = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplateName('@oxidshipping/admin/tariff_sandbox.html.twig');
    }

    public function calculate()
    {
        if (!$this->isMutatingPost()) {
            return;
        }

        $parsed = SandboxRequest::parse($this->sandboxForm());
        if ($parsed['request'] === null) {
            $this->sandboxView = SandboxView::fields($parsed['input'], $parsed['fieldErrors']);

            return;
        }

        $this->sandboxView = $this->calculator()->calculate($parsed['request']);
    }

    public function render()
    {
        $template = parent::render();
        $this->_aViewData['sandbox'] = $this->sandboxView ?? SandboxView::idle();

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function sandboxForm(): array
    {
        $request = Registry::getRequest();

        return [
            'lengthMm' => $request->getRequestParameter('lengthMm', ''),
            'widthMm' => $request->getRequestParameter('widthMm', ''),
            'heightMm' => $request->getRequestParameter('heightMm', ''),
            'weightGrams' => $request->getRequestParameter('weightGrams', ''),
            'quantity' => $request->getRequestParameter('quantity', ''),
            'postalCode' => $request->getRequestParameter('postalCode', ''),
            'country' => $request->getRequestParameter('country', ''),
            'indoor' => $request->getRequestParameter('indoor', '0'),
        ];
    }

    private function calculator(): SandboxCalculator
    {
        $service = ContainerFacade::get(SandboxCalculator::class);
        if (!$service instanceof SandboxCalculator) {
            throw new \RuntimeException('SandboxCalculator is not in the shop container.');
        }

        return $service;
    }
}
