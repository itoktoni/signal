<?php

namespace App\Analysis;

use App\Settings\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ApiProviderManager
{
     private array $providers = [];
     private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->initializeProviders();
    }

    /**
     * Initialize CoinGecko API provider only
     */
    private function initializeProviders(): void
    {
        try {
            $provider = new \App\Analysis\Providers\CoinGeckoApiProvider();
            $this->providers[$provider->getCode()] = $provider;
        } catch (\Exception $e) {
            Log::error("Failed to initialize CoinGecko API provider: " . $e->getMessage());
        }
    }

    /**
     * Get historical candlestick data from CoinGecko
     */
    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200, ?string $forcedApi = null): array
    {
        $provider = $this->providers['coingecko'] ?? null;

        if (!$provider || !$provider->isAvailable()) {
            throw new \Exception("CoinGecko API provider is not available");
        }

        try {
            // Get CoinGecko coin ID for the symbol
            $coinId = $this->getCoinGeckoCoinId($symbol);

            $rawData = $provider->getHistoricalData($coinId, $interval, $limit);

            if (!empty($rawData)) {
                return $rawData;
            } else {
                throw new \Exception("No historical data available for {$symbol}");
            }
        } catch (\Exception $e) {
            Log::error("CoinGecko API failed for {$symbol}: " . $e->getMessage());
            throw new \Exception("Failed to get historical data for {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Get CoinGecko coin ID for a symbol
     */
    private function getCoinGeckoCoinId(string $symbol): string
    {
        $coinIdMapping = config('crypto.coingecko.coin_id_mapping', []);

        // Direct lookup first
        if (isset($coinIdMapping[$symbol])) {
            return $coinIdMapping[$symbol];
        }

        // Try case-insensitive lookup
        $upperSymbol = strtoupper($symbol);
        if (isset($coinIdMapping[$upperSymbol])) {
            return $coinIdMapping[$upperSymbol];
        }

        // For unknown symbols, try to use the symbol as-is (CoinGecko might still find it)
        // or return a default/fallback
        Log::warning("Coin ID not found for symbol: {$symbol}, trying direct lookup with CoinGecko");

        // Return the symbol as-is and let CoinGecko handle it
        return strtolower($symbol);
    }

    /**
     * Check if interval is supported by provider
     */
    private function isIntervalSupported(string $providerCode, string $interval): bool
    {
        $limitations = config("crypto.coin_api_mapping.api_limitations.{$providerCode}.supported_intervals", []);

        return empty($limitations) || in_array($interval, $limitations);
    }

    /**
     * Adjust limit based on provider limitations
     */
    private function adjustLimitForProvider(string $providerCode, int $limit): int
    {
        $maxLimit = config("crypto.coin_api_mapping.api_limitations.{$providerCode}.max_klines_limit", 1000);

        return min($limit, $maxLimit);
    }

    /**
     * Get current price from CoinGecko
     */
    public function getCurrentPrice(string $symbol): float
    {
        $provider = $this->providers['coingecko'] ?? null;

        if (!$provider || !$provider->isAvailable()) {
            throw new \Exception("CoinGecko API provider is not available");
        }

        try {
            // Get CoinGecko coin ID for the symbol
            $coinId = $this->getCoinGeckoCoinId($symbol);

            $price = $provider->getCurrentPrice($coinId);

            if ($price > 0) {
                return $price;
            } else {
                throw new \Exception("Invalid price received for {$symbol}");
            }
        } catch (\Exception $e) {
            Log::error("CoinGecko API failed for {$symbol}: " . $e->getMessage());
            throw new \Exception("Failed to get current price for {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Get ticker data from CoinGecko
     */
    public function getTickerData(string $symbol): array
    {
        $provider = $this->providers['coingecko'] ?? null;

        if (!$provider || !$provider->isAvailable()) {
            throw new \Exception("CoinGecko API provider is not available");
        }

        try {
            // Get CoinGecko coin ID for the symbol
            $coinId = $this->getCoinGeckoCoinId($symbol);

            $tickerData = $provider->getTickerData($coinId);

            if (!empty($tickerData)) {
                return $tickerData;
            } else {
                throw new \Exception("No ticker data available for {$symbol}");
            }
        } catch (\Exception $e) {
            Log::error("CoinGecko API failed for {$symbol}: " . $e->getMessage());
            throw new \Exception("Failed to get ticker data for {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Get multiple ticker data from CoinGecko
     */
    public function getMultipleTickerData(array $symbols): array
    {
        $provider = $this->providers['coingecko'] ?? null;

        if (!$provider || !$provider->isAvailable()) {
            throw new \Exception("CoinGecko API provider is not available");
        }

        try {
            // Convert symbols to CoinGecko coin IDs
            $coinIds = [];
            foreach ($symbols as $symbol) {
                $coinIds[] = $this->getCoinGeckoCoinId($symbol);
            }

            $tickerData = $provider->getMultipleTickers($coinIds);

            if (!empty($tickerData)) {
                return $tickerData;
            } else {
                throw new \Exception("No ticker data available for symbols: " . implode(', ', $symbols));
            }
        } catch (\Exception $e) {
            Log::error("CoinGecko API failed for symbols: " . implode(', ', $symbols) . " - " . $e->getMessage());
            throw new \Exception("Failed to get ticker data: " . $e->getMessage());
        }
    }

    /**
     * Get symbol information from CoinGecko
     */
    public function getSymbolInfo(?string $symbol = null): array
    {
        $provider = $this->providers['coingecko'] ?? null;

        if (!$provider || !$provider->isAvailable()) {
            throw new \Exception("CoinGecko API provider is not available");
        }

        try {
            $coinId = $symbol ? $this->getCoinGeckoCoinId($symbol) : null;
            $symbolInfo = $provider->getSymbolInfo($coinId);

            if (!empty($symbolInfo)) {
                return $symbolInfo;
            } else {
                throw new \Exception("No symbol info available");
            }
        } catch (\Exception $e) {
            Log::error("CoinGecko API failed to get symbol info: " . $e->getMessage());
            throw new \Exception("Failed to get symbol info: " . $e->getMessage());
        }
    }

    /**
     * Get all available providers (only CoinGecko)
     */
    public function getAvailableProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get CoinGecko provider
     */
    public function getProvider(string $providerCode): ?ApiProviderInterface
    {
        return $this->providers[$providerCode] ?? null;
    }
}