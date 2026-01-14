<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use GuzzleHttp\Psr7\Response;
use Http\Mock\Client;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Services\Coinlayer\AccessKeyType;
use Peso\Services\CoinlayerService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EdgeCasesTest extends TestCase
{
    public function testInvalidRequest(): void
    {
        $service = new CoinlayerService('xxx', AccessKeyType::Free);

        $response = $service->send(new stdClass());
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(RequestNotSupportedException::class, $response->exception);
        self::assertEquals('Unsupported request type: "stdClass"', $response->exception->getMessage());
    }

    public function testHttpFailure(): void
    {
        $http = new Client();
        $http->setDefaultResponse(new Response(500, body: 'Server error or something'));

        $service = new CoinlayerService('xxx', AccessKeyType::Free, httpClient: $http);

        self::expectException(HttpFailureException::class);
        self::expectExceptionMessage('HTTP error 500. Response is "Server error or something"');
        $service->send(new CurrentExchangeRateRequest('EUR', 'USD'));
    }
}
