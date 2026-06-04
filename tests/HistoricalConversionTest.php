<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Peso\Core\Requests\HistoricalConversionRequest;
use Peso\Core\Responses\ConversionResponse;
use Peso\Core\Types\Decimal;
use Peso\Services\Coinlayer\AccessKeyType;
use Peso\Services\CoinlayerService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class HistoricalConversionTest extends TestCase
{
    public function testCurrentConv(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxpaidxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);
        $date = Calendar::parse('2016-06-13');

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'BTC', 'USD', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('864278.4192', $response->amount->value);
        self::assertEquals('2016-06-13', $response->date->toString());

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'USD', 'ETH', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('70.345229', $response->amount->value);
        self::assertEquals('2016-06-13', $response->date->toString());

        $response = $service->send(
            new HistoricalConversionRequest(Decimal::init('1234.56'), 'ETH', 'LTC', $date),
        );
        self::assertInstanceOf(ConversionResponse::class, $response);
        self::assertEquals('4158.64252', $response->amount->value);
        self::assertEquals('2016-06-13', $response->date->toString());
    }
}
