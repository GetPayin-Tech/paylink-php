<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Exceptions;

/**
 * Thrown when the request never produced an HTTP response — a network failure,
 * DNS error, or the configured timeout elapsing before the server replied.
 */
class PaylinkConnectionException extends PaylinkException {}
