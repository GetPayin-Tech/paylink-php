<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Exceptions;

/**
 * Thrown when the client is constructed with missing or invalid configuration
 * (e.g. an empty public/hash token, or a non-positive timeout).
 */
class PaylinkConfigException extends PaylinkException {}
