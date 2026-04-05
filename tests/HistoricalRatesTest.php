<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\Coinlayer\AccessKeyType;
use Peso\Services\CoinlayerService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

// phpcs:disable Generic.Files.LineLength.TooLong
final class HistoricalRatesTest extends TestCase
{
    public function testRateUSD(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);
        $date = Calendar::parse('2026-02-04');

        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'BTC', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('73154.462318', $response->rate->value);
        self::assertEquals('2026-02-04', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'ZSC', $date)); // exponent rate
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.00002376', $response->rate->value);
        self::assertEquals('2026-02-04', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'DOGE', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.104133', $response->rate->value);
        self::assertEquals('2026-02-04', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateCZK(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);
        $date = Calendar::parse('2026-02-04');

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'BTC', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1510913.949582', $response->rate->value);
        self::assertEquals('2026-02-04', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'ZSC', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.000491', $response->rate->value);
        self::assertEquals('2026-02-04', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('CZK', 'DOGE', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('2.150743', $response->rate->value);
        self::assertEquals('2026-02-04', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Free, symbols: [
            'BTC', 'ZSC', 'MNX',
        ], cache: $cache, httpClient: $http);
        $date = Calendar::parse('2026-02-04');

        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'BTC', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('73154.462318', $response->rate->value);
        self::assertEquals('2026-02-04', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'ZSC', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.00002376', $response->rate->value);
        self::assertEquals('2026-02-04', $response->date->toString());

        // not included
        $response = $service->send(new HistoricalExchangeRateRequest('USD', 'DOGE', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for USD/DOGE on 2026-02-04', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testInvalidCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);
        $date = Calendar::parse('2026-02-04');

        $response = $service->send(new HistoricalExchangeRateRequest('UNK', 'DOGE', $date));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for UNK/DOGE on 2026-02-04', $response->exception->getMessage());
    }
}
