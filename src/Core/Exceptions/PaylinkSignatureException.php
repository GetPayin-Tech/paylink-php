<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Exceptions;

use GetPayin\Paylink\Core\Webhooks;

/**
 * Thrown by {@see Webhooks::verify()} when a webhook payload's
 * signature is missing or does not match the value recomputed from the hashToken.
 */
class PaylinkSignatureException extends PaylinkException
{
    public function __construct(string $message = 'Webhook signature verification failed.')
    {
        parent::__construct($message);
    }
}
