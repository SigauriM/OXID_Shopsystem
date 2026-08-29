<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\ValidationFailed;
use PHPUnit\Framework\TestCase;

final class PostalCodeTest extends TestCase
{
    public function testDresdenPostalCodeKeepsLeadingZero(): void
    {
        $engine = new QuoteEngine();
        $result = $engine->quote(new QuoteRequest(
            lines: [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            postalCode: '01067',
            country: 'DE',
            indoor: false,
            config: new TariffConfig('test-2026'),
        ));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertSame('01067', $result->snapshot->postalCode);
    }

    public function testPostalCodeIsTrimmedInSnapshot(): void
    {
        $engine = new QuoteEngine();
        $result = $engine->quote(new QuoteRequest(
            lines: [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            postalCode: '  01067  ',
            country: 'DE',
            indoor: false,
            config: new TariffConfig('test-2026'),
        ));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertSame('01067', $result->snapshot->postalCode);
    }

    public function testAustrianFourDigitPostalCodePassesShapeCheck(): void
    {
        $engine = new QuoteEngine();
        $result = $engine->quote(new QuoteRequest(
            lines: [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            postalCode: '1010',
            country: 'AT',
            indoor: false,
            config: new TariffConfig('test-2026'),
        ));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertSame('1010', $result->snapshot->postalCode);
    }

    public function testPostalCodeWithMarkupIsValidationFailed(): void
    {
        $engine = new QuoteEngine();
        $result = $engine->quote(new QuoteRequest(
            lines: [new OrderLine('line-1', 100, 100, 100, 1, 1)],
            postalCode: '01067<script>',
            country: 'DE',
            indoor: false,
            config: new TariffConfig('test-2026'),
        ));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertSame('postal_code_invalid', $result->errors[0]->code);
    }
}