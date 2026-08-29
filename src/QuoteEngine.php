<?php

declare(strict_types=1);

namespace OxidShipping\Engine;

use OxidShipping\Engine\Domain\AddressShape;
use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\Result\InputSnapshot;
use OxidShipping\Engine\Result\PieceRejection;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteResult;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Engine\Validation\InputValidator;
use OxidShipping\Engine\Zone\ZoneResolver;

final readonly class QuoteEngine
{
    public function __construct(
        private InputValidator $validator = new InputValidator(),
        private PieceFactory $pieceFactory = new PieceFactory(),
        private ZoneResolver $zoneResolver = new ZoneResolver(),
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

        return Quote::fromPipeline(
            $pieces,
            $destination,
            $rejections,
            new InputSnapshot(
                lines: $request->lines,
                postalCode: $postalCode,
                country: $country,
                indoor: $request->indoor,
            ),
            $request->config->version,
        );
    }
}
