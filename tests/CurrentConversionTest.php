<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Exceptions\ConversionNotPerformedException;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Responses\ConversionResponse;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Types\Decimal;
use Peso\Services\Coinlayer\AccessKeyType;
use Peso\Services\CoinlayerService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class CurrentConversionTest extends TestCase
{
    public function testCurrentConv(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxpaidxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'BTC', 'USD'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('79411132.470438', $response->amount->value);
        self::assertEquals('2026-06-04', $response->date->toString());

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'USD', 'ETH'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('0.680243', $response->amount->value);
        self::assertEquals('2026-06-04', $response->date->toString());

        $response = $service->send(
            new CurrentConversionRequest(Decimal::init('1234.56'), 'ETH', 'LTC'),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('47644.680257', $response->amount->value);
        self::assertEquals('2026-06-04', $response->date->toString());
    }

    public function testInvalidBaseCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxpaidxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentConversionRequest(Decimal::init(1), 'XBT', 'USD'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1 XBT to USD', $response->exception->getMessage());
    }

    public function testInvalidQuoteCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxpaidxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentConversionRequest(Decimal::init(1), 'USD', 'XBT'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ConversionNotPerformedException::class, $response->exception);
        self::assertEquals('Unable to convert 1 USD to XBT', $response->exception->getMessage());
    }
}
