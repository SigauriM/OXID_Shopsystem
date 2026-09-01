<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Domain\Rejected;
use OxidShipping\Engine\Domain\RejectReason;
use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\QuoteEngine;
use OxidShipping\Engine\Tests\Support\TestConfig;
use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteResult;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Engine\Validation\InputLimits;
use OxidShipping\Engine\Validation\ValidationError;
use OxidShipping\Engine\Validation\ValidationErrorCode;
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
        $this->assertContains(ValidationErrorCode::CartEmpty, $this->codes($result));
        foreach ($result->errors as $error) {
            $this->assertDoesNotMatchRegularExpression('/zone|region|unknown/i', $error->field);
            $this->assertDoesNotMatchRegularExpression('/zone|region|unknown/i', $error->code->value);
        }
    }

    public function testWeightZeroIsValidationFailed(): void
    {
        $result = $this->quoteLine(weightGrams: 0);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::WeightNotPositive, $this->codes($result));
    }

    public function testWeightNegativeIsValidationFailed(): void
    {
        $result = $this->quoteLine(weightGrams: -1);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::WeightNotPositive, $this->codes($result));
    }

    public function testZeroDimensionIsValidationFailed(): void
    {
        $result = $this->quoteLine(heightMm: 0);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::DimensionNotPositive, $this->codes($result));
    }

    public function testNegativeDimensionIsValidationFailed(): void
    {
        $result = $this->quoteLine(widthMm: -5);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::DimensionNotPositive, $this->codes($result));
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
        $this->assertContains(ValidationErrorCode::DimensionTooLong, $this->codes($result));
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
        $this->assertContains(ValidationErrorCode::WeightTooHeavy, $this->codes($result));
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
        $this->assertContains(ValidationErrorCode::QuantityTooSmall, $this->codes($result));
    }

    public function testQuantityNegativeIsValidationFailed(): void
    {
        $result = $this->quoteLine(quantity: -1);

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::QuantityTooSmall, $this->codes($result));
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
        $this->assertContains(ValidationErrorCode::QuantityTooLarge, $this->codes($result));
    }

    public function testTooManyLinesEmitsCartErrorThenValidatesFirstHundred(): void
    {
        $lines = [];
        for ($i = 0; $i < InputLimits::MAX_LINES + 1; $i++) {
            $weightGrams = ($i === 0 || $i === InputLimits::MAX_LINES) ? 0 : 1;
            $lines[] = new OrderLine('line-' . $i, 100, 100, 100, $weightGrams, 1);
        }

        $result = $this->engine->quote($this->request(lines: $lines));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertSame(ValidationErrorCode::CartTooManyLines, $result->errors[0]->code);
        $this->assertSame('lines', $result->errors[0]->field);

        $fields = array_map(
            static fn (ValidationError $error): string => $error->field,
            $result->errors,
        );
        $this->assertContains('lines.0.weightGrams', $fields);
        $this->assertNotContains('lines.' . InputLimits::MAX_LINES . '.weightGrams', $fields);

        foreach ($fields as $field) {
            if (preg_match('/^lines\.(\d+)/', $field, $matches) === 1) {
                $this->assertLessThan(InputLimits::MAX_LINES, (int) $matches[1]);
            }
        }
    }

    public function testEmptyPostalCodeIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(postalCode: ''));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::PostalCodeEmpty, $this->codes($result));
    }

    public function testWhitespacePostalCodeIsValidationFailedNotRegionReject(): void
    {
        $result = $this->engine->quote($this->request(postalCode: '   '));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::PostalCodeEmpty, $this->codes($result));
        foreach ($result->errors as $error) {
            $this->assertDoesNotMatchRegularExpression('/zone|region|unknown/i', $error->code->value);
        }
    }

    public function testEmptyCountryIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(country: ''));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::CountryEmpty, $this->codes($result));
    }

    public function testSingleLetterCountryIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(country: 'D'));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::CountryInvalid, $this->codes($result));
    }

    public function testCountryWordIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(country: 'GERMANY'));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::CountryInvalid, $this->codes($result));
    }

    public function testAlpha3CountryIsValidationFailedNotRegionReject(): void
    {
        $result = $this->engine->quote($this->request(country: 'DEU'));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::CountryInvalid, $this->codes($result));
        foreach ($result->errors as $error) {
            $this->assertDoesNotMatchRegularExpression('/zone|region|unknown/i', $error->code->value);
        }
    }

    public function testSwitzerlandPassesValidationOnThisStep(): void
    {
        $result = $this->engine->quote($this->request(country: 'CH'));

        $this->assertInstanceOf(Quote::class, $result);
        $this->assertSame('CH', $result->snapshot->country);
        $this->assertInstanceOf(Rejected::class, $result->destination);
        $this->assertSame(RejectReason::CountryNotServed, $result->destination->reason);
    }

    public function testGarbageCountryIsValidationFailed(): void
    {
        $result = $this->engine->quote($this->request(country: 'DE1'));

        $this->assertInstanceOf(ValidationFailed::class, $result);
        $this->assertContains(ValidationErrorCode::CountryInvalid, $this->codes($result));
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
        $this->assertContains(ValidationErrorCode::DimensionNotPositive, $codes);
        $this->assertContains(ValidationErrorCode::WeightNotPositive, $codes);
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
            config: TestConfig::tariff(),
        );
    }

    private function quoteLine(
        int $lengthMm = 100,
        int $widthMm = 100,
        int $heightMm = 100,
        int $weightGrams = 1,
        int $quantity = 1,
    ): QuoteResult {
        return $this->engine->quote($this->request(
            lengthMm: $lengthMm,
            widthMm: $widthMm,
            heightMm: $heightMm,
            weightGrams: $weightGrams,
            quantity: $quantity,
        ));
    }

    /**
     * @return list<ValidationErrorCode>
     */
    private function codes(ValidationFailed $failed): array
    {
        return array_map(
            static fn (ValidationError $error): ValidationErrorCode => $error->code,
            $failed->errors,
        );
    }
}
