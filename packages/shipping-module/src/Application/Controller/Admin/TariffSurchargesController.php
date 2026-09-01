<?php

declare(strict_types=1);

namespace OxidShipping\Module\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Registry;
use OxidShipping\Module\Admin\DocumentDraft;
use OxidShipping\Module\Admin\SaveOutcome;

final class TariffSurchargesController extends TariffAdminController
{
    private ?SaveOutcome $saveOutcome = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplateName('@oxidshipping/admin/tariff_surcharges.html.twig');
    }

    public function save()
    {
        if (!$this->isMutatingPost()) {
            return;
        }

        $this->saveOutcome = $this->adminService()->saveSurcharges(
            $this->surchargesForm(),
            $this->authorId(),
        );
        if ($this->saveOutcome->wrote()) {
            parent::save();
        }
    }

    public function render()
    {
        $template = parent::render();
        $loaded = $this->adminService()->load();
        $outcome = $this->saveOutcome;
        $usePosted = $outcome !== null && $outcome->formDocument !== null;

        $document = $usePosted
            ? $outcome->formDocument
            : ($loaded['document'] ?? null);
        $rows = is_array($document) ? DocumentDraft::surchargesByPriority($document) : [];
        $order = [];
        foreach ($rows as $row) {
            $order[] = $row['id'];
        }

        $this->_aViewData['tariffDocument'] = $document;
        $this->_aViewData['surchargeRows'] = $rows;
        $this->_aViewData['surchargeOrder'] = implode(',', $order);
        $this->_aViewData['baseHash'] = $usePosted
            ? (string) Registry::getRequest()->getRequestParameter('baseHash', '')
            : ($loaded['baseHash'] ?? '');
        $this->_aViewData['fieldErrors'] = $outcome->fieldErrors ?? [];
        $this->_aViewData['kernelMessage'] = $outcome->kernelMessage ?? '';
        $this->_aViewData['saveKind'] = $outcome->kind ?? '';
        $this->_aViewData['suggestedVersion'] = $outcome->suggestedVersion ?? null;

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function surchargesForm(): array
    {
        $request = Registry::getRequest();
        $island = $request->getRequestParameter('island', []);
        if (!is_array($island)) {
            $island = [];
        }
        $indoor = $request->getRequestParameter('indoor', []);
        if (!is_array($indoor)) {
            $indoor = [];
        }

        return [
            'baseHash' => (string) $request->getRequestParameter('baseHash', ''),
            'version' => (string) $request->getRequestParameter('version', ''),
            'surchargeOrder' => (string) $request->getRequestParameter('surchargeOrder', ''),
            'island' => $island,
            'indoor' => $indoor,
        ];
    }
}
