<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Date;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalConversionRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Types\Decimal;
use Peso\Services\Coinlayer\AccessKeyType;
use Peso\Services\CoinlayerService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SupportTest extends TestCase
{
    public function testRequestsFree(): void
    {
        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Free);

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('USD', 'BTC')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('USD', 'BTC', Date::today())));

        self::assertFalse($service->supports(
            new CurrentConversionRequest(new Decimal('1000'), 'USD', 'BTC'),
        ));
        self::assertFalse($service->supports(
            new HistoricalConversionRequest(new Decimal('1000'), 'USD', 'BTC', Date::today()),
        ));

        self::assertFalse($service->supports(new stdClass()));
    }

    public function testRequests(): void
    {
        $service = new CoinlayerService('xxxpaidxxx', AccessKeyType::Subscription);

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('USD', 'BTC')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('USD', 'BTC', Date::today())));

        self::assertTrue($service->supports(
            new CurrentConversionRequest(new Decimal('1000'), 'USD', 'BTC'),
        ));
        self::assertTrue($service->supports(
            new HistoricalConversionRequest(new Decimal('1000'), 'USD', 'BTC', Date::today()),
        ));

        self::assertFalse($service->supports(new stdClass()));
    }
}
