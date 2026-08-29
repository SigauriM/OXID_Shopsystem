<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Engine\Validation\InputLimits;
use OxidShipping\Engine\Validation\ValidationError;
use PHPUnit\Framework\TestCase;

final class QuoteEngineValidationTest extends TestCase
{
    private QuoteEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new QuoteEngine();
    }

    public function testEmptyCartIsValidationFailedNotRegionReject(): void
    {
        $result = $this->engine->quote($this->request(lines: []));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('cart_empty', $this->codes($result));
        foreach ($result->errors as $error) {
            $this->assertDoesNotMatchRegularExpression('/zone|region|unknown/i', $error->field);
            $this->assertDoesNotMatchRegularExpression('/zone|region|unknown/i', $error->code);
        }
    }

    public function testWeightZeroIsValidationFailed(): void
    {
        $result = $this->quoteLine(weightGrams: 0);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('weight_not_positive', $this->codes($result));
    }

    public function testWeightNegativeIsValidationFailed(): void
    {
        $result = $this->quoteLine(weightGrams: -1);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('weight_not_positive', $this->codes($result));
    }

    public function testZeroDimensionIsValidationFailed(): void
    {
        $result = $this->quoteLine(heightMm: 0);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('dimension_not_positive', $this->codes($result));
    }

    public function testNegativeDimensionIsValidationFailed(): void
    {
        $result = $this->quoteLine(widthMm: -5);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('dimension_not_positive', $this->codes($result));
    }

    public function testLongestSide15000MillimetresIsAccepted(): void
    {
        $result = $this->quoteLine(lengthMm: 10, widthMm: 15000, heightMm: 10);

        $this->assertInstanceOf(Quote::class, $result);
    }

    public function testTwoSidesAt15000MillimetresAreAccepted(): void
    {
        $result = $this->quoteLine(lengthMm: 15000, widthMm: 15000, heightMm: 1);

        $this->assertInstanceOf(Quote::class, $result);
    }

    public function testLongestSide15001MillimetresIsValidationFailed(): void
    {
        $result = $this->quoteLine(lengthMm: 10, widthMm: 10, heightMm: 15001);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('dimension_too_long', $this->codes($result));
    }

    public function testWeightOneMillionGramsIsAccepted(): void
    {
        $result = $this->quoteLine(weightGrams: 1_000_000);

        $this->assertInstanceOf(Quote::class, $result);
    }

    public function testWeightOneMillionPlusOneGramsIsValidationFailed(): void
    {
        $result = $this->quoteLine(weightGrams: 1_000_001);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('weight_too_heavy', $this->codes($result));
    }

    public function testWeightIsNotMultipliedByQuantity(): void
    {
        $result = $this->quoteLine(weightGrams: 600_000, quantity: 2);

        $this->assertInstanceOf(Quote::class, $result);
    }

    public function testQuantityZeroIsValidationFailed(): void
    {
        $result = $this->quoteLine(quantity: 0);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('quantity_too_small', $this->codes($result));
    }

    public function testQuantityNegativeIsValidationFailed(): void
    {
        $result = $this->quoteLine(quantity: -1);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('quantity_too_small', $this->codes($result));
    }

    public function testQuantityAtMaxIsAccepted(): void
    {
        $result = $this->quoteLine(quantity: InputLimits::MAX_QUANTITY);

        $this->assertInstanceOf(Quote::class, $result);
    }

    public function testQuantityAboveMaxIsValidationFailed(): void
    {
        $result = $this->quoteLine(quantity: InputLimits::MAX_QUANTITY + 1);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('quantity_too_large', $this->codes($result));
    }

    public function testTooManyLinesIsValidationFailed(): void
    {
        $lines = [];
        for ($i = 0; $i < InputLimits::MAX_LINES + 1; $i++) {
            $lines[] = new OrderLine('line-' . $i, 100, 100, 100, 1, 1);
        }

        $result = $this->engine->quote($this->request(lines: $lines));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('cart_too_many_lines', $this->codes($result));
    }

    public function testEmptyPostalCodeIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(postalCode: ''));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('postal_code_empty', $this->codes($result));
    }

    public function testWhitespacePostalCodeIsValidationFailedNotRegionReject(): void
    {
        $result = $this->engine->quote($this->request(postalCode: '   '));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('postal_code_empty', $this->codes($result));
        foreach ($result->errors as $error) {
            $this->assertDoesNotMatchRegularExpression('/zone|region|unknown/i', $error->code);
        }
    }

    public function testEmptyCountryIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(country: ''));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('country_empty', $this->codes($result));
    }

    public function testSingleLetterCountryIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(country: 'D'));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('country_invalid', $this->codes($result));
    }

    public function testCountryWordIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(country: 'GERMANY'));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('country_invalid', $this->codes($result));
    }

    public function testSwitzerlandPassesValidationOnThisStep(): void
    {
        $result = $this->engine->quote($this->request(country: 'CH'));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertSame('CH', $result->snapshot->country);
    }

    public function testGarbageCountryIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(country: 'DE1'));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains('country_invalid', $this->codes($result));
    }

    public function testCountryIsNormalisedToUppercase(): void
    {
        $result = $this->engine->quote($this->request(country: 'de'));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertSame('DE', $result->snapshot->country);
    }

    public function testValidatorCollectsMultipleErrors(): void
    {
        $result = $this->quoteLine(heightMm: 0, weightGrams: 0);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $codes = $this->codes($result);
        $this->assertContains('dimension_not_positive', $codes);
        $this->assertContains('weight_not_positive', $codes);
    }

    /**
     * @param list<OrderLine>|null $lines
     */
    private function request(
        ?array $lines = null,
        string $postalCode = '01067',
        string $country = 'DE',
        int $lengthMm = 100,
        int $widthMm = 100,
        int $heightMm = 100,
        int $weightGrams = 1,
        int $quantity = 1,
    ): QuoteRequest {
        if ($lines === null) {
            $lines = [
                new OrderLine('line-1', $lengthMm, $widthMm, $heightMm, $weightGrams, $quantity),
            ];
        }

        return new QuoteRequest(
            lines: $lines,
            postalCode: $postalCode,
            country: $country,
            indoor: false,
            config: new TariffConfig('test-2026'),
        );
    }

    private function quoteLine(
        int $lengthMm = 100,
        int $widthMm = 100,
        int $heightMm = 100,
        int $weightGrams = 1,
        int $quantity = 1,
    ): Quote|ValidationFailed {
        $result = $this->engine->quote($this->request(
            lengthMm: $lengthMm,
            widthMm: $widthMm,
            heightMm: $heightMm,
            weightGrams: $weightGrams,
            quantity: $quantity,
        ));

        if (!$result instanceof Quote && !$result instanceof ValidationFailed) {
            $this->fail('Quote engine must return Quote or ValidationFailed.');
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function codes(ValidationFailed $failed): array
    {
        return array_map(
            static fn (ValidationError $error): string => $error->code,
            $failed->errors,
        );
    }
}