<?php

declare(strict_types=1);

namespace OxidShipping\Engine\Tests;

use OxidShipping\Engine\Input\OrderLine;
use OxidShipping\Engine\Input\QuoteRequest;
use OxidShipping\Engine\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class QuoteRequestTest extends TestCase
{
    public function testAssociativeLinesAreProgrammerError(): void
    {
        $line = new OrderLine('line-1', 100, 100, 100, 1, 1);

        try {
            $request = new QuoteRequest(
                lines: [1 => $line],
                postalCode: '01067',
                country: 'DE',
                indoor: false,
                config: TestConfig::tariff(),
            );
            $this->fail('Expected InvalidArgumentException, got ' . count($request->lines) . ' lines.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('QuoteRequest lines must be a list.', $exception->getMessage());
        }
    }

    public function testNonOrderLineIsProgrammerError(): void
    {
        try {
            $request = new QuoteRequest(
                lines: ['not-a-line'],
                postalCode: '01067',
                country: 'DE',
                indoor: false,
                config: TestConfig::tariff(),
            );
            $this->fail('Expected InvalidArgumentException, got ' . count($request->lines) . ' lines.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                'QuoteRequest lines[0] must be an OrderLine.',
                $exception->getMessage(),
            );
        }
    }
}