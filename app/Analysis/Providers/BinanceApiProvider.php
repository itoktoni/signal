<?php

namespace App\Analysis\Providers;

use App\Analysis\ApiProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BinanceApiProvider implements ApiProviderInterface
{
    private Client $client;
    private array $config;
    private array $rateLimitInfo;

    public function __construct()
    {
        $this->config = config('crypto.api_providers.providers.binance', []);
        $this->rateLimitInfo = [
            'requests_remaining' => $this->config['rate_limits']['requests_per_minute'] ?? 1200,
            'requests_per_minute' => $this->config['rate_limits']['requests_per_minute'] ?? 1200,
        ];

        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? 'https://api.binance.com',
            'timeout' => $this->config['timeout'] ?? 30,
            'headers' => [
                'User-Agent' => 'Laravel-Crypto-Analysis/1.0',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getCode(): string
    {
        return 'binance';
    }

    public function getName(): string
    {
        return $this->config['name'] ?? 'Binance API';
    }

    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $cacheKey = "binance_klines_{$symbol}_{$interval}_{$limit}";

        // Check cache first (cache for 1 minute to reduce API calls)
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $response = $this->client->get('/api/v3/klines', [
                'query' => [
                    'symbol' => strtoupper($symbol),
                    'interval' => $interval,
                    'limit' => min($limit, 1000), // Binance max limit is 1000
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid response from Binance API');
            }

            // Update rate limit info from response headers
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $data, now()->addSeconds(60));

            return $data;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('Binance API request failed: ' . $e->getMessage());
        }
    }

    public function getTickerData(string $symbol): array
    {
        $cacheKey = "binance_ticker_{$symbol}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $response = $this->client->get('/api/v3/ticker/price', [
                'query' => [
                    'symbol' => strtoupper($symbol),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['price']) || !is_numeric($data['price'])) {
                throw new \Exception('Invalid price response from Binance API');
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $data, now()->addSeconds(30));

            return $data;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('Binance API request failed: ' . $e->getMessage());
        }
    }

    public function getMultipleTickers(array $symbols): array
    {
        $cacheKey = "binance_tickers_" . implode('_', $symbols);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $symbolStr = implode(',', array_map('strtoupper', $symbols));
            $response = $this->client->get('/api/v3/ticker/price', [
                'query' => [
                    'symbols' => "[{$symbolStr}]",
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid response from Binance API');
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $data, now()->addSeconds(30));

            return $data;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('Binance API request failed: ' . $e->getMessage());
        }
    }

    public function getSymbolInfo(?string $symbol = null): array
    {
        $cacheKey = "binance_symbol_info";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $params = [];
            if ($symbol) {
                $params['symbol'] = strtoupper($symbol);
            }

            $response = $this->client->get('/api/v3/exchangeInfo', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['symbols']) || !is_array($data['symbols'])) {
                throw new \Exception('Invalid response from Binance API');
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $data, now()->addMinutes(60));

            return $data;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('Binance API request failed: ' . $e->getMessage());
        }
    }

    public function getCurrentPrice(string $symbol): float
    {
        $cacheKey = "binance_price_{$symbol}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $response = $this->client->get('/api/v3/ticker/price', [
                'query' => [
                    'symbol' => strtoupper($symbol),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['price']) || !is_numeric($data['price'])) {
                throw new \Exception('Invalid price response from Binance API');
            }

            $price = (float) $data['price'];

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $price, now()->addSeconds(30));

            return $price;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('Binance API request failed: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        // Check if provider is enabled in config
        if (!($this->config['enabled'] ?? true)) {
            return false;
        }

        // Check if we're currently rate limited
        $rateLimitKey = 'binance_rate_limited';
        if (Cache::get($rateLimitKey, false)) {
            return false;
        }

        // Check remaining requests
        return ($this->rateLimitInfo['requests_remaining'] ?? 0) > 0;
    }

    public function getPriority(): int
    {
        return $this->config['priority'] ?? 0;
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
                    'open_time' => 'integer (timestamp)',
                    'open' => 'string (price)',
                    'high' => 'string (price)',
                    'low' => 'string (price)',
                    'close' => 'string (price)',
                    'volume' => 'string (volume)',
                    'close_time' => 'integer (timestamp)',
                    'quote_asset_volume' => 'string',
                    'number_of_trades' => 'integer',
                    'taker_buy_base_asset_volume' => 'string',
                    'taker_buy_quote_asset_volume' => 'string',
                    'unused' => 'string'
                ],
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
        // Binance data is already in the expected format
        return $rawData;
    }

    public function normalizeTickerData(array $rawData): array
    {
        // Binance ticker data is already normalized
        return $rawData;
    }

    private function updateRateLimitInfo($response): void
    {
        $headers = $response->getHeaders();

        // Update rate limit info from Binance headers
        if (isset($headers['X-MBX-USED-WEIGHT-1m'])) {
            $used = (int) $headers['X-MBX-USED-WEIGHT-1m'][0];
            $this->rateLimitInfo['requests_remaining'] = max(0, ($this->rateLimitInfo['requests_per_minute'] ?? 1200) - $used);
        }
    }

    private function handleRateLimit(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response && $response->getStatusCode() === 429) {
            // Rate limited - mark as unavailable for a short time
            $rateLimitKey = 'binance_rate_limited';
            $retryAfter = $response->getHeader('Retry-After')[0] ?? 60;
            Cache::put($rateLimitKey, true, now()->addSeconds((int) $retryAfter));

            Log::warning('Binance API rate limited', [
                'retry_after' => $retryAfter,
                'error' => $e->getMessage()
            ]);
        }
    }
}