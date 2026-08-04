<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Resources;

use GetPayin\Paylink\Core\Config;
use GetPayin\Paylink\Core\Http\RequestExecutor;
use GetPayin\Paylink\Core\Http\RequestOptions;
use GetPayin\Paylink\Core\Internal\FieldOrders;
use GetPayin\Paylink\Core\Internal\SignRequest;
use GetPayin\Paylink\Core\PaylinkClient;

/**
 * The `invoices` resource: hosted-checkout creation. Reached via
 * {@see PaylinkClient::$invoices}.
 */
final class Invoices
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestExecutor $http,
    ) {}

    /**
     * Create an invoice and return a temporary signed checkout URL
     * (`POST /api/v2/integration/init`). Redirect the payer to `checkoutUrl`.
     *
     * @param  array<string, mixed>  $params
     * @return array{checkoutUrl: string, invoiceId: int, expiresAt: string}
     */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        $spec = FieldOrders::invoiceCreate();
        $body = SignRequest::build($spec, $params, $this->config->publicToken, $this->config->hashToken());

        $data = $this->http->object(new RequestOptions(
            method: 'POST',
            path: $spec->path,
            body: $body,
            idempotencyKey: $idempotencyKey,
        ));

        return [
            'checkoutUrl' => (string) $data['checkout_url'],
            'invoiceId' => (int) $data['invoice_id'],
            'expiresAt' => (string) $data['expires_at'],
        ];
    }
}
