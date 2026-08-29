<?php

declare(strict_types=1);

namespace OxidShipping\Engine;

use OxidShipping\Engine\Input\InputNormalizer;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Measurement\PieceFactory;
use OxidShipping\Engine\Result\InputSnapshot;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteResult;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Engine\Validation\InputValidator;

final readonly class QuoteEngine
{
    public function __construct(
        private InputValidator $validator = new InputValidator(),
        private PieceFactory $pieceFactory = new PieceFactory(),
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

        return new Quote(
            pieces: $this->pieceFactory->expand(
                $request->lines,
                $request->config->volumetricDivisor,
            ),
            snapshot: new InputSnapshot(
                lines: $request->lines,
                postalCode: InputNormalizer::postalCode($request->postalCode),
                country: InputNormalizer::country($request->country),
                indoor: $request->indoor,
            ),
            configVersion: $request->config->version,
        );
    }
}
