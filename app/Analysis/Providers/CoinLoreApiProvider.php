<?php

namespace App\Analysis\Providers;

use App\Analysis\ApiProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CoinLoreApiProvider implements ApiProviderInterface
{
    private Client $client;
    private array $config;
    private array $rateLimitInfo;

    public function __construct()
    {
        $this->config = config('crypto.api_providers.coinlore', []);
        $this->rateLimitInfo = [
            'requests_remaining' => $this->config['rate_limits']['requests_per_minute'] ?? 100,
            'requests_per_minute' => $this->config['rate_limits']['requests_per_minute'] ?? 100,
        ];

        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? 'https://api.coinlore.net/api',
            'timeout' => $this->config['timeout'] ?? 30,
            'headers' => [
                'User-Agent' => 'Laravel-Crypto-Analysis/1.0',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getCode(): string
    {
        return 'coinlore';
    }

    public function getName(): string
    {
        return $this->config['name'] ?? 'CoinLore API';
    }

    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $cacheKey = "coinlore_klines_{$symbol}_{$interval}_{$limit}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Check if symbol is already a numeric ID
            $coinSymbol = is_numeric($symbol) ? $symbol : $this->convertSymbolToCoinLore($symbol);

            // Convert interval to CoinLore format
            $intervalParam = $this->convertIntervalForCoinLore($interval);

            $response = $this->client->get("coin/{$coinSymbol}/", [
                'query' => [
                    'period' => $intervalParam,
                    'limit' => min($limit, 1000), // CoinLore limit
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid response from CoinLore API');
            }

            // Convert to Binance format for compatibility
            $convertedData = $this->convertCoinLoreToBinanceFormat($data);

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $convertedData, now()->addMinutes(10));

            return $convertedData;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinLore API request failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('CoinLore data processing failed', ['error' => $e->getMessage()]);
            throw new \Exception('CoinLore API data processing failed: ' . $e->getMessage());
        }
    }

    public function getTickerData(string $symbol): array
    {
        $cacheKey = "coinlore_ticker_{$symbol}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Check if symbol is already a numeric ID
            $coinSymbol = is_numeric($symbol) ? $symbol : $this->convertSymbolToCoinLore($symbol);

            $response = $this->client->get("ticker/?id={$coinSymbol}");

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data) || !isset($data[0]['price_usd'])) {
                throw new \Exception('Invalid ticker response from CoinLore API');
            }

            $tickerData = $data[0];
            $result = [
                'symbol' => strtoupper($symbol),
                'price' => (string) $tickerData['price_usd']
            ];

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinLore API request failed: ' . $e->getMessage());
        }
    }

    public function getMultipleTickers(array $symbols): array
    {
        $cacheKey = "coinlore_tickers_" . implode('_', $symbols);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $coinSymbols = [];
            foreach ($symbols as $symbol) {
                // Check if symbol is already a numeric ID
                $coinSymbols[] = is_numeric($symbol) ? $symbol : $this->convertSymbolToCoinLore($symbol);
            }
            $symbolsStr = implode(',', $coinSymbols);

            $response = $this->client->get('/tickers/', [
                'query' => [
                    'symbols' => $symbolsStr,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid response from CoinLore API');
            }

            $result = [];
            foreach ($data as $item) {
                $result[] = [
                    'symbol' => strtoupper($item['symbol'] . 'USDT'),
                    'price' => (string) $item['price_usd']
                ];
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinLore API request failed: ' . $e->getMessage());
        }
    }

    public function getSymbolInfo(?string $symbol = null): array
    {
        $cacheKey = "coinlore_symbol_info";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $params = [];

            if ($symbol) {
                // Check if symbol is already a numeric ID
                $coinSymbol = is_numeric($symbol) ? $symbol : $this->convertSymbolToCoinLore($symbol);
                $params['symbol'] = $coinSymbol;
            }

            $response = $this->client->get('/global/', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!is_array($data)) {
                throw new \Exception('Invalid response from CoinLore API');
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $data, now()->addMinutes(60));

            return $data;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('CoinLore API request failed: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        // Check if provider is enabled in config
        if (!($this->config['enabled'] ?? true)) {
            return false;
        }

        // Check if we're currently rate limited
        $rateLimitKey = 'coinlore_rate_limited';
        if (Cache::get($rateLimitKey, false)) {
            return false;
        }

        // Check remaining requests
        return ($this->rateLimitInfo['requests_remaining'] ?? 0) > 0;
    }

    public function getPriority(): int
    {
        return $this->config['priority'] ?? 3; // Lower priority
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
        return $this->convertCoinLoreToBinanceFormat($rawData);
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

    private function convertSymbolToCoinLore(string $symbol): string
    {
        // If symbol is already numeric, return as is
        if (is_numeric($symbol)) {
            return $symbol;
        }

        // Convert trading pair symbols to CoinLore format
        $mapping = [
            'BTCUSDT' => '90',
            'ETHUSDT' => '80',
            'BNBUSDT' => '2710',
            'ADAUSDT' => '257',
            'XRPUSDT' => '58',
            'SOLUSDT' => '48543',
            'DOTUSDT' => '11815',
            'DOGEUSDT' => '2',
            'AVAXUSDT' => '23210',
            'LTCUSDT' => '1',
            'LINKUSDT' => '1975',
            'MATICUSDT' => '3890',
            'ALGOUSDT' => '4030',
            'UNIUSDT' => '7083',
            'ATOMUSDT' => '3794',
            'VETUSDT' => '3077',
            'ICPUSDT' => '8916',
            'FILUSDT' => '10804',
            'TRXUSDT' => '1958',
            'ETCUSDT' => '532',
            'BTC' => '90',  // Add base symbol mapping
            'ETH' => '80',
            'BNB' => '2710',
            'ADA' => '257',
            'XRP' => '58',
            'SOL' => '48543',
            'DOT' => '11815',
            'DOGE' => '2',
            'AVAX' => '23210',
            'LTC' => '1',
            'LINK' => '1975',
            'MATIC' => '3890',
            'ALGO' => '4030',
            'UNI' => '7083',
            'ATOM' => '3794',
            'VET' => '3077',
            'ICP' => '8916',
            'FIL' => '10804',
            'TRX' => '1958',
            'ETC' => '532',
        ];

        return $mapping[$symbol] ?? '90'; // Default to BTC
    }

    private function convertIntervalForCoinLore(string $interval): string
    {
        // Convert interval to CoinLore format
        $intervalMapping = [
            '1m' => '1MIN',
            '5m' => '5MIN',
            '15m' => '15MIN',
            '30m' => '30MIN',
            '1h' => '1H',
            '4h' => '4H',
            '1d' => '1D',
        ];

        return $intervalMapping[$interval] ?? '1H';
    }

    private function createSyntheticHistoricalData(string $symbol, string $interval, int $limit): array
    {
        $binanceFormat = [];

        // Use approximate current prices for demonstration
        $approximatePrices = [
            'BTCUSDT' => 109000,
            'ETHUSDT' => 3500,
            'BNBUSDT' => 580,
            'ADAUSDT' => 0.35,
            'XRPUSDT' => 0.52,
            'SOLUSDT' => 140,
            'DOTUSDT' => 4.2,
            'DOGEUSDT' => 0.38,
            'AVAXUSDT' => 25,
            'LTCUSDT' => 65,
        ];

        $currentPrice = $approximatePrices[$symbol] ?? 100000;
        $currentTime = time() * 1000; // Current timestamp in milliseconds

        // Create synthetic historical data based on current price
        for ($i = $limit - 1; $i >= 0; $i--) { // Reverse order to get chronological data
            $timestamp = $currentTime - ($i * $this->getIntervalMilliseconds($interval));

            // Create slight price variations for realistic data
            $variation = 1 + (sin($i / 10) * 0.02); // ±2% variation
            $price = $currentPrice * $variation;

            $binanceFormat[] = [
                $timestamp,           // Open time
                (string) $price,      // Open
                (string) $price,      // High
                (string) $price,      // Low
                (string) $price,      // Close
                '1000000',            // Volume (synthetic)
                $timestamp + 60000,   // Close time
                '0',                  // Quote asset volume
                10,                   // Number of trades
                '0',                  // Taker buy base asset volume
                '0',                  // Taker buy quote asset volume
                '0'                   // Unused field
            ];
        }

        return $binanceFormat; // Already in chronological order
    }

    private function getIntervalMilliseconds(string $interval): int
    {
        $intervalMs = [
            '1m' => 60 * 1000,
            '5m' => 5 * 60 * 1000,
            '15m' => 15 * 60 * 1000,
            '30m' => 30 * 60 * 1000,
            '1h' => 60 * 60 * 1000,
            '4h' => 4 * 60 * 60 * 1000,
            '1d' => 24 * 60 * 60 * 1000,
        ];

        return $intervalMs[$interval] ?? (60 * 60 * 1000); // Default to 1h
    }

    private function getCoinIdBySymbol(string $symbol): int
    {
        // Get CoinLore coin ID by symbol
        $coinIdMapping = [
            'BTC' => 90,
            'ETH' => 80,
            'BNB' => 2710,
            'ADA' => 2577,
            'XRP' => 58,
            'SOL' => 11597,
            'DOT' => 11419,
            'DOGE' => 2,
            'AVAX' => 11597,
            'LTC' => 1,
            'LINK' => 1975,
            'MATIC' => 3890,
            'ALGO' => 4030,
            'UNI' => 7083,
            'ATOM' => 3794,
            'VET' => 3077,
            'ICP' => 8916,
            'FIL' => 10804,
            'TRX' => 1958,
            'ETC' => 532,
        ];

        return $coinIdMapping[$symbol] ?? 90; // Default to BTC
    }

    private function convertCoinLoreToBinanceFormat(array $coinloreData): array
    {
        $binanceFormat = [];

        foreach ($coinloreData as $item) {
            if (!isset($item['time'], $item['open'], $item['high'], $item['low'], $item['close'])) {
                continue;
            }

            // CoinLore format: {"time": timestamp, "open": "price", "high": "price", "low": "price", "close": "price", "volume": "volume"}
            $timestamp = $item['time'] * 1000; // Convert to milliseconds
            $open = $item['open'];
            $high = $item['high'];
            $low = $item['low'];
            $close = $item['close'];
            $volume = $item['volume'] ?? 0;

            $binanceFormat[] = [
                $timestamp,           // Open time
                (string) $open,       // Open
                (string) $high,       // High
                (string) $low,        // Low
                (string) $close,      // Close
                (string) $volume,     // Volume
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

    private function updateRateLimitInfo($response): void
    {
        // CoinLore doesn't provide detailed rate limit headers
        $this->rateLimitInfo['requests_remaining'] = max(0, $this->rateLimitInfo['requests_remaining'] - 1);
    }

    private function handleRateLimit(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response && $response->getStatusCode() === 429) {
            // Rate limited - mark as unavailable for a longer time
            $rateLimitKey = 'coinlore_rate_limited';
            $retryAfter = 600; // 10 minutes for CoinLore
            Cache::put($rateLimitKey, true, now()->addSeconds($retryAfter));

            Log::warning('CoinLore API rate limited', [
                'retry_after' => $retryAfter,
                'error' => $e->getMessage()
            ]);
        }
    }
}