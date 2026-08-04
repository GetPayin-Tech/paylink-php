<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Laravel\Providers;

use GetPayin\Paylink\Core\Config;
use GetPayin\Paylink\Core\Http\Transport;
use GetPayin\Paylink\Core\PaylinkClient;
use GetPayin\Paylink\Laravel\LaravelHttpTransport;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Registers a {@see PaylinkClient} singleton built from `config/paylink.php`, so
 * a Laravel app can inject the client (or use the `Paylink` facade) without
 * wiring tokens by hand. Auto-discovered via `extra.laravel.providers`.
 *
 * Deferred: nothing is resolved until the client is first requested.
 */
final class PaylinkServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'paylink');

        $this->app->singleton(PaylinkClient::class, static function (Application $app): PaylinkClient {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('paylink', []);

            return new PaylinkClient(
                publicToken: (string) ($config['public_token'] ?? ''),
                hashToken: (string) ($config['hash_token'] ?? ''),
                baseUrl: (string) ($config['base_url'] ?? Config::DEFAULT_BASE_URL),
                timeoutMs: (int) ($config['timeout_ms'] ?? Config::DEFAULT_TIMEOUT_MS),
                maxRetries: (int) ($config['max_retries'] ?? Config::DEFAULT_MAX_RETRIES),
                transport: ($config['use_laravel_http'] ?? true) ? new LaravelHttpTransport : null,
                logger: Log::channel($config['log_channel'] ?? null),
            );
        });

        $this->app->alias(PaylinkClient::class, 'paylink');
        $this->app->bind(Transport::class, LaravelHttpTransport::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->configPath() => $this->app->configPath('paylink.php')], 'paylink-config');
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [PaylinkClient::class, 'paylink', Transport::class];
    }

    private function configPath(): string
    {
        return __DIR__ . '/../../../config/paylink.php';
    }
}
