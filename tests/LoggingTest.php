<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Concerns\LogsAction;
use GetPayin\Paylink\Core\Exceptions\PaylinkApiException;
use GetPayin\Paylink\Core\Http\RequestOptions;
use GetPayin\Paylink\Tests\Support\FakeTransport;
use GetPayin\Paylink\Tests\Support\SpyLogger;
use Psr\Log\LoggerInterface;

it('logs a timed info line for every request, prefixed with the component', function (): void {
    $logger = new SpyLogger;

    makeExecutor(new FakeTransport(FakeTransport::ok(['ok' => 1])), logger: $logger)
        ->execute(new RequestOptions('POST', '/api/v2/integration/init', body: []));

    $info = $logger->withLevel('info');

    expect($info)->toHaveCount(1)
        ->and($info[0]['message'])->toBe('[RequestExecutor] POST /api/v2/integration/init')
        ->and($info[0]['context'])->toHaveKey('duration_ms');
});

it('logs a warning on each retry and an error once retries are exhausted', function (): void {
    $logger = new SpyLogger;
    $transport = new FakeTransport(
        FakeTransport::fail(503, ['success' => false]),
        FakeTransport::fail(503, ['success' => false]),
        FakeTransport::fail(503, ['success' => false]),
    );

    expect(fn () => makeExecutor($transport, maxRetries: 2, logger: $logger)
        ->execute(new RequestOptions('GET', '/x')))
        ->toThrow(PaylinkApiException::class);

    expect($logger->withLevel('warning'))->toHaveCount(2)
        ->and($logger->withLevel('error'))->toHaveCount(1)
        ->and($logger->withLevel('warning')[0]['message'])->toBe('[RequestExecutor] Request failed; retrying')
        ->and($logger->withLevel('warning')[0]['context'])->toMatchArray(['status' => 503, 'attempt' => 1]);
});

it('does not log an error for a non-retryable client failure', function (): void {
    $logger = new SpyLogger;

    expect(fn () => makeExecutor(new FakeTransport(FakeTransport::fail(422, ['success' => false, 'message' => 'bad'])), logger: $logger)
        ->execute(new RequestOptions('POST', '/x', body: [])))
        ->toThrow(PaylinkApiException::class);

    expect($logger->withLevel('error'))->toBeEmpty()
        ->and($logger->withLevel('warning'))->toBeEmpty();
});

it('logs a warning when a webhook signature does not match, without leaking the payload', function (): void {
    $logger = new SpyLogger;
    $client = makeClient(new FakeTransport, 'secret', $logger);

    $client->webhooks->isValid([
        'event' => 'invoice.paid', 'success' => 1, 'invoice_id' => 42, 'signature' => 'tampered',
    ]);

    $warnings = $logger->withLevel('warning');

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['message'])->toBe('[Webhooks] Webhook rejected: signature mismatch')
        ->and($warnings[0]['context'])->toMatchArray(['event' => 'invoice.paid', 'invoice_id' => 42]);
});

it('masks sensitive keys in any log context', function (): void {
    $logger = new SpyLogger;

    $harness = new class($logger)
    {
        use LogsAction;

        public function __construct(private LoggerInterface $spy) {}

        protected function logger(): LoggerInterface
        {
            return $this->spy;
        }

        /** @param  array<string, mixed>  $context */
        public function emit(array $context): void
        {
            $this->logInfo('probe', $context);
        }
    };

    $harness->emit(['token' => 'super-secret', 'card_number' => '4111111111111111', 'nested' => ['cvv' => '123'], 'safe' => 'ok']);

    $context = $logger->records[0]['context'];

    expect($context['token'])->toBe('********')
        ->and($context['card_number'])->toBe('********')
        ->and($context['nested']['cvv'])->toBe('********')
        ->and($context['safe'])->toBe('ok');
});
