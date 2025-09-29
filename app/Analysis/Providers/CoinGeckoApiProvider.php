<?php

namespace App\Analysis\Providers;

use App\Analysis\ApiProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CoinGeckoApiProvider implements ApiProviderInterface
{
    private Client $client;
    private array $config;
    private array $rateLimitInfo;

    public function __construct()
    {
        // $this->config = config('crypto.api_providers.providers.coingecko', []);
        // $this->rateLimitInfo = [
        //     'requests_remaining' => $this->config['rate_limits']['requests_per_minute'] ?? 50,
        //     'requests_per_minute' => $this->config['rate_limits']['requests_per_minute'] ?? 50,
        // ];

        $headers = [
            'User-Agent' => 'Laravel-Crypto-Analysis/1.0',
            'Accept' => 'application/json',
        ];

        // Add API key if provided
        if (!empty($this->config['api_key'])) {
            $headers['x-cg-demo-api-key'] = $this->config['api_key'];
        }

        $this->client = new Client([
            'base_uri' => rtrim($this->config['base_url'] ?? 'https://api.coingecko.com/api/v3/', '/') . '/',
            'timeout' => $this->config['timeout'] ?? 30,
            'headers' => $headers,
        ]);
    }

    public function getCode(): string
    {
        return 'coingecko';
    }

    public function getName(): string
    {
        return $this->config['name'] ?? 'CoinGecko API';
    }

    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $cacheKey = "coingecko_klines_{$symbol}_{$interval}_{$limit}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Convert symbol format (BTCUSDT -> bitcoin, tether)
            $coinId = $this->convertSymbolToCoinGeckoId($symbol);

            // Convert interval to days for CoinGecko
            $days = $this->convertIntervalToDays($interval, $limit);

            $response = $this->client->get("coins/{$coinId}/ohlc", [
                'query' => [
                    'vs_currency' => 'usd',
                    'days' => min($days, 90), // CoinGecko free plan supports up to 90 days
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid response from CoinGecko API');
            }

            // Convert CoinGecko format to Binance format for compatibility
            $convertedData = $this->convertCoinGeckoToBinanceFormat($data, $interval);

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $convertedData, now()->addMinutes(5));

            return $convertedData;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinGecko API request failed: ' . $e->getMessage());
        }
    }

    public function getTickerData(string $symbol): array
    {
        $cacheKey = "coingecko_ticker_{$symbol}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Get coin ID for the symbol
            $coinIds = $this->getCoinIdsFromSymbol($symbol);

            if (empty($coinIds)) {
                throw new \Exception("No CoinGecko coin ID found for symbol: {$symbol}");
            }

            $response = $this->client->get('/simple/price', [
                'query' => [
                    'ids' => implode(',', $coinIds),
                    'vs_currencies' => 'usd',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data)) {
                throw new \Exception('Invalid price response from CoinGecko API');
            }

            // Find the price for the first available coin
            $result = [];
            foreach ($coinIds as $coinId) {
                if (isset($data[$coinId]['usd'])) {
                    $result = [
                        'symbol' => strtoupper($symbol),
                        'price' => (string) $data[$coinId]['usd']
                    ];
                    break;
                }
            }

            if (empty($result)) {
                throw new \Exception('No price found for symbol: ' . $symbol);
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinGecko API request failed: ' . $e->getMessage());
        }
    }

    public function getMultipleTickers(array $symbols): array
    {
        $cacheKey = "coingecko_tickers_" . implode('_', $symbols);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $coinIds = [];
            foreach ($symbols as $symbol) {
                $ids = $this->getCoinIdsFromSymbol($symbol);
                $coinIds = array_merge($coinIds, $ids);
            }
            $coinIds = array_unique($coinIds);

            if (empty($coinIds)) {
                throw new \Exception("No CoinGecko coin IDs found for symbols: " . implode(', ', $symbols));
            }

            $response = $this->client->get('/simple/price', [
                'query' => [
                    'ids' => implode(',', $coinIds),
                    'vs_currencies' => 'usd',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data)) {
                throw new \Exception('Invalid response from CoinGecko API');
            }

            $result = [];
            foreach ($symbols as $symbol) {
                $ids = $this->getCoinIdsFromSymbol($symbol);
                foreach ($ids as $coinId) {
                    if (isset($data[$coinId]['usd'])) {
                        $result[] = [
                            'symbol' => strtoupper($symbol),
                            'price' => (string) $data[$coinId]['usd']
                        ];
                        break;
                    }
                }
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinGecko API request failed: ' . $e->getMessage());
        }
    }

    public function getSymbolInfo(?string $symbol = null): array
    {
        // $cacheKey = "coingecko_symbol_info";

        // // Check cache first
        // $cached = Cache::get($cacheKey);
        // if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
        //     return $cached;
        // }

        try {
            // $params = [];
            // if ($symbol) {
            //     $coinIds = $this->getCoinIdsFromSymbol($symbol);
            //     if (!empty($coinIds)) {
            //         $params['ids'] = implode(',', $coinIds);
            //     }
            // }

            $curl = curl_init();

            curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.coingecko.com/api/v3/coins/list", // Ganti dari pro-api ke api
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            // Hapus header API key jika pakai versi gratis
            ]);


            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            $data = json_decode($response, true);

            // Update rate limit info
            //$this->updateRateLimitInfo($response);

            // Cache the result
            // Cache::put($cacheKey, $data, now()->addMinutes(30));

            return $data;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinGecko API request failed: ' . $e->getMessage());
        }
    }

    public function getCurrentPrice(string $symbol): float
    {
        $cacheKey = "coingecko_price_{$symbol}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Get coin ID for the symbol
            $coinIds = $this->getCoinIdsFromSymbol($symbol);

            if (empty($coinIds)) {
                throw new \Exception("No CoinGecko coin ID found for symbol: {$symbol}");
            }

            $response = $this->client->get('coins/list', [
                'query' => [
                    'ids' => implode(',', $coinIds),
                    'vs_currencies' => 'usd',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data)) {
                throw new \Exception('Invalid price response from CoinGecko API');
            }

            // Find the price for the first available coin
            $price = null;
            foreach ($coinIds as $coinId) {
                if (isset($data[$coinId]['usd'])) {
                    $price = (float) $data[$coinId]['usd'];
                    break;
                }
            }

            if ($price === null) {
                throw new \Exception('No price found for symbol: ' . $symbol);
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $price, now()->addMinutes(2));

            return $price;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinGecko API request failed: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        // Check if provider is enabled in config
        if (!($this->config['enabled'] ?? true)) {
            return false;
        }

        // Check if we're currently rate limited
        $rateLimitKey = 'coingecko_rate_limited';
        if (Cache::get($rateLimitKey, false)) {
            return false;
        }

        // Check remaining requests
        return ($this->rateLimitInfo['requests_remaining'] ?? 0) > 0;
    }

    public function getPriority(): int
    {
        return $this->config['priority'] ?? 1;
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
                    'close' => 'float (price)'
                ],
                'note' => 'Converted to Binance format for compatibility',
                'example' => [
                    1499040000000,      // Open time
                    "0.01634790",       // Open
                    "0.80000000",       // High
                    "0.01575800",       // Low
                    "0.01577100",       // Close
                    "148976.11427815",  // Volume (estimated)
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
        // Convert CoinGecko format to Binance format
        return $this->convertCoinGeckoToBinanceFormat($rawData, $interval);
    }

    public function normalizeTickerData(array $rawData): array
    {
        // Normalize CoinGecko ticker data to standard format
        $normalized = [];

        foreach ($rawData as $item) {
            $normalized[] = [
                'symbol' => $item['symbol'] ?? 'UNKNOWN',
                'price' => (string) ($item['price'] ?? 0)
            ];
        }

        return $normalized;
    }

    private function convertSymbolToCoinGeckoId(string $symbol): string
    {
        // Get mapping from configuration
        $coinMapping = config('crypto.coin_api_mapping.api_limitations.coingecko.coin_id_mapping', []);

        // Return mapped ID or default to bitcoin
        return $coinMapping[$symbol] ?? 'bitcoin';
    }

    private function convertIntervalToDays(string $interval, int $limit): int
    {
        // Convert interval to approximate days for CoinGecko
        // Use fixed days parameter that works with CoinGecko free API
        $intervalDays = [
            '1m' => 1,
            '5m' => 7,    // Minimum 7 days for short intervals
            '15m' => 14,  // Minimum 14 days for medium intervals
            '30m' => 30,  // 30 days for longer intervals
            '1h' => 30,   // 30 days for 1h interval
            '4h' => 90,   // 90 days for 4h interval (max for free)
            '1d' => 90,   // 90 days for daily (max for free)
        ];

        $days = $intervalDays[$interval] ?? 30;

        // Ensure minimum days for sufficient data points
        return max($days, 7);
    }

    private function convertCoinGeckoToBinanceFormat(array $coingeckoData, string $interval): array
    {
        $binanceFormat = [];

        foreach ($coingeckoData as $item) {
            if (!is_array($item) || count($item) < 5) {
                continue;
            }

            // CoinGecko format: [timestamp, open, high, low, close]
            // Binance format: [open_time, open, high, low, close, volume, close_time, ...]
            $timestamp = $item[0];
            $open = $item[1];
            $high = $item[2];
            $low = $item[3];
            $close = $item[4];

            $binanceFormat[] = [
                $timestamp,           // Open time
                (string) $open,       // Open
                (string) $high,       // High
                (string) $low,        // Low
                (string) $close,      // Close
                '0',                  // Volume (not provided by CoinGecko)
                $timestamp + 60000,   // Close time (approximate)
                '0',                  // Quote asset volume
                0,                    // Number of trades
                '0',                  // Taker buy base asset volume
                '0',                  // Taker buy quote asset volume
                '0'                   // Unused field
            ];
        }

        return $binanceFormat;
    }

    private function getCoinIdsFromSymbol(string $symbol): array
    {
        $coinId = $this->convertSymbolToCoinGeckoId($symbol);
        return [$coinId];
    }

    private function updateRateLimitInfo($response): void
    {
        // CoinGecko doesn't provide detailed rate limit headers like Binance
        // We'll decrement our own counter
        $this->rateLimitInfo['requests_remaining'] = max(0, $this->rateLimitInfo['requests_remaining'] - 1);
    }

    private function handleRateLimit(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response && $response->getStatusCode() === 429) {
            // Rate limited - mark as unavailable for a longer time than Binance
            $rateLimitKey = 'coingecko_rate_limited';
            $retryAfter = 300; // 5 minutes for CoinGecko
            Cache::put($rateLimitKey, true, now()->addSeconds($retryAfter));

            Log::warning('CoinGecko API rate limited', [
                'retry_after' => $retryAfter,
                'error' => $e->getMessage()
            ]);
        }
    }
}