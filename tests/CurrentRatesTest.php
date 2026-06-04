<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\Coinlayer\AccessKeyType;
use Peso\Services\CoinlayerService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

// phpcs:disable Generic.Files.LineLength.TooLong
final class CurrentRatesTest extends TestCase
{
    public function testRateUSD(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Free, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('BTC', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('97532.258571', $response->rate->value);
        self::assertEquals('2026-01-14', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('ZSC', 'USD')); // exponent rate
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.00002376', $response->rate->value);
        self::assertEquals('2026-01-14', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('DOGE', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.148912', $response->rate->value);
        self::assertEquals('2026-01-14', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateCZK(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('BTC', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('2023357.58177', $response->rate->value);
        self::assertEquals('2026-01-14', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('ZSC', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.000495', $response->rate->value);
        self::assertEquals('2026-01-14', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('DOGE', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('3.08173', $response->rate->value);
        self::assertEquals('2026-01-14', $response->date->toString());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testRateWithSymbols(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Free, symbols: [
            'BTC', 'ZSC', 'MNX',
        ], cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('BTC', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('97450.985', $response->rate->value);
        self::assertEquals('2026-01-14', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('ZSC', 'USD'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.00002376', $response->rate->value);
        self::assertEquals('2026-01-14', $response->date->toString());

        // not included
        $response = $service->send(new CurrentExchangeRateRequest('DOGE', 'USD'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for DOGE/USD', $response->exception->getMessage());

        self::assertCount(1, $http->getRequests()); // subsequent requests are cached
    }

    public function testInvalidCurrency(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CoinlayerService('xxxfreexxx', AccessKeyType::Subscription, cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('DOGE', 'UNK'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for DOGE/UNK', $response->exception->getMessage());
    }
}
