<?php

declare(strict_types=1);

namespace OxidShipping\Module\Quote;

use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteResult;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Engine\ShippingClass;
use OxidShipping\Module\Logging\QuoteTraceLogger;
use OxidShipping\Module\Mapping\CartMapper;
use OxidShipping\Module\Mapping\CartSource;
use OxidShipping\Module\Mapping\MappedCart;
use OxidShipping\Module\Mapping\MappingFailed;
use OxidShipping\Module\Tariff\TariffLoadFailed;
use OxidShipping\Module\Tariff\TariffProvider;
use Psr\Log\LoggerInterface;

final class QuoteFacade
{
    private const INDOOR = false;

    private ?string $cachedFingerprint = null;

    private ?ShopQuote $cachedQuote = null;

    public function __construct(
        private QuoteEngine $engine,
        private TariffProvider $tariffSource,
        private CartMapper $cartMapper,
        private LoggerInterface $logger,
        private QuoteTraceLogger $traceLogger,
    ) {
    }

    public function quote(CartSource $source): ShopQuote
    {
        try {
            return $this->quoteOrFail($source);
        } catch (TariffLoadFailed) {
            $this->logger->error('Shipping tariff could not be loaded.');

            return $this->invalid();
        } catch (\Throwable) {
            $this->logger->error('Shipping quote could not be calculated.');

            return $this->invalid();
        }
    }

    private function quoteOrFail(CartSource $source): ShopQuote
    {
        $postalCode = $source->postalCode();
        $country = $source->countryIso();
        if (trim($postalCode) === '' || trim($country) === '') {
            return $this->needAddress();
        }

        $mapped = $this->cartMapper->map($source->lines(), $postalCode, $country);
        if ($mapped instanceof MappingFailed) {
            $this->logger->error('Shipping cart mapping failed.');

            return $this->invalid();
        }

        $config = $this->tariffSource->get();
        $fingerprint = $this->fingerprint($mapped, $config);
        if ($this->cachedFingerprint === $fingerprint && $this->cachedQuote !== null) {
            return $this->cachedQuote;
        }

        $result = $this->engine->quote(new QuoteRequest(
            $mapped->lines,
            $mapped->postalCode,
            $mapped->country,
            self::INDOOR,
            $config,
        ));

        $shopQuote = $this->toShopQuote($result);
        $this->traceLogger->write($shopQuote, $result, TariffDocument::hash($config));
        $this->cachedFingerprint = $fingerprint;
        $this->cachedQuote = $shopQuote;

        return $shopQuote;
    }

    private function toShopQuote(QuoteResult $result): ShopQuote
    {
        if ($result instanceof ValidationFailed) {
            return $this->invalid();
        }

        if (!$result instanceof Quote) {
            return $this->invalid();
        }

        if ($result->destination instanceof Rejected) {
            return new ShopQuote(
                ShopQuoteStatus::NotPossible,
                0,
                [],
                $this->rejectLangKey($result->destination->reason),
            );
        }

        if ($result->totalCents < 1) {
            return $this->invalid();
        }

        $shipments = [];
        foreach ($result->shipments as $priced) {
            $shipment = $priced->shipment;
            $shipments[] = new ShopShipment(
                $this->classLangKey($shipment->class),
                $shipment->zoneId,
                $shipment->indoor,
                $priced->totalCents,
                $priced->transitDays,
            );
        }

        return new ShopQuote(
            ShopQuoteStatus::Quoted,
            $result->totalCents,
            $shipments,
            '',
        );
    }

    private function fingerprint(MappedCart $mapped, TariffConfig $config): string
    {
        $lines = [];
        foreach ($mapped->lines as $line) {
            $lines[] = [
                $line->lineId,
                $line->lengthMm,
                $line->widthMm,
                $line->heightMm,
                $line->weightGrams,
                $line->quantity,
            ];
        }

        return hash('sha256', json_encode(
            [
                'country' => $mapped->country,
                'indoor' => self::INDOOR,
                'lines' => $lines,
                'postalCode' => $mapped->postalCode,
                'tariffHash' => TariffDocument::hash($config),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    private function needAddress(): ShopQuote
    {
        return new ShopQuote(
            ShopQuoteStatus::NeedAddress,
            0,
            [],
            'OXIDSHIPPING_NEED_ADDRESS',
        );
    }

    private function invalid(): ShopQuote
    {
        return new ShopQuote(
            ShopQuoteStatus::Invalid,
            0,
            [],
            'OXIDSHIPPING_INVALID',
        );
    }

    private function rejectLangKey(RejectReason $reason): string
    {
        return match ($reason) {
            RejectReason::CountryNotServed => 'OXIDSHIPPING_NOT_POSSIBLE_COUNTRY',
            RejectReason::UnknownZone => 'OXIDSHIPPING_NOT_POSSIBLE_PLZ',
            RejectReason::ZoneForbidden => 'OXIDSHIPPING_NOT_POSSIBLE_ZONE',
        };
    }

    private function classLangKey(ShippingClass $class): string
    {
        return match ($class) {
            ShippingClass::Paket => 'OXIDSHIPPING_CLASS_PAKET',
            ShippingClass::Sperrgut => 'OXIDSHIPPING_CLASS_SPERRGUT',
            ShippingClass::Spedition => 'OXIDSHIPPING_CLASS_SPEDITION',
        };
    }
}
