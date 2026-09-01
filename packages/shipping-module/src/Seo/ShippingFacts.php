<?php

declare(strict_types=1);

namespace OxidShipping\Module\Seo;

use OxidShipping\Engine\Domain\ZoneDefinition;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;
use OxidShipping\Module\Adapter\SingleArticleSource;
use OxidShipping\Module\Mapping\CartLine;
use OxidShipping\Module\Quote\QuoteChannel;
use OxidShipping\Module\Quote\QuoteFacade;
use OxidShipping\Module\Quote\StorefrontCountry;
use OxidShipping\Module\Tariff\TariffProvider;
use Psr\Log\LoggerInterface;

final class ShippingFacts
{
    private const MAX_POSTAL_CODES = 200;

    /**
     * @var array<string, list<ShippingFact>>
     */
    private array $cache = [];

    public function __construct(
        private QuoteFacade $quotes,
        private TariffProvider $tariff,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<ShippingFact>
     */
    public function for(CartLine $line): array
    {
        $config = $this->tariff->get();
        $key = $line->articleNumber . "\0" . TariffDocument::hash($config);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $facts = $this->collect($line, $config);
        $this->cache[$key] = $facts;

        return $facts;
    }

    /**
     * @return list<ShippingFact>
     */
    private function collect(CartLine $line, TariffConfig $config): array
    {
        /** @var array<string, array{country: string, codes: list<string>}> $byZone */
        $byZone = [];
        foreach ($config->zones->directory->postalEntries() as $entry) {
            if (!in_array($entry->country, StorefrontCountry::CODES, true)) {
                continue;
            }
            if (!isset($byZone[$entry->zoneId])) {
                $byZone[$entry->zoneId] = [
                    'country' => $entry->country,
                    'codes' => [],
                ];
            }
            $byZone[$entry->zoneId]['codes'][] = $entry->postalCode;
        }

        $definitions = $config->zones->directory->definitions();
        usort(
            $definitions,
            static fn (ZoneDefinition $a, ZoneDefinition $b): int => $a->zoneId <=> $b->zoneId,
        );

        $facts = [];
        foreach ($definitions as $definition) {
            $fact = $this->factForZone($line, $definition, $byZone);
            if ($fact !== null) {
                $facts[] = $fact;
            }
        }

        return $facts;
    }

    /**
     * @param array<string, array{country: string, codes: list<string>}> $byZone
     */
    private function factForZone(CartLine $line, ZoneDefinition $definition, array $byZone): ?ShippingFact
    {
        if ($definition->forbidden) {
            return null;
        }

        $bucket = $byZone[$definition->zoneId] ?? null;
        if ($bucket === null) {
            return null;
        }

        $codes = $bucket['codes'];
        sort($codes, SORT_STRING);
        if ($codes === []) {
            return null;
        }
        if (count($codes) > self::MAX_POSTAL_CODES) {
            $this->logger->info('Shipping schema skipped a zone with more than 200 postal codes.');

            return null;
        }

        $quote = $this->quotes->quote(
            new SingleArticleSource($line, $codes[0], $bucket['country']),
            QuoteChannel::ProductPage,
        );
        if (!$quote->isQuoted()) {
            return null;
        }
        if (count($quote->shipments) !== 1) {
            $this->logger->error('Shipping schema skipped a zone with more than one shipment.');

            return null;
        }

        $shipment = $quote->shipments[0];

        return new ShippingFact(
            $definition->zoneId,
            $bucket['country'],
            $codes,
            $shipment->totalCents,
            $shipment->transitDays,
        );
    }
}
