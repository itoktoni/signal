<?php

namespace App\Analysis\Providers;

use App\Analysis\ApiProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FreeCryptoApiProvider implements ApiProviderInterface
{
    private Client $client;
    private array $config;
    private array $rateLimitInfo;

    public function __construct()
    {
        $this->config = config('crypto.api_providers.providers.freecryptoapi', []);
        $this->rateLimitInfo = [
            'requests_remaining' => $this->config['rate_limits']['requests_per_minute'] ?? 100,
            'requests_per_minute' => $this->config['rate_limits']['requests_per_minute'] ?? 100,
        ];

        $headers = [
            'User-Agent' => 'Laravel-Crypto-Analysis/1.0',
            'Accept' => 'application/json',
        ];

        // Add API key if provided
        if (!empty($this->config['api_key'])) {
            $headers['X-API-Key'] = $this->config['api_key'];
        }

        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? 'https://freecryptoapi.com/api/v1',
            'timeout' => $this->config['timeout'] ?? 30,
            'headers' => $headers,
        ]);
    }

    public function getCode(): string
    {
        return 'freecryptoapi';
    }

    public function getName(): string
    {
        $apiKey = $this->config['api_key'] ?? '';
        $keyPreview = !empty($apiKey) ? substr($apiKey, 0, 8) . '...' : 'No Key';
        return "FreeCryptoAPI ({$keyPreview})";
    }

    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $cacheKey = "freecryptoapi_klines_{$symbol}_{$interval}_{$limit}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            // Convert symbol format (BTCUSDT -> BTC-USDT)
            $formattedSymbol = $this->formatSymbolForFreeCryptoAPI($symbol);

            // Convert interval to FreeCryptoAPI format
            $intervalParam = $this->convertIntervalForFreeCryptoAPI($interval);

            $response = $this->client->get('/candles', [
                'query' => [
                    'symbol' => $formattedSymbol,
                    'interval' => $intervalParam,
                    'limit' => min($limit, 500), // FreeCryptoAPI limit
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid response from FreeCryptoAPI');
            }

            // Convert to Binance format for compatibility
            $convertedData = $this->convertFreeCryptoToBinanceFormat($data);

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $convertedData, now()->addMinutes(10));

            return $convertedData;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('FreeCryptoAPI request failed: ' . $e->getMessage());
        }
    }

    public function getTickerData(string $symbol): array
    {
        $cacheKey = "freecryptoapi_ticker_{$symbol}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $formattedSymbol = $this->formatSymbolForFreeCryptoAPI($symbol);

            $response = $this->client->get('/ticker', [
                'query' => [
                    'symbol' => $formattedSymbol,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !isset($data['price'])) {
                throw new \Exception('Invalid ticker response from FreeCryptoAPI');
            }

            $result = [
                'symbol' => strtoupper($symbol),
                'price' => (string) $data['price']
            ];

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('FreeCryptoAPI request failed: ' . $e->getMessage());
        }
    }

    public function getMultipleTickers(array $symbols): array
    {
        $cacheKey = "freecryptoapi_tickers_" . implode('_', $symbols);

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $formattedSymbols = array_map([$this, 'formatSymbolForFreeCryptoAPI'], $symbols);

            $response = $this->client->get('/tickers', [
                'query' => [
                    'symbols' => implode(',', $formattedSymbols),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid response from FreeCryptoAPI');
            }

            $result = [];
            foreach ($data as $item) {
                $result[] = [
                    'symbol' => $item['symbol'] ?? 'UNKNOWN',
                    'price' => (string) ($item['price'] ?? 0)
                ];
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(2));

            return $result;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('FreeCryptoAPI request failed: ' . $e->getMessage());
        }
    }

    public function getSymbolInfo(?string $symbol = null): array
    {
        $cacheKey = "freecryptoapi_symbol_info";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && config('crypto.api_providers.fallback_enabled', true)) {
            return $cached;
        }

        try {
            $params = [];

            if ($symbol) {
                $formattedSymbol = $this->formatSymbolForFreeCryptoAPI($symbol);
                $params['symbol'] = $formattedSymbol;
            }

            $response = $this->client->get('/symbols', [
                'query' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!is_array($data)) {
                throw new \Exception('Invalid response from FreeCryptoAPI');
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $data, now()->addMinutes(60));

            return $data;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('FreeCryptoAPI request failed: ' . $e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        // Check if provider is enabled in config
        if (!($this->config['enabled'] ?? true)) {
            return false;
        }

        // Check if we're currently rate limited
        $rateLimitKey = 'freecryptoapi_rate_limited';
        if (Cache::get($rateLimitKey, false)) {
            return false;
        }

        // Check remaining requests
        return ($this->rateLimitInfo['requests_remaining'] ?? 0) > 0;
    }

    public function getPriority(): int
    {
        return $this->config['priority'] ?? 2; // Lower priority than Binance and CoinGecko
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
        return $this->convertFreeCryptoToBinanceFormat($rawData);
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

    private function formatSymbolForFreeCryptoAPI(string $symbol): string
    {
        // Convert BTCUSDT to BTC-USDT format
        return preg_replace('/(BTC|ETH|BNB|ADA|XRP|SOL|DOT|DOGE|AVAX|LTC|LINK|MATIC|ALGO|UNI|ATOM|VET|ICP|FIL|TRX|ETC)(USDT)$/i', '$1-$2', $symbol);
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

    private function convertIntervalForFreeCryptoAPI(string $interval): string
    {
        // Convert interval to FreeCryptoAPI format
        $intervalMapping = [
            '1m' => '1m',
            '5m' => '5m',
            '15m' => '15m',
            '30m' => '30m',
            '1h' => '1h',
            '4h' => '4h',
            '1d' => '1d',
        ];

        return $intervalMapping[$interval] ?? '1h';
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
        // This is a simplified approach for demonstration
        for ($i = 0; $i < $limit; $i++) {
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

        return array_reverse($binanceFormat); // Reverse to get chronological order
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

    private function convertCoinCapToBinanceFormat(array $coincapData): array
    {
        $binanceFormat = [];

        foreach ($coincapData as $item) {
            if (!isset($item['priceUsd'], $item['time'])) {
                continue;
            }

            // CoinCap format: {"time": timestamp, "priceUsd": "12345.67", "date": "2023-01-01"}
            // We need to create OHLC data from price data
            $timestamp = $item['time'];
            $price = (float) $item['priceUsd'];
            $volume = $item['volume'] ?? 0;

            // For simplicity, create synthetic OHLC (same price for open/high/low/close)
            // In a real implementation, you'd need to aggregate price data
            $binanceFormat[] = [
                $timestamp,           // Open time
                (string) $price,      // Open
                (string) $price,      // High
                (string) $price,      // Low
                (string) $price,      // Close
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

    private function convertFreeCryptoToBinanceFormat(array $freecryptoData): array
    {
        $binanceFormat = [];

        foreach ($freecryptoData as $item) {
            if (!is_array($item) || count($item) < 5) {
                continue;
            }

            // FreeCryptoAPI format: [timestamp, open, high, low, close, volume]
            // Binance format: [open_time, open, high, low, close, volume, close_time, ...]
            $timestamp = $item[0];
            $open = $item[1];
            $high = $item[2];
            $low = $item[3];
            $close = $item[4];
            $volume = $item[5] ?? 0;

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
        // FreeCryptoAPI doesn't provide detailed rate limit headers
        $this->rateLimitInfo['requests_remaining'] = max(0, $this->rateLimitInfo['requests_remaining'] - 1);
    }

    private function handleRateLimit(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response && $response->getStatusCode() === 429) {
            // Rate limited - mark as unavailable for a longer time
            $rateLimitKey = 'freecryptoapi_rate_limited';
            $retryAfter = 600; // 10 minutes for FreeCryptoAPI
            Cache::put($rateLimitKey, true, now()->addSeconds($retryAfter));

            Log::warning('FreeCryptoAPI rate limited', [
                'retry_after' => $retryAfter,
                'error' => $e->getMessage()
            ]);
        }
    }
}