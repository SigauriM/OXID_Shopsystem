<?php

declare(strict_types=1);

namespace OxidShipping\Module\Logging;

use OxidShipping\Engine\Result\Quote;
use OxidShipping\Engine\Result\QuoteEncoder;
use OxidShipping\Engine\Result\QuoteResult;
use OxidShipping\Engine\Result\ValidationFailed;
use OxidShipping\Module\Quote\ShopQuote;
use OxidShipping\Module\Quote\ShopQuoteStatus;
use Psr\Log\LoggerInterface;

final class QuoteTraceLogger
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    private string $filePath;

    public function __construct(
        private LoggerInterface $logger,
        private string $moduleVersion = '1.0.0',
        ?string $filePath = null,
    ) {
        $this->filePath = $filePath ?? dirname(__DIR__, 4) . '/source/log/shipping-quotes.ndjson';
    }

    public function write(ShopQuote $shopQuote, QuoteResult $result, string $configHash): void
    {
        if ($shopQuote->status === ShopQuoteStatus::NeedAddress) {
            return;
        }

        if (
            $shopQuote->status === ShopQuoteStatus::Invalid
            && !$result instanceof ValidationFailed
        ) {
            return;
        }

        try {
            $payload = $this->payload($shopQuote, $result, $configHash);
            $this->append($payload);
        } catch (\Throwable) {
            $this->logger->error('Shipping quote trace could not be written.');
        }
    }

    private function payload(ShopQuote $shopQuote, QuoteResult $result, string $configHash): string
    {
        $envelope = [
            'at' => gmdate('Y-m-d\TH:i:s\Z'),
            'configHash' => $configHash,
            'moduleVersion' => $this->moduleVersion,
            'status' => $shopQuote->status->value,
        ];

        if ($result instanceof Quote) {
            $encoded = QuoteEncoder::encode($result);
            $quote = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($quote)) {
                throw new \RuntimeException('Encoded quote must decode to an object.');
            }
            $envelope['quote'] = $quote;
        } elseif ($result instanceof ValidationFailed) {
            $errors = [];
            foreach ($result->errors as $error) {
                $errors[] = [
                    'code' => $error->code->value,
                    'field' => $error->field,
                ];
            }
            $envelope['errors'] = $errors;
        }

        return json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function append(string $line): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Shipping quote log directory cannot be created.');
        }

        if (is_file($this->filePath) && filesize($this->filePath) > self::MAX_BYTES) {
            $rotated = $this->filePath . '.' . gmdate('YmdHis');
            if (!rename($this->filePath, $rotated)) {
                throw new \RuntimeException('Shipping quote log could not be rotated.');
            }
        }

        $written = file_put_contents($this->filePath, $line . "\n", FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('Shipping quote log could not be written.');
        }
    }
}
