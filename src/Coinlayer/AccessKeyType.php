<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Coinlayer;

enum AccessKeyType
{
    case Free;
    case Subscription;
}
