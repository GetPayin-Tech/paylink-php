<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Exceptions;

use Exception;

/**
 * Base class for every error thrown by the SDK. Catch this to handle any
 * PayLink failure generically, or narrow to a subclass for specific handling.
 */
class PaylinkException extends Exception {}
