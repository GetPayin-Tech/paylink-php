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
 * The `vcc` resource: server-to-server charges with raw card data. Requires PCI
 * scope. Reached via {@see PaylinkClient::$vcc}.
 */
final class Vcc
{
    public function __construct(
        private readonly Config $config,
        private readonly RequestExecutor $http,
    ) {}

    /**
     * Server-to-server card charge with raw card data
     * (`POST /api/v2/integration/vcc/charge`). Requires PCI scope to send PAN/CVV.
     *
     * @param  array<string, mixed>  $params
     * @return array{invoiceId: int, invoiceNumber: string, amount: int, currency: string, paidStatus: string}
     */
    public function charge(array $params, ?string $idempotencyKey = null): array
    {
        $spec = FieldOrders::vccCharge();
        $body = SignRequest::build($spec, $params, $this->config->publicToken, $this->config->hashToken());

        $data = $this->http->object(new RequestOptions(
            method: 'POST',
            path: $spec->path,
            body: $body,
            idempotencyKey: $idempotencyKey,
        ));

        return [
            'invoiceId' => (int) $data['invoice_id'],
            'invoiceNumber' => (string) $data['invoice_number'],
            'amount' => (int) $data['amount'],
            'currency' => (string) $data['currency'],
            'paidStatus' => (string) $data['paid_status'],
        ];
    }
}
