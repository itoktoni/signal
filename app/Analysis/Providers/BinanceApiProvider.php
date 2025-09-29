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
            'requests_remaining' => 1200, // Binance spot API limit
            'requests_per_minute' => 1200,
        ];

        $headers = [
            'User-Agent' => 'Laravel-Crypto-Analysis/1.0',
            'Accept' => 'application/json',
        ];

        $this->client = new Client([
            'base_uri' => $this->config['base_url'] ?? 'https://api.binance.com/api/v3/',
            'timeout' => 30,
            'headers' => $headers,
        ]);
    }

    public function getCode(): string
    {
        return 'binance';
    }

    public function getName(): string
    {
        return 'Binance API';
    }

    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200): array
    {
        $cacheKey = "binance_klines_{$symbol}_{$interval}_{$limit}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            // Convert symbol to Binance format (e.g., 'bitcoin' -> 'BTCUSDT')
            $binanceSymbol = $this->convertToBinanceSymbol($symbol);

            // Binance klines endpoint
            $response = $this->client->get('klines', [
                'query' => [
                    'symbol' => $binanceSymbol,
                    'interval' => $this->convertIntervalToBinanceFormat($interval),
                    'limit' => min($limit, 1000), // Binance max 1000
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !is_array($data)) {
                throw new \Exception('Invalid klines response from Binance API');
            }

            // Convert Binance format to standard format
            $convertedData = $this->convertBinanceToStandardFormat($data);

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $convertedData, now()->addMinutes(5));

            return $convertedData;

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
        if ($cached) {
            return $cached;
        }

        try {
            $binanceSymbol = $this->convertToBinanceSymbol($symbol);

            $response = $this->client->get('ticker/price', [
                'query' => [
                    'symbol' => $binanceSymbol,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !isset($data['price'])) {
                throw new \Exception('Invalid ticker response from Binance API');
            }

            $result = [
                'symbol' => $data['symbol'],
                'price' => $data['price']
            ];

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(1));

            return $result;

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
        if ($cached) {
            return $cached;
        }

        try {
            // Convert all symbols to Binance format
            $binanceSymbols = array_map([$this, 'convertToBinanceSymbol'], $symbols);

            $response = $this->client->get('ticker/price');

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data)) {
                throw new \Exception('Invalid multiple tickers response from Binance API');
            }

            $result = [];

            foreach ($data as $ticker) {
                if (in_array($ticker['symbol'], $binanceSymbols)) {
                    $result[] = [
                        'symbol' => $ticker['symbol'],
                        'price' => $ticker['price']
                    ];
                }
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addMinutes(1));

            return $result;

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
        if ($cached) {
            return $cached;
        }

        try {
            $response = $this->client->get('exchangeInfo');

            $data = json_decode($response->getBody()->getContents(), true);

            if (empty($data) || !isset($data['symbols'])) {
                throw new \Exception('Invalid exchange info response from Binance API');
            }

            $symbols = $data['symbols'];

            if ($symbol) {
                $symbols = array_filter($symbols, function($s) use ($symbol) {
                    return strtoupper($s['symbol']) === strtoupper($symbol);
                });
            }

            // Update rate limit info
            $this->updateRateLimitInfo($response);

            // Cache the result
            Cache::put($cacheKey, $symbols, now()->addMinutes(30));

            return $symbols;

        } catch (RequestException $e) {
            $this->handleRateLimit($e);
            throw new \Exception('Binance API request failed: ' . $e->getMessage());
        }
    }

    public function getCurrentPrice(string $symbol): float
    {
        $ticker = $this->getTickerData($symbol);
        return (float) $ticker['price'];
    }

    public function isAvailable(): bool
    {
        return true; // Binance is generally very reliable
    }

    public function getPriority(): int
    {
        return 1; // Lower priority than CoinGecko (0)
    }

    public function getRateLimitInfo(): array
    {
        return [
            'requests_remaining' => 1200, // Binance spot API
            'requests_per_minute' => 1200,
        ];
    }

    public function getDataFormat(): array
    {
        return [
            'historical' => [
                'type' => 'array',
                'structure' => [
                    'timestamp' => 'integer (milliseconds)',
                    'open' => 'string (price)',
                    'high' => 'string (price)',
                    'low' => 'string (price)',
                    'close' => 'string (price)',
                    'volume' => 'string',
                    'close_time' => 'integer (milliseconds)',
                    'quote_asset_volume' => 'string',
                    'number_of_trades' => 'integer',
                    'taker_buy_base_asset_volume' => 'string',
                    'taker_buy_quote_asset_volume' => 'string',
                    'unused_field' => 'string'
                ],
                'note' => 'Standard Binance kline format',
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
        return $this->convertBinanceToStandardFormat($rawData);
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

    private function convertIntervalToBinanceFormat(string $interval): string
    {
        $mapping = [
            '1m' => '1m',
            '3m' => '3m',
            '5m' => '5m',
            '15m' => '15m',
            '30m' => '30m',
            '1h' => '1h',
            '2h' => '2h',
            '4h' => '4h',
            '6h' => '6h',
            '8h' => '8h',
            '12h' => '12h',
            '1d' => '1d',
            '3d' => '3d',
            '1w' => '1w',
            '1M' => '1M',
        ];

        return $mapping[$interval] ?? '1h';
    }

    private function convertToBinanceSymbol(string $symbol): string
    {
        // If it's already a Binance-style symbol (contains USDT, BTC, etc.), return as-is
        if (preg_match('/(USDT|BTC|ETH|BNB|BUSD)$/', strtoupper($symbol))) {
            return strtoupper($symbol);
        }

        // Convert CoinGecko-style symbols to Binance format
        $coingeckoToBinance = [
            'bitcoin' => 'BTCUSDT',
            'ethereum' => 'ETHUSDT',
            'binancecoin' => 'BNBUSDT',
            'cardano' => 'ADAUSDT',
            'ripple' => 'XRPUSDT',
            'solana' => 'SOLUSDT',
            'polkadot' => 'DOTUSDT',
            'dogecoin' => 'DOGEUSDT',
            'avalanche-2' => 'AVAXUSDT',
            'litecoin' => 'LTCUSDT',
            'chainlink' => 'LINKUSDT',
            'matic-network' => 'MATICUSDT',
            'algorand' => 'ALGOUSDT',
            'uniswap' => 'UNIUSDT',
            'cosmos' => 'ATOMUSDT',
            'vechain' => 'VETUSDT',
            'internet-computer' => 'ICPUSDT',
            'filecoin' => 'FILUSDT',
            'tron' => 'TRXUSDT',
            'ethereum-classic' => 'ETCUSDT',
        ];

        $lowerSymbol = strtolower($symbol);
        if (isset($coingeckoToBinance[$lowerSymbol])) {
            return $coingeckoToBinance[$lowerSymbol];
        }

        // If no mapping found, try to convert common patterns
        // e.g., 'btc' -> 'BTCUSDT', 'eth' -> 'ETHUSDT'
        $upperSymbol = strtoupper($symbol);
        if (in_array($upperSymbol, ['BTC', 'ETH', 'BNB', 'ADA', 'XRP', 'SOL', 'DOT', 'DOGE', 'AVAX', 'LTC'])) {
            return $upperSymbol . 'USDT';
        }

        // Last resort: assume it's already a valid symbol
        return strtoupper($symbol);
    }

    private function convertBinanceToStandardFormat(array $binanceData): array
    {
        $standardFormat = [];

        foreach ($binanceData as $kline) {
            if (!is_array($kline) || count($kline) < 6) {
                continue;
            }

            // Binance format is already what we want for standard format
            $standardFormat[] = [
                (int) $kline[0],        // Open time
                (string) $kline[1],     // Open
                (string) $kline[2],     // High
                (string) $kline[3],     // Low
                (string) $kline[4],     // Close
                (string) $kline[5],     // Volume
                (int) $kline[6],        // Close time
                (string) ($kline[7] ?? '0'),     // Quote asset volume
                (int) ($kline[8] ?? 0),          // Number of trades
                (string) ($kline[9] ?? '0'),     // Taker buy base asset volume
                (string) ($kline[10] ?? '0'),    // Taker buy quote asset volume
                (string) ($kline[11] ?? '0')     // Unused field
            ];
        }

        return $standardFormat;
    }

    private function updateRateLimitInfo($response): void
    {
        // Binance provides rate limit headers, but for simplicity we'll track requests
        // In a production environment, you'd parse the actual headers
    }

    private function handleRateLimit(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response && $response->getStatusCode() === 429) {
            // Rate limited
            $rateLimitKey = 'binance_rate_limited';
            $retryAfter = 60; // 1 minute for Binance
            Cache::put($rateLimitKey, true, now()->addSeconds($retryAfter));

            Log::warning('Binance API rate limited', [
                'retry_after' => $retryAfter,
                'error' => $e->getMessage()
            ]);
        }
    }
}