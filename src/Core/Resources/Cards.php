<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Resources;

use GetPayin\Paylink\Core\Config;
use GetPayin\Paylink\Core\Http\RequestExecutor;
use GetPayin\Paylink\Core\Http\RequestOptions;
use GetPayin\Paylink\Core\Internal\EndpointSpec;
use GetPayin\Paylink\Core\Internal\FieldOrders;
use GetPayin\Paylink\Core\Internal\SignRequest;
use GetPayin\Paylink\Core\PaylinkClient;

/**
 * The `cards` resource: vault a card as a reusable token, charge a stored token,
 * and revoke it. Reached via {@see PaylinkClient::$cards}.
 */
final class Cards
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestExecutor $http,
    ) {}

    /**
     * Vault a card and return a reusable token (`POST /api/v2/integration/tokens/card`).
     *
     * @param  array<string, mixed>  $params
     * @return array{token: string, card: array{brand: string|null, last4: string|null, expMonth: int|null, expYear: int|null}, status: string}
     */
    public function tokenize(array $params, ?string $idempotencyKey = null): array
    {
        $data = $this->send(FieldOrders::cardTokenize(), $params, $idempotencyKey);
        $card = isset($data['card']) && is_array($data['card']) ? $data['card'] : [];

        return [
            'token' => (string) $data['token'],
            'card' => [
                'brand' => isset($card['brand']) ? (string) $card['brand'] : null,
                'last4' => isset($card['last4']) ? (string) $card['last4'] : null,
                'expMonth' => isset($card['exp_month']) ? (int) $card['exp_month'] : null,
                'expYear' => isset($card['exp_year']) ? (int) $card['exp_year'] : null,
            ],
            'status' => (string) $data['status'],
        ];
    }

    /**
     * Charge a previously vaulted card (`POST /api/v2/integration/tokens/charge`).
     *
     * @param  array<string, mixed>  $params
     * @return array{invoiceId: int, invoiceNumber: string, amount: int, currency: string, paidStatus: string, cardToken: string}
     */
    public function charge(array $params, ?string $idempotencyKey = null): array
    {
        $data = $this->send(FieldOrders::cardCharge(), $params, $idempotencyKey);

        return [
            'invoiceId' => (int) $data['invoice_id'],
            'invoiceNumber' => (string) $data['invoice_number'],
            'amount' => (int) $data['amount'],
            'currency' => (string) $data['currency'],
            'paidStatus' => (string) $data['paid_status'],
            'cardToken' => (string) $data['card_token'],
        ];
    }

    /**
     * Revoke a vaulted card token (`POST /api/v2/integration/tokens/revoke`). Idempotent.
     *
     * @param  array<string, mixed>  $params
     * @return array{message: string}
     */
    public function revoke(array $params, ?string $idempotencyKey = null): array
    {
        $data = $this->send(FieldOrders::cardRevoke(), $params, $idempotencyKey);

        return ['message' => isset($data['message']) ? (string) $data['message'] : 'Token revoked.'];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function send(EndpointSpec $spec, array $params, ?string $idempotencyKey): array
    {
        $body = SignRequest::build($spec, $params, $this->config->publicToken, $this->config->hashToken());

        return $this->http->object(new RequestOptions(
            method: 'POST',
            path: $spec->path,
            body: $body,
            idempotencyKey: $idempotencyKey,
        ));
    }
}
