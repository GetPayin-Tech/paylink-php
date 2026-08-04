<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Resources;

use GetPayin\Paylink\Core\Config;
use GetPayin\Paylink\Core\Http\RequestExecutor;
use GetPayin\Paylink\Core\Http\RequestOptions;
use GetPayin\Paylink\Core\Internal\FieldOrders;
use GetPayin\Paylink\Core\Internal\SignRequest;
use GetPayin\Paylink\Core\PaylinkClient;
use GetPayin\Paylink\Core\Signature;

/**
 * The `recurring` resource: create a mandate and manage its lifecycle — read
 * status, cancel, pause, and resume. Reached via
 * {@see PaylinkClient::$recurring}.
 */
final class Recurring
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestExecutor $http,
    ) {}

    /**
     * Create a recurring mandate and return a checkout URL for the first (setup)
     * charge (`POST /api/v2/integration/recurring/init`). Pass `$idempotencyKey`
     * (or an `externalReference`) to make retries safe.
     *
     * @param  array<string, mixed>  $params
     * @return array{checkoutUrl: string, mandateId: string, invoiceId: int, expiresAt: string}
     */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        $spec = FieldOrders::recurringCreate();
        $body = SignRequest::build($spec, $params, $this->config->publicToken, $this->config->hashToken());

        $data = $this->http->object(new RequestOptions(
            method: 'POST',
            path: $spec->path,
            body: $body,
            idempotencyKey: $idempotencyKey,
        ));

        return [
            'checkoutUrl' => (string) $data['checkout_url'],
            'mandateId' => (string) $data['mandate_id'],
            'invoiceId' => (int) $data['invoice_id'],
            'expiresAt' => (string) $data['expires_at'],
        ];
    }

    /**
     * Query a mandate's status (`GET /api/v2/integration/recurring/{mandate}`).
     *
     * @return array{mandateId: string, status: string, amount: int, completedCycles: int, totalCycles: int|null, nextChargeAt: string|null}
     */
    public function status(string $mandateId): array
    {
        $data = $this->http->object(new RequestOptions(
            method: 'GET',
            path: $this->mandatePath($mandateId),
            query: ['token' => $this->config->publicToken, 'signature' => $this->signMandate($mandateId)],
        ));

        return [
            'mandateId' => (string) $data['mandate_id'],
            'status' => (string) $data['status'],
            'amount' => (int) $data['amount'],
            'completedCycles' => (int) $data['completed_cycles'],
            'totalCycles' => isset($data['total_cycles']) ? (int) $data['total_cycles'] : null,
            'nextChargeAt' => isset($data['next_charge_at']) ? (string) $data['next_charge_at'] : null,
        ];
    }

    /**
     * Cancel a mandate (`POST /api/v2/integration/recurring/{mandate}/cancel`).
     *
     * @return array<string, mixed>
     */
    public function cancel(string $mandateId): array
    {
        return $this->action($mandateId, 'cancel');
    }

    /**
     * Pause a mandate (`POST /api/v2/integration/recurring/{mandate}/pause`).
     *
     * @return array<string, mixed>
     */
    public function pause(string $mandateId): array
    {
        return $this->action($mandateId, 'pause');
    }

    /**
     * Resume a paused mandate (`POST /api/v2/integration/recurring/{mandate}/resume`).
     *
     * @return array<string, mixed>
     */
    public function resume(string $mandateId): array
    {
        return $this->action($mandateId, 'resume');
    }

    /**
     * @return array<string, mixed>
     */
    private function action(string $mandateId, string $action): array
    {
        return $this->http->object(new RequestOptions(
            method: 'POST',
            path: $this->mandatePath($mandateId) . '/' . $action,
            body: ['token' => $this->config->publicToken, 'signature' => $this->signMandate($mandateId)],
        ));
    }

    private function mandatePath(string $mandateId): string
    {
        return '/api/v2/integration/recurring/' . rawurlencode($mandateId);
    }

    private function signMandate(string $mandateId): string
    {
        return Signature::build([$mandateId], $this->config->hashToken());
    }
}
