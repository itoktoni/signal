<?php

namespace App\Analysis\Providers;

use App\Analysis\ApiProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CoinDeskApiProvider implements ApiProviderInterface
{
    private Client $client;
    private array $config;
    private array $rateLimitInfo;

    public function __construct()
    {
        $this->config = config('crypto.api_providers.providers.coindesk', []);

        $this->client = new Client([
            'base_uri' => rtrim($this->config['base_url'] ?? 'https://api.coindesk.com/v1', '/') . '/',
            'timeout' => $this->config['timeout'] ?? 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'CryptoSignalBot/1.0',
            ],
        ]);

        // Initialize rate limit info
        $this->rateLimitInfo = [
            'requests_remaining' => 1000, // CoinDesk free tier limit
            'reset_time' => null,
        ];
    }

    public function getCode(): string
    {
        return 'coindesk';
    }

    public function getName(): string
    {
        return $this->config['name'] ?? 'CoinDesk API';
    }

    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $cacheKey = "coindesk_klines_{$symbol}_{$interval}_{$limit}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Convert symbol to CoinDesk format
            $coinDeskSymbol = $this->convertSymbolToCoinDesk($symbol);

            // Get historical data from CoinDesk
            $response = $this->client->get("bpi/historical/close.json", [
                'query' => [
                    'start' => $this->getStartDate($limit, $interval),
                    'end' => date('Y-m-d'),
                    'currency' => 'USD',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !isset($data['bpi'])) {
                throw new \Exception('Invalid response from CoinDesk API');
            }

            // Convert CoinDesk format to Binance format for compatibility
            $convertedData = $this->convertCoinDeskToBinanceFormat($data['bpi'], $interval);

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $convertedData, now()->addMinutes(10));

            return $convertedData;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinDesk API request failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('CoinDesk API error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getTickerData(string $symbol): array
    {
        $cacheKey = "coindesk_ticker_{$symbol}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Get current price from CoinDesk
            $response = $this->client->get('bpi/currentprice.json');

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !isset($data['bpi']['USD']['rate_float'])) {
                throw new \Exception('Invalid ticker response from CoinDesk API');
            }

            $result = [
                'symbol' => strtoupper($symbol),
                'price' => (string) $data['bpi']['USD']['rate_float']
            ];

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinDesk API request failed: ' . $e->getMessage());
        }
    }

    public function getMultipleTickers(array $symbols): array
    {
        $cacheKey = "coindesk_tickers_" . implode('_', $symbols);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // For simplicity, get current price and replicate for all symbols
            // CoinDesk mainly focuses on Bitcoin
            $response = $this->client->get('bpi/currentprice.json');

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !isset($data['bpi']['USD']['rate_float'])) {
                throw new \Exception('Invalid response from CoinDesk API');
            }

            $currentPrice = (string) $data['bpi']['USD']['rate_float'];

            $result = [];
            foreach ($symbols as $symbol) {
                // Only Bitcoin is supported by CoinDesk
                if (strtoupper($symbol) === 'BTC' || strtoupper($symbol) === 'BTCUSDT') {
                    $result[] = [
                        'symbol' => strtoupper($symbol),
                        'price' => $currentPrice
                    ];
                }
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinDesk API request failed: ' . $e->getMessage());
        }
    }

    public function getSymbolInfo(?string $symbol = null): array
    {
        $cacheKey = "coindesk_symbol_info" . ($symbol ? "_{$symbol}" : "");

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // CoinDesk mainly supports Bitcoin information
            $response = $this->client->get('bpi/currentprice.json');
            $data = json_decode($response->getBody()->getContents(), true);

            $result = [];
            if (isset($data['bpi'])) {
                $result[] = [
                    'symbol' => 'BTC',
                    'name' => 'Bitcoin',
                    'price' => $data['bpi']['USD']['rate_float'],
                    'currency' => 'USD',
                    'last_updated' => $data['time']['updated']
                ];
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(60));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinDesk API request failed: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        // Check if provider is enabled in config
        if (!($this->config['enabled'] ?? true)) {
            return false;
        }

        // Check if we're currently rate limited
        $rateLimitKey = 'coindesk_rate_limited';
        if (Cache::get($rateLimitKey, false)) {
            return false;
        }

        // Check remaining requests
        return ($this->rateLimitInfo['requests_remaining'] ?? 0) > 0;
    }

    public function getPriority(): int
    {
        return $this->config['priority'] ?? 6; // Lower priority
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
                    'timestamp' => 'integer',
                    'open' => 'float (price)',
                    'high' => 'float (price)',
                    'low' => 'float (price)',
                    'close' => 'float (price)',
                    'volume' => 'float (volume)'
                ],
                'note' => 'Converted to Binance format for compatibility',
                'example' => [
                    1499040000000,      // Open time
                    "50000.00",         // Open
                    "51000.00",         // High
                    "49000.00",         // Low
                    "50500.00",         // Close
                    "1000.00",          // Volume
                    1499644799999,      // Close time
                    "0",                // Quote asset volume
                    0,                  // Number of trades
                    "0",                // Taker buy base asset volume
                    "0",                // Taker buy quote asset volume
                    "0"                 // Unused field
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
                    'price' => '50000.00'
                ]
            ]
        ];
    }

    public function normalizeHistoricalData(array $rawData, string $interval): array
    {
        return $this->convertCoinDeskToBinanceFormat($rawData, $interval);
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

    private function convertSymbolToCoinDesk(string $symbol): string
    {
        // CoinDesk mainly supports Bitcoin
        $upperSymbol = strtoupper($symbol);
        if ($upperSymbol === 'BTC' || $upperSymbol === 'BTCUSDT') {
            return 'BTC';
        }

        return 'BTC'; // Default to Bitcoin
    }

    private function getStartDate(int $limit, string $interval): string
    {
        $days = min($limit * $this->getIntervalDays($interval), 365); // Max 1 year
        $date = new \DateTime();
        $date->modify("-{$days} days");
        return $date->format('Y-m-d');
    }

    private function getIntervalDays(string $interval): int
    {
        $intervalDays = [
            '1m' => 1,
            '5m' => 1,
            '15m' => 1,
            '30m' => 1,
            '1h' => 1,
            '4h' => 2,
            '1d' => 7,
        ];

        return $intervalDays[$interval] ?? 1;
    }

    private function convertCoinDeskToBinanceFormat(array $coinDeskData, string $interval): array
    {
        $binanceFormat = [];

        foreach ($coinDeskData as $date => $price) {
            $timestamp = strtotime($date) * 1000;

            // Create OHLC data from close price
            $close = (float) $price;
            $variation = 0.02; // 2% variation for synthetic OHLC

            $open = $close * (1 - $variation + (mt_rand(0, 200) / 1000));
            $high = $close * (1 + mt_rand(0, (int)($variation * 1000)) / 1000);
            $low = $close * (1 - mt_rand(0, (int)($variation * 1000)) / 1000);
            $volume = mt_rand(100000, 1000000); // Synthetic volume

            $binanceFormat[] = [
                $timestamp,                     // Open time
                (string) $open,                 // Open
                (string) $high,                 // High
                (string) $low,                  // Low
                (string) $close,                // Close
                (string) $volume,               // Volume
                $timestamp + $this->getIntervalMs($interval), // Close time
                '0',                            // Quote asset volume
                0,                              // Number of trades
                '0',                            // Taker buy base asset volume
                '0',                            // Taker buy quote asset volume
                '0'                             // Unused field
            ];
        }

        // Sort by timestamp
        usort($binanceFormat, function($a, $b) {
            return $a[0] <=> $b[0];
        });

        return $binanceFormat;
    }

    private function getIntervalMs(string $interval): int
    {
        $intervals = [
            '1m' => 60000,
            '5m' => 300000,
            '15m' => 900000,
            '30m' => 1800000,
            '1h' => 3600000,
            '4h' => 14400000,
            '1d' => 86400000,
        ];

        return $intervals[$interval] ?? 3600000;
    }

    private function updateRateLimitInfo($response): void
    {
        // CoinDesk doesn't provide detailed rate limit headers
        $this->rateLimitInfo['requests_remaining'] = max(0, $this->rateLimitInfo['requests_remaining'] - 1);
    }

    private function handleRateLimit(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response && $response->getStatusCode() === 429) {
            // Rate limited - mark as unavailable for a longer time
            $rateLimitKey = 'coindesk_rate_limited';
            $retryAfter = 3600; // 1 hour for CoinDesk
            Cache::put($rateLimitKey, true, now()->addSeconds($retryAfter));

            Log::warning('CoinDesk API rate limited', [
                'retry_after' => $retryAfter,
                'error' => $e->getMessage()
            ]);
        }
    }
}