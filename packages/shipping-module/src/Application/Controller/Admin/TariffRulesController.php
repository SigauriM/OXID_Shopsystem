<?php

declare(strict_types=1);

namespace OxidShipping\Module\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Registry;
use OxidShipping\Module\Admin\SaveOutcome;

final class TariffRulesController extends TariffAdminController
{
    private ?SaveOutcome $saveOutcome = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplateName('@oxidshipping/admin/tariff_rules.html.twig');
    }

    public function save()
    {
        if (!$this->isMutatingPost()) {
            return;
        }

        $this->saveOutcome = $this->adminService()->saveClassification(
            $this->classificationForm(),
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

        $this->_aViewData['tariffDocument'] = $usePosted
            ? $outcome->formDocument
            : ($loaded['document'] ?? null);
        $this->_aViewData['baseHash'] = $usePosted
            ? (string) Registry::getRequest()->getRequestParameter('baseHash', '')
            : ($loaded['baseHash'] ?? '');
        $this->_aViewData['fieldErrors'] = $outcome->fieldErrors ?? [];
        $this->_aViewData['kernelMessage'] = $outcome->kernelMessage ?? '';
        $this->_aViewData['saveKind'] = $outcome->kind ?? '';
        $this->_aViewData['suggestedVersion'] = $outcome->suggestedVersion ?? null;
        $this->_aViewData['thresholdClasses'] = ['sperrgut', 'spedition'];

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function classificationForm(): array
    {
        $request = Registry::getRequest();
        $classification = $request->getRequestParameter('classification', []);
        if (!is_array($classification)) {
            $classification = [];
        }

        return [
            'baseHash' => (string) $request->getRequestParameter('baseHash', ''),
            'version' => (string) $request->getRequestParameter('version', ''),
            'dimFactorCmKg' => $request->getRequestParameter('dimFactorCmKg', ''),
            'orderWeightSpeditionGrams' => $request->getRequestParameter('orderWeightSpeditionGrams', ''),
            'classification' => $classification,
        ];
    }
}
