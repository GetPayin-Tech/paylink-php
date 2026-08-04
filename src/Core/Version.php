<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core;

/**
 * The installed SDK version, sent on the User-Agent of every request so
 * server-side logs can attribute traffic to a version.
 */
final class Version
{
    public const VALUE = '0.1.0';
}
