<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core;

use GetPayin\Paylink\Core\Exceptions\PaylinkConfigException;
use GetPayin\Paylink\Core\Http\CurlTransport;
use GetPayin\Paylink\Core\Http\RequestExecutor;
use GetPayin\Paylink\Core\Http\Transport;
use GetPayin\Paylink\Core\Resources\Cards;
use GetPayin\Paylink\Core\Resources\Invoices;
use GetPayin\Paylink\Core\Resources\Payments;
use GetPayin\Paylink\Core\Resources\Recurring;
use GetPayin\Paylink\Core\Resources\Vcc;
use Psr\Log\LoggerInterface;

/**
 * The PayLink integration client. Construct once with your integration's
 * `publicToken` and secret `hashToken`, then call the resource namespaces:
 *
 * - `invoices` — create checkouts.
 * - `payments` — void, refund, settle, reverse authorization, and check status.
 * - `vcc` — server-to-server raw-card charges.
 * - `cards` — tokenize, charge, and revoke stored cards.
 * - `recurring` — create and manage recurring mandates.
 * - `webhooks` — verify inbound webhook signatures.
 *
 * The secret `hashToken` is server-side only; never expose it to a browser or
 * mobile app.
 *
 * @example
 * $paylink = new PaylinkClient(publicToken: $pub, hashToken: $secret);
 * $checkout = $paylink->invoices->create([...]);
 */
final class PaylinkClient
{
    public readonly Invoices $invoices;

    public readonly Payments $payments;

    public readonly Vcc $vcc;

    public readonly Cards $cards;

    public readonly Recurring $recurring;

    public readonly Webhooks $webhooks;

    /**
     * @param  string  $publicToken  Integration public token; sent as `token` on every request.
     * @param  string  $hashToken  Integration signing secret; server-side only.
     * @param  string  $baseUrl  API base URL; trailing slashes are trimmed.
     * @param  int  $timeoutMs  Per-attempt timeout in milliseconds.
     * @param  int  $maxRetries  Retries for replay-safe requests (0 disables retries).
     * @param  Transport|null  $transport  HTTP transport; defaults to {@see CurlTransport}.
     * @param  int  $retryBaseDelayMs  Base delay for exponential backoff.
     * @param  LoggerInterface|null  $logger  PSR-3 logger for retries and rejected webhooks; in Laravel pass `Log::channel('paylink')`. Defaults to a no-op logger.
     *
     * @throws PaylinkConfigException When configuration is invalid.
     */
    public function __construct(
        string $publicToken,
        string $hashToken,
        string $baseUrl = Config::DEFAULT_BASE_URL,
        int $timeoutMs = Config::DEFAULT_TIMEOUT_MS,
        int $maxRetries = Config::DEFAULT_MAX_RETRIES,
        ?Transport $transport = null,
        int $retryBaseDelayMs = Config::DEFAULT_RETRY_BASE_DELAY_MS,
        ?LoggerInterface $logger = null,
    ) {
        $config = Config::resolve(
            $publicToken,
            $hashToken,
            $baseUrl,
            $timeoutMs,
            $maxRetries,
            $transport,
            $retryBaseDelayMs,
            $logger,
        );

        $http = new RequestExecutor($config);

        $this->invoices = new Invoices($config, $http);
        $this->payments = new Payments($config, $http);
        $this->vcc = new Vcc($config, $http);
        $this->cards = new Cards($config, $http);
        $this->recurring = new Recurring($config, $http);
        $this->webhooks = new Webhooks($config);
    }

    /** Accessor for {@see $invoices}; enables method-style and facade access. */
    public function invoices(): Invoices
    {
        return $this->invoices;
    }

    /** Accessor for {@see $payments}; enables method-style and facade access. */
    public function payments(): Payments
    {
        return $this->payments;
    }

    /** Accessor for {@see $vcc}; enables method-style and facade access. */
    public function vcc(): Vcc
    {
        return $this->vcc;
    }

    /** Accessor for {@see $cards}; enables method-style and facade access. */
    public function cards(): Cards
    {
        return $this->cards;
    }

    /** Accessor for {@see $recurring}; enables method-style and facade access. */
    public function recurring(): Recurring
    {
        return $this->recurring;
    }

    /** Accessor for {@see $webhooks}; enables method-style and facade access. */
    public function webhooks(): Webhooks
    {
        return $this->webhooks;
    }
}
