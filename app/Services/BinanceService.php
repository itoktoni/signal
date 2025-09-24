<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CryptoSymbol;

class BinanceService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('crypto.binance_api');
    }

    /**
     * Fetch exchange info from Binance API
     */
    public function fetchExchangeInfo()
    {
        $url = $this->config['base_url'] . $this->config['exchange_info_endpoint'];

        try {
            $response = Http::timeout($this->config['timeout'])
                ->retry($this->config['retry_attempts'], $this->config['retry_delay'])
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Binance API Error', [
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Binance API Exception', [
                'url' => $url,
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Fetch ticker price for a specific symbol
     */
    public function fetchTickerPrice($symbol)
    {
        $url = $this->config['base_url'] . $this->config['ticker_endpoint'];
        $params = ['symbol' => strtoupper($symbol)];

        try {
            $response = Http::timeout($this->config['timeout'])
                ->retry($this->config['retry_attempts'], $this->config['retry_delay'])
                ->get($url, $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Binance Ticker API Error', [
                'symbol' => $symbol,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Binance Ticker API Exception', [
                'symbol' => $symbol,
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get ticker price with fallback to demo data
     */
    public function getTickerPriceWithFallback($symbol)
    {
        $priceData = $this->fetchTickerPrice($symbol);

        if ($priceData) {
            return $priceData;
        }

        // Return demo/fallback price data
        Log::info("Using fallback price data for {$symbol}");

        return [
            'symbol' => strtoupper($symbol),
            'price' => $this->getDemoPrice($symbol),
            'priceChange' => '0.00',
            'priceChangePercent' => '0.00',
            'weightedAvgPrice' => $this->getDemoPrice($symbol),
            'prevClosePrice' => $this->getDemoPrice($symbol),
            'lastPrice' => $this->getDemoPrice($symbol),
            'bidPrice' => $this->getDemoPrice($symbol),
            'askPrice' => $this->getDemoPrice($symbol),
            'openPrice' => $this->getDemoPrice($symbol),
            'highPrice' => $this->getDemoPrice($symbol),
            'lowPrice' => $this->getDemoPrice($symbol),
            'volume' => '1000000.00',
            'quoteAssetVolume' => '1000000.00',
            'openTime' => now()->timestamp * 1000,
            'closeTime' => now()->timestamp * 1000,
            'count' => 1000,
            'is_fallback' => true
        ];
    }

    /**
     * Get demo price for common symbols
     */
    private function getDemoPrice($symbol)
    {
        $demoPrices = [
            'HYPEUSDT' => '0.00000123',
            'BTCUSDT' => '60000.00',
            'ETHUSDT' => '3000.00',
            'BNBUSDT' => '400.00',
            'ADAUSDT' => '0.35',
            'SOLUSDT' => '150.00',
            'XRPUSDT' => '0.50',
            'DOTUSDT' => '5.00',
            'AVAXUSDT' => '25.00',
            'MATICUSDT' => '0.80',
            'LINKUSDT' => '10.00',
            'UNIUSDT' => '8.00',
            'LTCUSDT' => '70.00',
            'ALGOUSDT' => '0.15',
            'ATOMUSDT' => '7.00',
        ];

        return $demoPrices[strtoupper($symbol)] ?? '1.00';
    }

    /**
     * Fetch klines (candlestick) data
     */
    public function fetchKlines($symbol, $interval = '1h', $limit = 100)
    {
        $url = $this->config['base_url'] . $this->config['klines_endpoint'];
        $params = [
            'symbol' => strtoupper($symbol),
            'interval' => $interval,
            'limit' => $limit
        ];

        try {
            $response = Http::timeout($this->config['timeout'])
                ->get($url, $params);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Binance Klines API Exception', [
                'symbol' => $symbol,
                'interval' => $interval,
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Process and store symbols from exchange info
     */
    public function processAndStoreSymbols($exchangeInfo)
    {
        if (!$exchangeInfo || !isset($exchangeInfo['symbols'])) {
            Log::error('Invalid exchange info data');
            return false;
        }

        $symbolFilters = config('crypto.symbol_filters');
        $processedCount = 0;
        $maxSymbols = $symbolFilters['max_symbols'];

        foreach ($exchangeInfo['symbols'] as $symbolData) {
            if ($processedCount >= $maxSymbols) {
                break;
            }

            // Apply filters
            if (!$this->shouldIncludeSymbol($symbolData, $symbolFilters)) {
                continue;
            }

            $this->storeSymbol($symbolData);
            $processedCount++;
        }

        Log::info("Processed {$processedCount} symbols from Binance");
        return $processedCount;
    }

    /**
     * Check if symbol should be included based on filters
     */
    protected function shouldIncludeSymbol($symbolData, $filters)
    {
        // Check if quote asset is allowed
        if (!in_array($symbolData['quoteAsset'], $filters['quote_assets'])) {
            return false;
        }

        // Check if symbol should be excluded
        $symbol = $symbolData['symbol'];
        foreach ($filters['exclude_symbols'] as $exclude) {
            if (str_contains($symbol, $exclude)) {
                return false;
            }
        }

        // Check if status is TRADING
        if ($symbolData['status'] !== 'TRADING') {
            return false;
        }

        // Check if spot trading is allowed
        if (!isset($symbolData['isSpotTradingAllowed']) || !$symbolData['isSpotTradingAllowed']) {
            return false;
        }

        return true;
    }

    /**
     * Store individual symbol data
     */
    protected function storeSymbol($symbolData)
    {
        try {
            $filters = $this->extractFilters($symbolData);

            CryptoSymbol::updateOrCreate(
                ['symbol' => $symbolData['symbol']],
                [
                    'base_asset' => $symbolData['baseAsset'],
                    'quote_asset' => $symbolData['quoteAsset'],
                    'status' => $symbolData['status'],
                    'is_spot_trading_allowed' => $symbolData['isSpotTradingAllowed'] ?? false,
                    'is_margin_trading_allowed' => $symbolData['isMarginTradingAllowed'] ?? false,
                    'min_price' => $filters['price']['min'] ?? null,
                    'max_price' => $filters['price']['max'] ?? null,
                    'tick_size' => $filters['price']['tick'] ?? null,
                    'min_qty' => $filters['lot']['min'] ?? null,
                    'max_qty' => $filters['lot']['max'] ?? null,
                    'step_size' => $filters['lot']['step'] ?? null,
                    'filters' => $symbolData,
                    'last_fetched_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error storing symbol', [
                'symbol' => $symbolData['symbol'],
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extract filters from symbol data
     */
    protected function extractFilters($symbolData)
    {
        $filters = [
            'price' => ['min' => null, 'max' => null, 'tick' => null],
            'lot' => ['min' => null, 'max' => null, 'step' => null],
        ];

        if (isset($symbolData['filters'])) {
            foreach ($symbolData['filters'] as $filter) {
                switch ($filter['filterType']) {
                    case 'PRICE_FILTER':
                        $filters['price']['min'] = $filter['minPrice'] ?? null;
                        $filters['price']['max'] = $filter['maxPrice'] ?? null;
                        $filters['price']['tick'] = $filter['tickSize'] ?? null;
                        break;
                    case 'LOT_SIZE':
                        $filters['lot']['min'] = $filter['minQty'] ?? null;
                        $filters['lot']['max'] = $filter['maxQty'] ?? null;
                        $filters['lot']['step'] = $filter['stepSize'] ?? null;
                        break;
                }
            }
        }

        return $filters;
    }

    /**
     * Get available symbols from database
     */
    public function getAvailableSymbols($quoteAsset = null)
    {
        $query = CryptoSymbol::active();

        if ($quoteAsset) {
            $query->where('quote_asset', strtoupper($quoteAsset));
        }

        return $query->orderBy('symbol')->get();
    }

    /**
     * Check if a symbol exists in database
     */
    public function symbolExists($symbol)
    {
        return CryptoSymbol::where('symbol', strtoupper($symbol))->exists();
    }
}