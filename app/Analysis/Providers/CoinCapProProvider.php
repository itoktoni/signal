<?php

namespace App\Analysis\Providers;

use App\Analysis\ApiProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CoinCapProProvider implements ApiProviderInterface
{
    private Client $client;
    private array $config;
    private array $rateLimitInfo;

    public function __construct()
    {
        $this->config = config('crypto.api_providers.providers.coincappro', []);
        $this->rateLimitInfo = [
            'requests_remaining' => $this->config['rate_limits']['requests_per_minute'] ?? 1000,
            'requests_per_minute' => $this->config['rate_limits']['requests_per_minute'] ?? 1000,
        ];

        $headers = [
            'User-Agent' => 'Laravel-Crypto-Analysis/1.0',
            'Accept' => 'application/json',
        ];

        // Add API key if provided
        if (!empty($this->config['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $this->config['api_key'];
        }

        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? 'https://pro-api.coincap.io/v2',
            'timeout' => $this->config['timeout'] ?? 30,
            'headers' => $headers,
        ]);
    }

    public function getCode(): string
    {
        return 'coincappro';
    }

    public function getName(): string
    {
        $apiKey = $this->config['api_key'] ?? '';
        $keyPreview = !empty($apiKey) ? substr($apiKey, 0, 8) . '...' : 'No Key';
        return "CoinCap Pro ({$keyPreview})";
    }

    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $cacheKey = "coincappro_klines_{$symbol}_{$interval}_{$limit}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Convert symbol format (BTCUSDT -> bitcoin)
            $coinId = $this->convertSymbolToCoinCapId($symbol);

            $response = $this->client->get("/assets/{$coinId}/history", [
                'query' => [
                    'interval' => $this->convertIntervalForCoinCap($interval),
                    'limit' => min($limit, 5000), // CoinCap Pro higher limit
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['data']) || !is_array($data['data'])) {
                throw new \Exception('Invalid response from CoinCap Pro API');
            }

            // Convert CoinCap format to Binance format for compatibility
            $convertedData = $this->convertCoinCapToBinanceFormat($data['data']);

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $convertedData, now()->addMinutes(15));

            return $convertedData;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinCap Pro API request failed: ' . $e->getMessage());
        }
    }

    public function getTickerData(string $symbol): array
    {
        $cacheKey = "coincappro_ticker_{$symbol}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $coinId = $this->convertSymbolToCoinCapId($symbol);

            $response = $this->client->get("/assets/{$coinId}");

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['data']) || !isset($data['data']['priceUsd'])) {
                throw new \Exception('Invalid ticker response from CoinCap Pro API');
            }

            $assetData = $data['data'];
            $result = [
                'symbol' => strtoupper($symbol),
                'price' => (string) $assetData['priceUsd']
            ];

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinCap Pro API request failed: ' . $e->getMessage());
        }
    }

    public function getMultipleTickers(array $symbols): array
    {
        $cacheKey = "coincappro_tickers_" . implode('_', $symbols);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $coinIds = array_map([$this, 'convertSymbolToCoinCapId'], $symbols);
            $coinIdsStr = implode(',', $coinIds);

            $response = $this->client->get("/assets?ids=" . $coinIdsStr);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data['data']) || !is_array($data['data'])) {
                throw new \Exception('Invalid response from CoinCap Pro API');
            }

            $result = [];
            foreach ($data['data'] as $asset) {
                $result[] = [
                    'symbol' => strtoupper(str_replace('-', '', $asset['symbol'] . 'USDT')),
                    'price' => (string) $asset['priceUsd']
                ];
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinCap Pro API request failed: ' . $e->getMessage());
        }
    }

    public function getSymbolInfo(?string $symbol = null): array
    {
        $cacheKey = "coincappro_symbol_info";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $params = [];

            if ($symbol) {
                $coinId = $this->convertSymbolToCoinCapId($symbol);
                $params['ids'] = $coinId;
            }

            $response = $this->client->get('/assets', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['data']) || !is_array($data['data'])) {
                throw new \Exception('Invalid response from CoinCap Pro API');
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $data['data'], now()->addMinutes(60));

            return $data['data'];

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinCap Pro API request failed: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        // Check if provider is enabled in config
        if (!($this->config['enabled'] ?? true)) {
            return false;
        }

        // Check if API key is provided
        if (empty($this->config['api_key'])) {
            return false;
        }

        // Check if we're currently rate limited
        $rateLimitKey = 'coincappro_rate_limited';
        if (Cache::get($rateLimitKey, false)) {
            return false;
        }

        // Check remaining requests
        return ($this->rateLimitInfo['requests_remaining'] ?? 0) > 0;
    }

    public function getPriority(): int
    {
        return $this->config['priority'] ?? 0; // Highest priority if API key is available
    }

    public function getRateLimitInfo(): array
    {
        return $this->rateLimitInfo;
    }

    public function getDataFormat(): array
    {
        return [
            'historical' => [
                'type' => 'array',
                'structure' => [
                    'time' => 'integer (timestamp)',
                    'priceUsd' => 'string (price)',
                    'date' => 'string (date)'
                ],
                'note' => 'Converted to Binance format for compatibility',
                'example' => [
                    1499040000000,      // Open time
                    "0.01634790",       // Open
                    "0.80000000",       // High
                    "0.01575800",       // Low
                    "0.01577100",       // Close
                    "148976.11427815",  // Volume
                    1499644799999,      // Close time
                    "2434.19055334",    // Quote asset volume
                    308,                // Number of trades
                    "1756.87402397",    // Taker buy base asset volume
                    "28.46694368",      // Taker buy quote asset volume
                    "17928899.62484339" // Unused field
                ]
            ],
            'ticker' => [
                'type' => 'object',
                'structure' => [
                    'symbol' => 'string',
                    'price' => 'string (current price)'
                ],
                'example' => [
                    'symbol' => 'BTCUSDT',
                    'price' => '43250.50'
                ]
            ]
        ];
    }

    public function normalizeHistoricalData(array $rawData, string $interval): array
    {
        return $this->convertCoinCapToBinanceFormat($rawData);
    }

    public function normalizeTickerData(array $rawData): array
    {
        $normalized = [];

        foreach ($rawData as $item) {
            $normalized[] = [
                'symbol' => $item['symbol'] ?? 'UNKNOWN',
                'price' => (string) ($item['price'] ?? 0)
            ];
        }

        return $normalized;
    }

    private function convertSymbolToCoinCapId(string $symbol): string
    {
        // Convert trading pair symbols to CoinCap coin IDs
        $mapping = [
            'BTCUSDT' => 'bitcoin',
            'ETHUSDT' => 'ethereum',
            'BNBUSDT' => 'binance-coin',
            'ADAUSDT' => 'cardano',
            'XRPUSDT' => 'xrp',
            'SOLUSDT' => 'solana',
            'DOTUSDT' => 'polkadot',
            'DOGEUSDT' => 'dogecoin',
            'AVAXUSDT' => 'avalanche',
            'LTCUSDT' => 'litecoin',
            'LINKUSDT' => 'chainlink',
            'MATICUSDT' => 'polygon',
            'ALGOUSDT' => 'algorand',
            'UNIUSDT' => 'uniswap',
            'ATOMUSDT' => 'cosmos',
            'VETUSDT' => 'vechain',
            'ICPUSDT' => 'internet-computer',
            'FILUSDT' => 'filecoin',
            'TRXUSDT' => 'tron',
            'ETCUSDT' => 'ethereum-classic',
        ];

        return $mapping[$symbol] ?? 'bitcoin';
    }

    private function convertIntervalForCoinCap(string $interval): string
    {
        // Convert interval to CoinCap format
        $intervalMapping = [
            '1m' => 'm1',
            '5m' => 'm5',
            '15m' => 'm15',
            '30m' => 'm30',
            '1h' => 'h1',
            '4h' => 'h4',
            '1d' => 'd1',
        ];

        return $intervalMapping[$interval] ?? 'h1';
    }

    private function convertCoinCapToBinanceFormat(array $coincapData): array
    {
        $binanceFormat = [];

        foreach ($coincapData as $item) {
            if (!isset($item['priceUsd'], $item['time'])) {
                continue;
            }

            // CoinCap format: {"time": timestamp, "priceUsd": "12345.67", "date": "2023-01-01"}
            $timestamp = $item['time'];
            $price = (float) $item['priceUsd'];
            $volume = $item['volume'] ?? 0;

            // Create synthetic OHLC from price data
            $binanceFormat[] = [
                $timestamp,           // Open time
                (string) $price,      // Open
                (string) $price,      // High
                (string) $price,      // Low
                (string) $price,      // Close
                (string) $volume,     // Volume
                $timestamp + 60000,   // Close time
                '0',                  // Quote asset volume
                0,                    // Number of trades
                '0',                  // Taker buy base asset volume
                '0',                  // Taker buy quote asset volume
                '0'                   // Unused field
            ];
        }

        return $binanceFormat;
    }

    private function updateRateLimitInfo($response): void
    {
        // CoinCap Pro provides rate limit headers
        $headers = $response->getHeaders();

        if (isset($headers['X-RateLimit-Remaining'])) {
            $remaining = (int) $headers['X-RateLimit-Remaining'][0];
            $this->rateLimitInfo['requests_remaining'] = $remaining;
        } else {
            $this->rateLimitInfo['requests_remaining'] = max(0, $this->rateLimitInfo['requests_remaining'] - 1);
        }
    }

    private function handleRateLimit(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response && $response->getStatusCode() === 429) {
            // Rate limited - mark as unavailable for a longer time
            $rateLimitKey = 'coincappro_rate_limited';
            $retryAfter = 600; // 10 minutes for CoinCap Pro
            Cache::put($rateLimitKey, true, now()->addSeconds($retryAfter));

            Log::warning('CoinCap Pro API rate limited', [
                'retry_after' => $retryAfter,
                'error' => $e->getMessage()
            ]);
        }
    }
}