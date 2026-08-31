<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Result;

use OxidShipping\Engine\Classification\ClassifiedPiece;
use OxidShipping\Engine\Domain\KnownZone;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Grouping\Shipment;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Measurement\MeasuredPiece;
use OxidShipping\Engine\Tariff\PricedShipment;
use OxidShipping\Engine\Tariff\PriceLine;

final class QuoteEncoder
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private function __construct()
    {
    }

    public static function encode(Quote $quote): string
    {
        return json_encode(self::sortKeys(self::document($quote)), self::JSON_FLAGS);
    }

    /**
     * @return array<string, mixed>
     */
    private static function document(Quote $quote): array
    {
        $classified = [];
        foreach ($quote->classified as $item) {
            $classified[] = self::classifiedPiece($item);
        }

        $rejections = [];
        foreach ($quote->rejections as $rejection) {
            $rejections[] = [
                'lineId' => $rejection->lineId,
                'lineIndex' => $rejection->lineIndex,
                'pieceIndex' => $rejection->pieceIndex,
                'reason' => $rejection->rejected->reason->value,
            ];
        }

        $shipments = [];
        foreach ($quote->shipments as $priced) {
            $shipments[] = self::pricedShipment($priced);
        }

        $trace = [];
        foreach ($quote->trace as $line) {
            $trace[] = self::priceLine($line);
        }

        return [
            'classified' => $classified,
            'configHash' => $quote->configHash,
            'configVersion' => $quote->configVersion,
            'destination' => self::destination($quote->destination),
            'rejections' => $rejections,
            'shipments' => $shipments,
            'snapshot' => self::snapshot($quote->snapshot),
            'totalCents' => $quote->totalCents,
            'trace' => $trace,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function destination(KnownZone|Rejected $destination): array
    {
        if ($destination instanceof KnownZone) {
            return [
                'type' => 'known',
                'zoneId' => $destination->zoneId,
            ];
        }

        return [
            'reason' => $destination->reason->value,
            'type' => 'rejected',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function classifiedPiece(ClassifiedPiece $item): array
    {
        return [
            'class' => $item->class->value,
            'piece' => self::measuredPiece($item->piece),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function measuredPiece(MeasuredPiece $piece): array
    {
        return [
            'actualGrams' => $piece->actualGrams,
            'billableGrams' => $piece->billableGrams,
            'dimensions' => [
                'heightMm' => $piece->dimensions->heightMm,
                'lengthMm' => $piece->dimensions->lengthMm,
                'widthMm' => $piece->dimensions->widthMm,
            ],
            'lineId' => $piece->lineId,
            'lineIndex' => $piece->lineIndex,
            'pieceIndex' => $piece->pieceIndex,
            'volumetricGrams' => $piece->volumetricGrams,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function pricedShipment(PricedShipment $priced): array
    {
        $lines = [];
        foreach ($priced->lines as $line) {
            $lines[] = self::priceLine($line);
        }

        return [
            'baseCents' => $priced->baseCents,
            'lines' => $lines,
            'shipment' => self::shipment($priced->shipment),
            'totalCents' => $priced->totalCents,
            'transitDays' => $priced->transitDays,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shipment(Shipment $shipment): array
    {
        $pieces = [];
        foreach ($shipment->pieces as $item) {
            $pieces[] = self::classifiedPiece($item);
        }

        return [
            'class' => $shipment->class->value,
            'indoor' => $shipment->indoor,
            'pieces' => $pieces,
            'zoneId' => $shipment->zoneId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function priceLine(PriceLine $line): array
    {
        return [
            'deltaCents' => $line->deltaCents,
            'explanation' => $line->explanation,
            'lineIndex' => $line->lineIndex,
            'pieceIndex' => $line->pieceIndex,
            'ruleId' => $line->ruleId->value,
            'stage' => $line->stage->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(InputSnapshot $snapshot): array
    {
        $lines = [];
        foreach ($snapshot->lines as $line) {
            $lines[] = self::orderLine($line);
        }

        return [
            'country' => $snapshot->country,
            'indoor' => $snapshot->indoor,
            'lines' => $lines,
            'postalCode' => $snapshot->postalCode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function orderLine(OrderLine $line): array
    {
        return [
            'heightMm' => $line->heightMm,
            'lengthMm' => $line->lengthMm,
            'lineId' => $line->lineId,
            'quantity' => $line->quantity,
            'weightGrams' => $line->weightGrams,
            'widthMm' => $line->widthMm,
        ];
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function sortKeys(array $value): array
    {
        if (array_is_list($value)) {
            $sorted = [];
            foreach ($value as $item) {
                $sorted[] = is_array($item) ? self::sortKeys($item) : $item;
            }

            return $sorted;
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortKeys($item);
            }
        }

        return $value;
    }
}
