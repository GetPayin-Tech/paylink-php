<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Laravel\Facades;

use GetPayin\Paylink\Core\PaylinkClient;
use GetPayin\Paylink\Core\Resources\Cards;
use GetPayin\Paylink\Core\Resources\Invoices;
use GetPayin\Paylink\Core\Resources\Payments;
use GetPayin\Paylink\Core\Resources\Recurring;
use GetPayin\Paylink\Core\Resources\Vcc;
use GetPayin\Paylink\Core\Webhooks;
use Illuminate\Support\Facades\Facade;

/**
 * Facade over the configured {@see PaylinkClient} singleton. Resource namespaces
 * are reached as method calls, e.g. `Paylink::invoices()->create([...])`.
 *
 * @method static Invoices invoices()
 * @method static Payments payments()
 * @method static Vcc vcc()
 * @method static Cards cards()
 * @method static Recurring recurring()
 * @method static Webhooks webhooks()
 *
 * @see PaylinkClient
 */
final class Paylink extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PaylinkClient::class;
    }
}
