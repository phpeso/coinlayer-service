<?php

/**
 * @copyright 2026 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests\Helpers;

use GuzzleHttp\Psr7\Response;
use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;
use Psr\Http\Message\RequestInterface;

final readonly class MockClient
{
    public static function get(): Client
    {
        $client = new Client();

        $client->on(
            new RequestMatcher('/live', 'api.coinlayer.com', ['GET'], ['https']),
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case 'access_key=xxxfreexxx&target=USD':
                        return new Response(200, body: fopen(__DIR__ . '/../data/rates/latest-usd.json', 'r'));

                    case 'access_key=xxxfreexxx&target=CZK':
                        return new Response(200, body: fopen(__DIR__ . '/../data/rates/latest-czk.json', 'r'));

                    case 'access_key=xxxfreexxx&target=USD&symbols=BTC%2CZSC%2CMNX':
                        return new Response(200, body: fopen(
                            __DIR__ . '/../data/rates/latest-usd-btc,zsc,mnx.json',
                            'r',
                        ));

                    case 'access_key=xxxfreexxx&target=UNK':
                        return new Response(400, body: fopen(__DIR__ . '/../data/rates/unknown-currency.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $request->getUri());
                }
            },
        );

        $client->on(
            new RequestMatcher('/2026-02-04', 'api.coinlayer.com', ['GET'], ['https']),
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case 'access_key=xxxfreexxx&target=USD':
                        return new Response(200, body: fopen(__DIR__ . '/../data/rates/2026-02-04-usd.json', 'r'));

                    case 'access_key=xxxfreexxx&target=CZK':
                        return new Response(200, body: fopen(__DIR__ . '/../data/rates/2026-02-04-czk.json', 'r'));

                    case 'access_key=xxxfreexxx&target=USD&symbols=BTC%2CZSC%2CMNX':
                        return new Response(200, body: fopen(
                            __DIR__ . '/../data/rates/2026-02-04-usd-btc,zsc,mnx.json',
                            'r',
                        ));

                    case 'access_key=xxxfreexxx&target=UNK':
                        return new Response(400, body: fopen(__DIR__ . '/../data/rates/unknown-currency.json', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $request->getUri());
                }
            },
        );

        $client->on(
            new RequestMatcher('/convert', 'api.coinlayer.com', ['GET'], ['https']),
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case 'access_key=xxpaidxx&from=BTC&to=USD&amount=1234.56':
                        return new Response(200, body: fopen(
                            __DIR__ . '/../data/conv/current-btc-usd.json',
                            'r',
                        ));

                    case 'access_key=xxpaidxx&from=USD&to=ETH&amount=1234.56':
                        return new Response(200, body: fopen(
                            __DIR__ . '/../data/conv/current-usd-eth.json',
                            'r',
                        ));

                    case 'access_key=xxpaidxx&from=ETH&to=LTC&amount=1234.56':
                        return new Response(200, body: fopen(
                            __DIR__ . '/../data/conv/current-eth-ltc.json',
                            'r',
                        ));

                    case 'access_key=xxpaidxx&from=USD&to=XBT&amount=1':
                        return new Response(400, body: fopen(
                            __DIR__ . '/../data/conv/current-usd-xbt.json',
                            'r',
                        ));

                    case 'access_key=xxpaidxx&from=XBT&to=USD&amount=1':
                        return new Response(400, body: fopen(
                            __DIR__ . '/../data/conv/current-xbt-usd.json',
                            'r',
                        ));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $request->getUri());
                }
            },
        );

        return $client;
    }
}
