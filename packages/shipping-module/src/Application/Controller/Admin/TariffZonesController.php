<?php

declare(strict_types=1);

namespace OxidShipping\Module\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Registry;
use OxidShipping\Module\Admin\DocumentDraft;
use OxidShipping\Module\Admin\SaveOutcome;

final class TariffZonesController extends TariffAdminController
{
    private ?SaveOutcome $saveOutcome = null;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplateName('@oxidshipping/admin/tariff_zones.html.twig');
    }

    public function save()
    {
        if (!$this->isMutatingPost()) {
            return;
        }

        $this->saveOutcome = $this->adminService()->saveZones(
            $this->zonesForm(),
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
        if (is_array($document)) {
            $document = DocumentDraft::attachTransitDays($document);
        }

        $this->_aViewData['tariffDocument'] = $document;
        $this->_aViewData['baseHash'] = $usePosted
            ? (string) Registry::getRequest()->getRequestParameter('baseHash', '')
            : ($loaded['baseHash'] ?? '');
        $this->_aViewData['fieldErrors'] = $outcome->fieldErrors ?? [];
        $this->_aViewData['kernelMessage'] = $outcome->kernelMessage ?? '';
        $this->_aViewData['saveKind'] = $outcome->kind ?? '';
        $this->_aViewData['suggestedVersion'] = $outcome->suggestedVersion ?? null;
        $this->_aViewData['servedCountryChoices'] = ['DE', 'AT'];
        $this->_aViewData['shippingClasses'] = ['paket', 'sperrgut', 'spedition'];
        $this->_aViewData['zoneIds'] = self::zoneIds($document);

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function zonesForm(): array
    {
        $request = Registry::getRequest();
        $definitions = $request->getRequestParameter('definitions', []);
        if (!is_array($definitions)) {
            $definitions = [];
        }
        $postalEntries = $request->getRequestParameter('postalEntries', []);
        if (!is_array($postalEntries)) {
            $postalEntries = [];
        }
        $servedCountries = $request->getRequestParameter('servedCountries', []);
        if (!is_array($servedCountries)) {
            $servedCountries = [];
        }

        return [
            'baseHash' => (string) $request->getRequestParameter('baseHash', ''),
            'version' => (string) $request->getRequestParameter('version', ''),
            'servedCountries' => $servedCountries,
            'definitions' => $definitions,
            'postalEntries' => $postalEntries,
        ];
    }

    /**
     * @param array<string, mixed>|null $document
     * @return list<string>
     */
    private static function zoneIds(?array $document): array
    {
        if ($document === null) {
            return [];
        }
        $ids = [];
        $definitions = $document['zones']['directory']['definitions'] ?? [];
        if (!is_array($definitions)) {
            return [];
        }
        foreach ($definitions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $zoneId = $row['zoneId'] ?? '';
            if (is_string($zoneId) && $zoneId !== '' && !in_array($zoneId, $ids, true)) {
                $ids[] = $zoneId;
            }
        }

        return $ids;
    }
}
