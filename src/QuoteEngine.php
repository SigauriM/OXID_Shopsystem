<?php

declare(strict_types=1);

namespace OxidShipping\Engine;

use OxidShipping\Engine\Classification\PieceClassifier;
use OxidShipping\Engine\Domain\AddressShape;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Grouping\GroupablePiece;
use OxidShipping\Engine\Grouping\ShipmentGrouper;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\OrderRules\OrderWeightSpeditionOverride;
use OxidShipping\Engine\Result\InputSnapshot;
use OxidShipping\Engine\Result\PieceRejection;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteResult;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Engine\Tariff\ShipmentPricer;
use OxidShipping\Engine\Validation\InputValidator;
use OxidShipping\Engine\Zone\ZoneResolver;

final readonly class QuoteEngine
{
    public function __construct(
        private InputValidator $validator = new InputValidator(),
        private PieceFactory $pieceFactory = new PieceFactory(),
        private ZoneResolver $zoneResolver = new ZoneResolver(),
        private PieceClassifier $pieceClassifier = new PieceClassifier(),
        private OrderWeightSpeditionOverride $orderWeightSpeditionOverride = new OrderWeightSpeditionOverride(),
        private ShipmentGrouper $shipmentGrouper = new ShipmentGrouper(),
        private ShipmentPricer $shipmentPricer = new ShipmentPricer(),
    ) {
        if (PHP_INT_SIZE !== 8) {
            throw new \RuntimeException('Shipping engine requires 64-bit PHP.');
        }
    }

    public function quote(QuoteRequest $request): QuoteResult
    {
        $errors = $this->validator->validate($request);
        if ($errors !== []) {
            return new ValidationFailed($errors);
        }

        $configHash = TariffDocument::hash($request->config);

        $postalCode = AddressShape::postalCode($request->postalCode);
        $country = AddressShape::country($request->country);

        $pieces = $this->pieceFactory->expand(
            $request->lines,
            $request->config->volumetricDivisor,
        );

        $destination = $this->zoneResolver->resolve(
            $postalCode,
            $country,
            $request->config->zones,
        );

        $rejections = [];
        if ($destination instanceof Rejected) {
            foreach ($pieces as $piece) {
                $rejections[] = new PieceRejection(
                    $piece->lineId,
                    $piece->lineIndex,
                    $piece->pieceIndex,
                    $destination,
                );
            }
        }

        $classified = [];
        $priced = [];
        if (!$destination instanceof Rejected) {
            $classified = $this->pieceClassifier->classifyAll(
                $pieces,
                $request->config->classification,
            );
            $classified = $this->orderWeightSpeditionOverride->apply(
                $classified,
                $request->config->orderWeightSpeditionThreshold,
            );
            $groupable = [];
            foreach ($classified as $item) {
                $groupable[] = new GroupablePiece(
                    $item,
                    $destination->zoneId,
                    $request->indoor,
                );
            }
            $shipments = $this->shipmentGrouper->group($groupable);
            $priced = $this->shipmentPricer->priceAll(
                $shipments,
                $request->config->rates,
            );
        }

        return Quote::fromPipeline(
            $pieces,
            $destination,
            $rejections,
            $classified,
            $priced,
            new InputSnapshot(
                lines: $request->lines,
                postalCode: $postalCode,
                country: $country,
                indoor: $request->indoor,
            ),
            $request->config->version,
            $configHash,
        );
    }
}
