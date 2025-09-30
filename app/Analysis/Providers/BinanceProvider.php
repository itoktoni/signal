<?php

namespace App\Analysis\Providers;

use App\Analysis\Contract\MarketDataInterface;
use GuzzleHttp\Client;

class BinanceProvider implements MarketDataInterface
{
    private Client $http;
    private string $baseUrl = 'https://api.binance.com/api/v3';

    public function __construct()
    {
        $this->http = new Client(['timeout' => 10]);
    }

    public function getCode(): string
    {
        return 'binance';
    }

    public function getName(): string
    {
        return 'Binance';
    }


    public function getHistoricalData(string $symbol, string $timeframe = '1h', int $limit = 200): array
    {
        $url = $this->baseUrl . "/klines";
        $response = $this->http->get($url, [
            'query' => [
                'symbol'   => strtoupper($symbol),
                'interval' => $timeframe,
                'limit'    => $limit,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getPrice(string $symbol): float
    {
        $url = $this->baseUrl . "/ticker/price";
        $response = $this->http->get($url, [
            'query' => ['symbol' => strtoupper($symbol)],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return (float) $data['price'];
    }
}
