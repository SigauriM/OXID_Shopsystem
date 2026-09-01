<?php

declare(strict_types=1);

namespace OxidShipping\Module\Application\Component\Widget;

use OxidEsales\Eshop\Application\Component\Widget\WidgetController;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidShipping\Engine\Validation\InputLimits;
use OxidShipping\Module\Adapter\ArticleCartLine;
use OxidShipping\Module\Adapter\SingleArticleSource;
use OxidShipping\Module\Quote\QuoteChannel;
use OxidShipping\Module\Quote\QuoteFacade;
use OxidShipping\Module\Quote\ShopQuote;
use OxidShipping\Module\Quote\ShopQuoteStatus;
use OxidShipping\Module\Quote\StorefrontCountry;

final class ShippingBox extends WidgetController
{
    private const MAX_ANID_LENGTH = 32;

    private const MAX_POSTAL_LENGTH = 10;

    private const MAX_HITS = 30;

    private const WINDOW_SECONDS = 60;

    private const SESSION_KEY = 'oxidshipping_widget_hits';

    /**
     * @var array<string, int>
     */
    protected $_aComponentNames = ['oxcmp_cur' => 1];

    public function __construct()
    {
        parent::__construct();
        $this->setTemplateName('@oxidshipping/widget/shippingbox.html.twig');
    }

    public function render()
    {
        Registry::getUtils()->setHeader('X-Robots-Tag: noindex');
        $template = parent::render();
        $this->_aViewData['quote'] = $this->quote();

        return $template;
    }

    private function quote(): ShopQuote
    {
        $this->ensureSession();
        if ($this->throttled()) {
            return $this->message(ShopQuoteStatus::Invalid, 'OXIDSHIPPING_TRY_LATER');
        }

        $article = $this->article();
        if ($article === null) {
            return $this->message(ShopQuoteStatus::Invalid, 'OXIDSHIPPING_INVALID');
        }

        $postal = $this->postalCode();
        if ($postal === null) {
            return $this->message(ShopQuoteStatus::NeedAddress, 'OXIDSHIPPING_BOX_HINT');
        }
        if ($postal === false) {
            return $this->message(ShopQuoteStatus::NotPossible, 'OXIDSHIPPING_NOT_POSSIBLE_PLZ');
        }

        $quantity = $this->quantity();
        if ($quantity === null) {
            return $this->message(ShopQuoteStatus::Invalid, 'OXIDSHIPPING_INVALID');
        }

        return $this->facade()->quote(
            new SingleArticleSource(
                ArticleCartLine::from($article, (float) $quantity),
                $postal,
                StorefrontCountry::default(),
            ),
            QuoteChannel::ProductPage,
        );
    }

    private function article(): ?Article
    {
        $anid = $this->requestString('anid');
        if ($anid === '' || strlen($anid) > self::MAX_ANID_LENGTH) {
            return null;
        }

        $article = oxNew(Article::class);
        if (!$article->load($anid) || !$article->isVisible() || $article->isNotBuyable()) {
            return null;
        }

        return $article;
    }

    /**
     * @return string|false|null Null = empty (need PLZ), false = garbage, string = digits.
     */
    private function postalCode(): string|false|null
    {
        $raw = Registry::getRequest()->getRequestParameter('plz', '');
        if (!is_string($raw) && !is_int($raw)) {
            return false;
        }
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }
        if (strlen($value) > self::MAX_POSTAL_LENGTH || preg_match('/^\d+$/', $value) !== 1) {
            return false;
        }

        return $value;
    }

    private function quantity(): ?int
    {
        $raw = Registry::getRequest()->getRequestParameter('am', '1');
        if ($raw === null || $raw === '') {
            return 1;
        }
        if (is_int($raw)) {
            return ($raw >= 1 && $raw <= InputLimits::MAX_QUANTITY) ? $raw : null;
        }
        if (!is_string($raw)) {
            return null;
        }
        $value = trim($raw);
        if ($value === '') {
            return 1;
        }
        if (preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }
        $quantity = (int) $value;
        if ($quantity < 1 || $quantity > InputLimits::MAX_QUANTITY) {
            return null;
        }

        return $quantity;
    }

    private function throttled(): bool
    {
        $session = Registry::getSession();
        $now = time();
        $hits = $session->getVariable(self::SESSION_KEY);
        if (!is_array($hits)) {
            $hits = [];
        }
        $fresh = [];
        foreach ($hits as $timestamp) {
            if (!is_numeric($timestamp)) {
                continue;
            }
            $at = (int) $timestamp;
            if ($at > $now - self::WINDOW_SECONDS) {
                $fresh[] = $at;
            }
        }
        if (count($fresh) >= self::MAX_HITS) {
            $session->setVariable(self::SESSION_KEY, $fresh);

            return true;
        }
        $fresh[] = $now;
        $session->setVariable(self::SESSION_KEY, $fresh);

        return false;
    }

    private function ensureSession(): void
    {
        $session = Registry::getSession();
        if ($session->isSessionStarted()) {
            return;
        }
        $session->setForceNewSession();
        $session->start();
    }

    private function requestString(string $name): string
    {
        $raw = Registry::getRequest()->getRequestParameter($name, '');
        if (is_int($raw)) {
            return (string) $raw;
        }
        if (!is_string($raw)) {
            return '';
        }

        return trim($raw);
    }

    private function facade(): QuoteFacade
    {
        $service = ContainerFacade::get(QuoteFacade::class);
        if (!$service instanceof QuoteFacade) {
            throw new \RuntimeException('QuoteFacade is not in the shop container.');
        }

        return $service;
    }

    private function message(ShopQuoteStatus $status, string $langKey): ShopQuote
    {
        return new ShopQuote($status, 0, [], $langKey);
    }
}
