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
        $this->http = new Client(['timeout' => 10]);
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

        $url = $this->baseUrl . "/coins/{$coinId}/market_chart";
        $response = $this->http->get($url, [
            'query' => [
                'vs_currency' => 'usd',
                'days'        => 30, // default ambil 30 hari
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Normalisasi agar mirip format Binance
        $normalized = [];
        foreach ($data['prices'] as $i => $price) {
            $normalized[] = [
                (string) $price[1], // open
                (string) $price[1], // high
                (string) $price[1], // low
                (string) $price[1], // close
                (string) ($data['total_volumes'][$i][1] ?? 0), // volume
                (int) $price[0], // closeTime
                '0', '0', '0', '0',
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
