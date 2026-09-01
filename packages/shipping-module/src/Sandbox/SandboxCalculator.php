<?php

declare(strict_types=1);

namespace OxidShipping\Module\Sandbox;

use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteEncoder;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Module\Tariff\TariffLoadFailed;
use OxidShipping\Module\Tariff\TariffProvider;

final class SandboxCalculator
{
    public function __construct(
        private QuoteEngine $engine,
        private TariffProvider $tariff,
    ) {
    }

    public function calculate(SandboxRequest $request): SandboxView
    {
        try {
            $config = $this->tariff->get();
        } catch (TariffLoadFailed) {
            return SandboxView::unavailable($request->input());
        }

        $result = $this->engine->quote(new QuoteRequest(
            [
                new OrderLine(
                    'sandbox',
                    $request->lengthMm,
                    $request->widthMm,
                    $request->heightMm,
                    $request->weightGrams,
                    $request->quantity,
                ),
            ],
            $request->postalCode,
            $request->country,
            $request->indoor,
            $config,
        ));

        if ($result instanceof ValidationFailed) {
            $errors = [];
            foreach ($result->errors as $error) {
                $errors[] = [
                    'field' => $error->field,
                    'code' => $error->code->value,
                ];
            }

            return SandboxView::validation($request->input(), $errors);
        }

        if (!$result instanceof Quote) {
            return SandboxView::unavailable($request->input());
        }

        return SandboxView::quoted(
            $request->input(),
            self::destination($result),
            self::pieces($result),
            self::shipments($result),
            self::trace($result),
            $result->totalCents,
            $result->configVersion,
            $result->configHash,
            QuoteEncoder::encode($result),
        );
    }

    /**
     * @return array{type: string, zoneId?: string, reason?: string}
     */
    private static function destination(Quote $quote): array
    {
        if ($quote->destination instanceof KnownZone) {
            return [
                'type' => 'known',
                'zoneId' => $quote->destination->zoneId,
            ];
        }

        return [
            'type' => 'rejected',
            'reason' => $quote->destination->reason->value,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pieces(Quote $quote): array
    {
        $rows = [];
        foreach ($quote->classified as $item) {
            $piece = $item->piece;
            $rows[] = [
                'lineIndex' => $piece->lineIndex,
                'pieceIndex' => $piece->pieceIndex,
                'lengthMm' => $piece->dimensions->lengthMm,
                'widthMm' => $piece->dimensions->widthMm,
                'heightMm' => $piece->dimensions->heightMm,
                'girthMm' => $piece->dimensions->girthMm(),
                'actualGrams' => $piece->actualGrams,
                'volumetricGrams' => $piece->volumetricGrams,
                'billableGrams' => $piece->billableGrams,
                'class' => $item->class->value,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function shipments(Quote $quote): array
    {
        $rows = [];
        foreach ($quote->shipments as $priced) {
            $rows[] = [
                'class' => $priced->shipment->class->value,
                'zoneId' => $priced->shipment->zoneId,
                'indoor' => $priced->shipment->indoor,
                'pieceCount' => count($priced->shipment->pieces),
                'baseCents' => $priced->baseCents,
                'totalCents' => $priced->totalCents,
                'transitDays' => $priced->transitDays,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function trace(Quote $quote): array
    {
        $rows = [];
        foreach ($quote->trace as $line) {
            $rows[] = [
                'ruleId' => $line->ruleId->value,
                'stage' => $line->stage->value,
                'deltaCents' => $line->deltaCents,
                'explanation' => $line->explanation,
                'lineIndex' => $line->lineIndex,
                'pieceIndex' => $line->pieceIndex,
            ];
        }

        return $rows;
    }
}
