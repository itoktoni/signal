<?php

namespace App\Analysis\Providers;

use App\Analysis\Contract\MarketDataInterface;
use GuzzleHttp\Client;

class CoingeckoProvider implements MarketDataInterface
{
    private Client $http;
    private string $baseUrl = 'https://api.coingecko.com/api/v3';

    public function __construct()
    {
        $headers = [
            'User-Agent' => 'Laravel-Crypto-Analysis/1.0',
            'Accept' => 'application/json',
        ];

        // Add API key if provided
        if (!empty($this->config['api_key'])) {
            $headers['x-cg-demo-api-key'] = env('COINGECKO_API_KEY');
        }

        $this->http = new Client(['timeout' => 10, 'headers' => $headers]);
    }

    public function getCode(): string
    {
        return 'coingecko';
    }

    public function getName(): string
    {
        return 'CoinGecko';
    }

    public function getHistoricalData(string $symbol, string $timeframe = '1h', int $limit = 200): array
    {
        // Coingecko pakai coin id, bukan pair langsung
        $coinId = strtolower(explode('USDT', $symbol)[0]);

        $url = $this->baseUrl . "/coins/{$coinId}/ohlc";
        $response = $this->http->get($url, [
            'query' => [
                'vs_currency' => 'usd',
                'days'        => 30, // default ambil 30 hari
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Normalisasi agar mirip format Binance
        $normalized = [];
        foreach ($data as $i => $price) {
            $normalized[] = [
                // time(), // closeTime
                (string) $price[0], // open
                (string) $price[1], // high
                (string) $price[2], // low
                (string) $price[3], // close
                (string) 0, // volume
                $price[1], $price[1], $price[1], $price[1],
            ];
        }

        return $normalized;
    }

    public function getPrice(string $symbol): float
    {
        $coinId = strtolower(explode('USDT', $symbol)[0]);

        $url = $this->baseUrl . "/simple/price";
        $response = $this->http->get($url, [
            'query' => [
                'ids'           => $coinId,
                'vs_currencies' => 'usd',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return (float) ($data[$coinId]['usd'] ?? 0);
    }
}
