<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Date\Calendar;
use DateInterval;
use Override;
use Peso\Core\Exceptions\ConversionNotPerformedException;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentConversionRequest;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalConversionRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ConversionResponse;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\PesoServiceInterface;
use Peso\Core\Services\ReversibleService;
use Peso\Core\Services\SDK\Cache\NullCache;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Services\SDK\HTTP\DiscoveredHttpClient;
use Peso\Core\Services\SDK\HTTP\DiscoveredRequestFactory;
use Peso\Core\Services\SDK\HTTP\UserAgentHelper;
use Peso\Core\Types\Decimal;
use Peso\Services\Coinlayer\AccessKeyType;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class CoinlayerService implements PesoServiceInterface
{
    private const ENDPOINT_LATEST = 'https://api.coinlayer.com/live?%s';
    private const ENDPOINT_HISTORICAL = 'https://api.coinlayer.com/%s?%s';
    private const ENDPOINT_CONVERSION = 'https://api.coinlayer.com/convert?%s';

    public function __construct(
        private string $accessKey,
        private AccessKeyType $accessKeyType,
        private array|null $symbols = null,
        private CacheInterface $cache = new NullCache(),
        private DateInterval $ttl = new DateInterval('PT1H'),
        private ClientInterface $httpClient = new DiscoveredHttpClient(),
        private RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
    ) {
    }

    public static function reversible(
        string $accessKey,
        AccessKeyType $accessKeyType,
        array|null $symbols = null,
        CacheInterface $cache = new NullCache(),
        DateInterval $ttl = new DateInterval('PT1H'),
        ClientInterface $httpClient = new DiscoveredHttpClient(),
        RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
    ): PesoServiceInterface {
        return new ReversibleService(
            new self($accessKey, $accessKeyType, $symbols, $cache, $ttl, $httpClient, $requestFactory),
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function send(object $request): ExchangeRateResponse|ConversionResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            return self::performCurrentRequest($request);
        }
        if ($request instanceof HistoricalExchangeRateRequest) {
            return self::performHistoricalRequest($request);
        }
        if ($request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest) {
            return self::performConversionRequest($request);
        }
        return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
    }

    private function performCurrentRequest(CurrentExchangeRateRequest $request): ErrorResponse|ExchangeRateResponse
    {
        $query = [
            'access_key' => $this->accessKey,
            'target' => $request->baseCurrency,
            'symbols' => $this->symbols === null ? null : implode(',', $this->symbols),
        ];

        $url = \sprintf(self::ENDPOINT_LATEST, http_build_query($query, encoding_type: PHP_QUERY_RFC3986));

        $rateData = $this->retrieveResponse($url);

        return isset($rateData['rates'][$request->quoteCurrency]) ?
            new ExchangeRateResponse(
                Decimal::init($rateData['rates'][$request->quoteCurrency]),
                Calendar::fromTimestamp($rateData['timestamp']),
            ) :
            new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
    }

    private function performHistoricalRequest(
        HistoricalExchangeRateRequest $request,
    ): ErrorResponse|ExchangeRateResponse {
        $query = [
            'access_key' => $this->accessKey,
            'target' => $request->baseCurrency,
            'symbols' => $this->symbols === null ? null : implode(',', $this->symbols),
        ];

        $url = \sprintf(
            self::ENDPOINT_HISTORICAL,
            (string)$request->date,
            http_build_query($query, encoding_type: PHP_QUERY_RFC3986),
        );

        $rateData = $this->retrieveResponse($url);

        return isset($rateData['rates'][$request->quoteCurrency]) ?
            new ExchangeRateResponse(
                Decimal::init($rateData['rates'][$request->quoteCurrency]),
                Calendar::fromTimestamp($rateData['timestamp']),
            ) :
            new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
    }

    private function performConversionRequest(
        CurrentConversionRequest|HistoricalConversionRequest $request,
    ): ErrorResponse|ConversionResponse {
        if ($this->accessKeyType !== AccessKeyType::Subscription) {
            return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
        }

        $query = [
            'access_key' => $this->accessKey,
            'from' => $request->baseCurrency,
            'to' => $request->quoteCurrency,
            'amount' => $request->baseAmount->value,
        ];

        if ($request instanceof HistoricalConversionRequest) {
            $query['date'] = $request->date->toString();
        }

        $url = \sprintf(self::ENDPOINT_CONVERSION, http_build_query($query, encoding_type: PHP_QUERY_RFC3986));

        $convertData = $this->retrieveResponse($url);

        return isset($convertData['result']) ?
            new ConversionResponse(
                Decimal::init($convertData['result']),
                Calendar::fromTimestamp($convertData['info']['timestamp']),
            ) :
            new ErrorResponse(ConversionNotPerformedException::fromRequest($request));
    }

    private function retrieveResponse(string $url): array|false
    {
        $cacheKey = 'peso|coinlayer|' . hash('sha1', $url);

        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

        $request = $this->requestFactory->createRequest('GET', $url);
        $request = $request->withHeader('User-Agent', UserAgentHelper::buildUserAgentString(
            'CoinlayerClient',
            'peso/coinlayer-service',
            $request->hasHeader('User-Agent') ? $request->getHeaderLine('User-Agent') : null,
        ));
        $response = $this->httpClient->sendRequest($request);

        // 400 indicates request problems
        if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 400) {
            throw HttpFailureException::fromResponse($request, $response);
        }

        /**
         * @var array{success: true, rates: array, timestamp: int}|array{success: false, error: array{code: int}} $data
         */
        $data = json_decode(
            (string)$response->getBody(),
            flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY,
        );

        if ($data['success'] === false) {
            if (!\in_array($data['error']['code'], [
                201, // invalid base currency
                302, // invalid date
            ])) {
                throw HttpFailureException::fromResponse($request, $response);
            }
        }

        $this->cache->set($cacheKey, $data, $this->ttl);

        return $data;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function supports(object $request): bool
    {
        if ($request instanceof CurrentExchangeRateRequest || $request instanceof HistoricalExchangeRateRequest) {
            return true;
        }

        if ($request instanceof CurrentConversionRequest || $request instanceof HistoricalConversionRequest) {
            // conversion is available only on subscription plans
            return $this->accessKeyType === AccessKeyType::Subscription;
        }

        return false;
    }
}
