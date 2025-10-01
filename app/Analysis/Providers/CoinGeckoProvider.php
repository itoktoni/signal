<?php

namespace App\Analysis\Providers;

use App\Analysis\Contract\MarketDataInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class CoingeckoProvider implements MarketDataInterface
{
    private Client $http;
    private string $baseUrl = 'https://api.coingecko.com/api/v3';

    public function __construct()
    {
        $this->http = new Client([
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Laravel-Crypto-Analysis/1.0',
                'Accept' => 'application/json',
            ]
        ]);
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
        // CoinGecko uses coin ID, not trading pairs
        $coinId = strtolower($symbol);

        // Map timeframe to appropriate days parameter for CoinGecko
        $days = match($timeframe) {
            '1h' => 1,    // 1 day for hourly data
            '4h' => 7,    // 7 days for 4h data
            '1d' => 90,   // 90 days for daily data
            '1w' => 365,  // 365 days for weekly data
            default => 90
        };

        $url = $this->baseUrl . "/coins/{$coinId}/ohlc";
        $response = $this->http->get($url, [
            'query' => [
                'vs_currency' => 'usd',
                'days' => $days
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Debug: Log the actual CoinGecko response

        if (empty($data) || !isset($data[0])) {
            Log::warning('CoinGecko OHLC returned empty data');
            return [];
        }

        // CoinGecko OHLC format is typically: [timestamp, open, high, low, close]
        // Convert to our expected format: [open, high, low, close, volume, timestamp, ...]
        $normalized = [];
        foreach ($data as $ohlc) {
            if (count($ohlc) >= 5) {
                $normalized[] = [
                    (int) $ohlc[0], // timestamp as closeTime
                    (string) $ohlc[1], // open
                    (string) $ohlc[2], // high
                    (string) $ohlc[3], // low
                    (string) $ohlc[4], // close
                    '0', // volume (not available in CoinGecko OHLC)
                    (int) $ohlc[0], // timestamp as closeTime
                    '0', '0', '0', '0',
                ];
            }
        }

        Log::info('CoinGecko data normalized', [
            'original_count' => count($data),
            'normalized_count' => count($normalized)
        ]);

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

    public function getSymbolInfo(): array
    {
        $url = $this->baseUrl . "/coins/list";
        $response = $this->http->get($url);

        $data = json_decode($response->getBody()->getContents(), true);

        $parsing = [];

        foreach ($data as $symbol) {
            // Only process USDT pairs that are actively trading
            if (!is_numeric($symbol['id']) && ctype_alnum($symbol['symbol']) && ctype_alnum($symbol['name']) && !(str_contains($symbol['id'], '_'))) {

                $parsing[] = [
                    'id' => $symbol['id'],
                    'symbol' => $symbol['symbol'],
                    'name' => $symbol['name'],
                    'provider' => 'coingecko',
                ];
            }
        }

        if (empty($parsing) || !is_array($parsing)) {
            Log::warning('CoinGecko getSymbolInfo returned empty or invalid data');
            return [];
        }

        return $parsing;
    }
}
