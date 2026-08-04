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
 * The `payments` resource: operations on an existing invoice — void, refund,
 * settle, reverse authorization, and check status. Reached via
 * {@see PaylinkClient::$payments}.
 */
final class Payments
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestExecutor $http,
    ) {}

    /**
     * Void a paid invoice (`POST /api/integration/void`).
     *
     * @param  array<string, mixed>  $params
     * @return array{invoiceId: int, paidStatus: string, authCode: string|null}
     */
    public function void(array $params, ?string $idempotencyKey = null): array
    {
        return $this->mapPayment($this->send(FieldOrders::paymentVoid(), $params, $idempotencyKey));
    }

    /**
     * Refund a paid invoice, full or partial (`POST /api/integration/refund`).
     * Pass `$idempotencyKey` to make retries safe.
     *
     * @param  array<string, mixed>  $params
     * @return array{invoiceId: int, paidStatus: string, authCode: string|null, refundAmount: int|null}
     */
    public function refund(array $params, ?string $idempotencyKey = null): array
    {
        $data = $this->send(FieldOrders::paymentRefund(), $params, $idempotencyKey);

        return [
            ...$this->mapPayment($data),
            'refundAmount' => isset($data['refund_amount']) ? (int) $data['refund_amount'] : null,
        ];
    }

    /**
     * Capture an authorized invoice (`POST /api/integration/settle`).
     *
     * @param  array<string, mixed>  $params
     * @return array{invoiceId: int, paidStatus: string, authCode: string|null}
     */
    public function settle(array $params, ?string $idempotencyKey = null): array
    {
        return $this->mapPayment($this->send(FieldOrders::paymentSettle(), $params, $idempotencyKey));
    }

    /**
     * Reverse an authorization hold (`POST /api/integration/reverse-authorization`).
     *
     * @param  array<string, mixed>  $params
     * @return array{invoiceId: int, paidStatus: string, authCode: string|null}
     */
    public function reverseAuthorization(array $params, ?string $idempotencyKey = null): array
    {
        return $this->mapPayment($this->send(FieldOrders::paymentReverseAuthorization(), $params, $idempotencyKey));
    }

    /**
     * Query the current gateway status of an invoice
     * (`POST /api/integration/check-status`). This is a pure read despite being a
     * POST, so it is retried on transient failures.
     *
     * @param  array<string, mixed>  $params
     * @return array{invoiceId: int, paidStatus: string, authCode: string|null}
     */
    public function checkStatus(array $params, ?string $idempotencyKey = null): array
    {
        return $this->mapPayment($this->send(FieldOrders::paymentCheckStatus(), $params, $idempotencyKey, replaySafe: true));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function send(EndpointSpec $spec, array $params, ?string $idempotencyKey, bool $replaySafe = false): array
    {
        $body = SignRequest::build($spec, $params, $this->config->publicToken, $this->config->hashToken());

        return $this->http->object(new RequestOptions(
            method: 'POST',
            path: $spec->path,
            body: $body,
            idempotencyKey: $idempotencyKey,
            replaySafe: $replaySafe ?: null,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{invoiceId: int, paidStatus: string, authCode: string|null}
     */
    private function mapPayment(array $data): array
    {
        return [
            'invoiceId' => (int) $data['invoice_id'],
            'paidStatus' => (string) $data['paid_status'],
            'authCode' => isset($data['auth_code']) ? (string) $data['auth_code'] : null,
        ];
    }
}
