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
     * Initialize all available API providers
     */
    private function initializeProviders(): void
    {
        $providerClasses = [
            \App\Analysis\Providers\BinanceApiProvider::class,
            \App\Analysis\Providers\CoinGeckoApiProvider::class,
        ];

        foreach ($providerClasses as $providerClass) {
            if (class_exists($providerClass)) {
                try {
                    $provider = new $providerClass();
                    $this->providers[$provider->getCode()] = $provider;
                } catch (\Exception $e) {
                    Log::warning("Failed to initialize API provider {$providerClass}: " . $e->getMessage());
                }
            }
        }

        // Sort providers by priority
        uasort($this->providers, function($a, $b) {
            return $a->getPriority() <=> $b->getPriority();
        });
    }

    /**
     * Get historical candlestick data with automatic fallback
     */
    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200, ?string $forcedApi = null): array
    {
        $errors = [];

        // Get coin-specific API providers
        $providersToTry = $forcedApi ? [$forcedApi] : $this->getProvidersForCoin($symbol);

        foreach ($providersToTry as $providerCode) {
            $provider = $this->providers[$providerCode] ?? null;

            if (!$provider || !$provider->isAvailable()) {
                Log::info("API provider {$providerCode} is not available, skipping");
                continue;
            }

            try {
                Log::info("Attempting to get historical data from {$providerCode} for {$symbol}");

                // Map symbol to provider-specific format
                $mappedSymbol = $this->getMappedSymbol($symbol, $providerCode);
                Log::info("Mapped symbol {$symbol} to {$mappedSymbol} for provider {$providerCode}");

                // Check if interval is supported by this provider
                if (!$this->isIntervalSupported($providerCode, $interval)) {
                    Log::info("Interval {$interval} not supported by {$providerCode}, skipping");
                    continue;
                }

                // Adjust limit based on provider limitations
                $adjustedLimit = $this->adjustLimitForProvider($providerCode, $limit);

                $rawData = $provider->getHistoricalData($mappedSymbol, $interval, $adjustedLimit);

                if (!empty($rawData)) {
                    $normalizedData = $provider->normalizeHistoricalData($rawData, $interval);
                    Log::info("Successfully retrieved historical data from {$providerCode} for {$symbol}");
                    return $normalizedData;
                }
            } catch (\Exception $e) {
                $error = "Provider {$providerCode} failed for {$symbol}: " . $e->getMessage();
                $errors[] = $error;
                Log::warning($error);

                // Mark provider as temporarily unavailable
                $this->markProviderUnavailable($providerCode);
            }
        }

        // If all providers failed, throw exception with all error details
        $errorMessage = "All API providers failed for {$symbol}. Errors: " . implode("; ", $errors);
        Log::error($errorMessage);
        throw new \Exception($errorMessage);
    }

    /**
      * Get the appropriate API providers for a specific coin
      */
     private function getProvidersForCoin(string $symbol): array
     {
         $coinMapping = config('crypto.coin_api_mapping', []);
         $symbolConverter = config('crypto.symbol_converter', []);
         $symbolMappings = $coinMapping['symbol_mappings'] ?? [];

         // Use the base symbol for primary API mapping (BTC instead of BTCUSDT)
         // Check if coin has specific API mapping
         if (isset($coinMapping['primary_api'][$symbol])) {
             $primaryApi = $coinMapping['primary_api'][$symbol];

             // If coin has fallback APIs configured
             if (isset($coinMapping['fallback_apis'][$symbol])) {
                 return $coinMapping['fallback_apis'][$symbol];
             }

             // Otherwise, try primary API first, then fall back to all providers sorted by priority
             $allProviders = array_keys($this->providers);
             $providersToTry = [$primaryApi];

             // Add other providers except the primary one
             foreach ($allProviders as $providerCode) {
                 if ($providerCode !== $primaryApi) {
                     $providersToTry[] = $providerCode;
                 }
             }

             return $providersToTry;
         }

         // Default: use all providers sorted by priority
         return array_keys($this->providers);
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
     * Get current price with automatic fallback
     */
    public function getCurrentPrice(string $symbol): float
    {
        $errors = [];

        foreach ($this->providers as $providerCode => $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            try {
                Log::info("Attempting to get current price from {$providerCode}");

                // Map symbol to provider-specific format
                $mappedSymbol = $this->getMappedSymbol($symbol, $providerCode);
                Log::info("Mapped symbol {$symbol} to {$mappedSymbol} for provider {$providerCode}");

                $tickerData = $provider->getTickerData($mappedSymbol);

                if (!empty($tickerData) && isset($tickerData['price'])) {
                    $price = (float) $tickerData['price'];
                    if ($price > 0) {
                        Log::info("Successfully retrieved current price from {$providerCode}: {$price}");
                        return $price;
                    }
                }
            } catch (\Exception $e) {
                $error = "Provider {$providerCode} failed: " . $e->getMessage();
                $errors[] = $error;
                Log::warning($error);

                $this->markProviderUnavailable($providerCode);
            }
        }

        $errorMessage = "All API providers failed to get current price. Errors: " . implode("; ", $errors);
        Log::error($errorMessage);
        throw new \Exception($errorMessage);
    }

    /**
     * Get ticker data with automatic fallback
     */
    public function getTickerData(string $symbol): array
    {
        $errors = [];

        foreach ($this->providers as $providerCode => $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            try {
                Log::info("Attempting to get ticker data from {$providerCode}");

                // Map symbol to provider-specific format
                $mappedSymbol = $this->getMappedSymbol($symbol, $providerCode);
                Log::info("Mapped symbol {$symbol} to {$mappedSymbol} for provider {$providerCode}");

                $tickerData = $provider->getTickerData($mappedSymbol);

                if (!empty($tickerData)) {
                    $normalizedData = $provider->normalizeTickerData([$tickerData]);
                    Log::info("Successfully retrieved ticker data from {$providerCode}");
                    return $normalizedData[0] ?? $tickerData;
                }
            } catch (\Exception $e) {
                $error = "Provider {$providerCode} failed: " . $e->getMessage();
                $errors[] = $error;
                Log::warning($error);

                $this->markProviderUnavailable($providerCode);
            }
        }

        $errorMessage = "All API providers failed to get ticker data. Errors: " . implode("; ", $errors);
        Log::error($errorMessage);
        throw new \Exception($errorMessage);
    }

    /**
     * Get multiple ticker data with automatic fallback
     */
    public function getMultipleTickerData(array $symbols): array
    {
        $errors = [];

        foreach ($this->providers as $providerCode => $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            try {
                Log::info("Attempting to get multiple ticker data from {$providerCode}");

                // Map symbols to provider-specific format
                $mappedSymbols = array_map(function($symbol) use ($providerCode) {
                    return $this->getMappedSymbol($symbol, $providerCode);
                }, $symbols);
                Log::info("Mapped symbols for provider {$providerCode}");

                $tickerData = $provider->getMultipleTickers($mappedSymbols);

                if (!empty($tickerData)) {
                    $normalizedData = $provider->normalizeTickerData($tickerData);
                    Log::info("Successfully retrieved multiple ticker data from {$providerCode}");
                    return $normalizedData;
                }
            } catch (\Exception $e) {
                $error = "Provider {$providerCode} failed: " . $e->getMessage();
                $errors[] = $error;
                Log::warning($error);

                $this->markProviderUnavailable($providerCode);
            }
        }

        $errorMessage = "All API providers failed to get multiple ticker data. Errors: " . implode("; ", $errors);
        Log::error($errorMessage);
        throw new \Exception($errorMessage);
    }

    /**
     * Get symbol information with automatic fallback
     */
    public function getSymbolInfo(?string $symbol = null): array
    {
        $errors = [];

        foreach ($this->providers as $providerCode => $provider) {
            if (!$provider->isAvailable()) {
                continue;
            }

            try {
                Log::info("Attempting to get symbol info from {$providerCode}");

                // Map symbol to provider-specific format if provided
                $mappedSymbol = $symbol ? $this->getMappedSymbol($symbol, $providerCode) : null;
                if ($symbol) {
                    Log::info("Mapped symbol {$symbol} to {$mappedSymbol} for provider {$providerCode}");
                }

                $symbolInfo = $provider->getSymbolInfo($mappedSymbol);

                if (!empty($symbolInfo)) {
                    Log::info("Successfully retrieved symbol info from {$providerCode}");
                    return $symbolInfo;
                }
            } catch (\Exception $e) {
                $error = "Provider {$providerCode} failed: " . $e->getMessage();
                $errors[] = $error;
                Log::warning($error);

                $this->markProviderUnavailable($providerCode);
            }
        }

        $errorMessage = "All API providers failed to get symbol info. Errors: " . implode("; ", $errors);
        Log::error($errorMessage);
        throw new \Exception($errorMessage);
    }

    /**
     * Get the default API provider code from settings
     */
    public function getDefaultProvider(): string
    {
        return $this->settings->get('api.default_provider', 'binance');
    }

    /**
     * Set the default API provider
     */
    public function setDefaultProvider(string $providerCode): void
    {
        if (!isset($this->providers[$providerCode])) {
            throw new \InvalidArgumentException("Provider {$providerCode} is not available");
        }

        $this->settings->set('api.default_provider', $providerCode);
    }

    /**
     * Get all available providers
     */
    public function getAvailableProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get a specific provider by code
     */
    public function getProvider(string $providerCode): ?ApiProviderInterface
    {
        return $this->providers[$providerCode] ?? null;
    }

    /**
     * Mark a provider as temporarily unavailable
     */
    private function markProviderUnavailable(string $providerCode): void
    {
        $cacheKey = "api_provider_unavailable_{$providerCode}";
        Cache::put($cacheKey, true, now()->addMinutes(5)); // Mark as unavailable for 5 minutes
    }

    /**
     * Check if provider is marked as unavailable
     */
    private function isProviderMarkedUnavailable(string $providerCode): bool
    {
        $cacheKey = "api_provider_unavailable_{$providerCode}";
        return Cache::get($cacheKey, false);
    }

    /**
     * Override the isAvailable method to check our cache
     */
    public function isProviderAvailable(string $providerCode): bool
    {
        $provider = $this->providers[$providerCode] ?? null;

        if (!$provider) {
            return false;
        }

        // Check if we marked it as unavailable
        if ($this->isProviderMarkedUnavailable($providerCode)) {
            return false;
        }

        return $provider->isAvailable();
    }

    /**
     * Map a symbol from one provider to another
     */
    public function mapSymbol(string $symbol, string $fromProvider, string $toProvider): string
    {
        $coinMapping = config('crypto.coin_api_mapping', []);
        $symbolMappings = $coinMapping['symbol_mappings'] ?? [];

        // If no mappings exist, return the symbol as is
        if (empty($symbolMappings)) {
            return $symbol;
        }

        // Get the base symbol from the source provider
        $baseSymbol = $symbol;
        if (isset($symbolMappings[$fromProvider][$symbol])) {
            // Find the key that maps to this value
            $baseSymbol = array_search($symbolMappings[$fromProvider][$symbol], $symbolMappings[$fromProvider]);
            if ($baseSymbol === false) {
                $baseSymbol = $symbol;
            }
        }

        // Map to the target provider
        if (isset($symbolMappings[$toProvider][$baseSymbol])) {
            return $symbolMappings[$toProvider][$baseSymbol];
        }

        // If no mapping found, return the original symbol
        return $symbol;
    }

    /**
     * Get the mapped symbol for a specific provider
     * This method handles the two-step mapping process:
     * 1. Convert base symbol to standard format using symbol_converter
     * 2. Map standard format to provider-specific format using symbol_mappings
     */
    public function getMappedSymbol(string $symbol, string $provider): string
    {
        // Step 1: Convert base symbol to standard format using symbol_converter
        $symbolConverter = config('crypto.symbol_converter', []);
        $standardSymbol = $symbolConverter[$symbol] ?? $symbol;

        // If the symbol is already in standard format, use it as is
        if ($standardSymbol === $symbol) {
            // Check if this is already a standard format symbol
            $isStandardFormat = false;
            foreach ($symbolConverter as $base => $standard) {
                if ($standard === $symbol) {
                    $isStandardFormat = true;
                    break;
                }
            }

            if (!$isStandardFormat) {
                // Try to find if this symbol exists in any provider mapping
                $coinMapping = config('crypto.coin_api_mapping', []);
                $symbolMappings = $coinMapping['symbol_mappings'] ?? [];

                // Check if this symbol exists in any provider's mapping
                foreach ($symbolMappings as $providerCode => $mappings) {
                    if (isset($mappings[$symbol])) {
                        $standardSymbol = $symbol;
                        break;
                    }
                }
            }
        }

        // Step 2: Map standard format to provider-specific format using symbol_mappings
        $coinMapping = config('crypto.coin_api_mapping', []);
        $symbolMappings = $coinMapping['symbol_mappings'] ?? [];

        // If no mappings exist or provider doesn't have mappings, return the standard symbol
        if (empty($symbolMappings) || !isset($symbolMappings[$provider])) {
            return $standardSymbol;
        }

        // Return the mapped symbol if it exists
        return $symbolMappings[$provider][$standardSymbol] ?? $standardSymbol;
    }

    /**
     * Get rate limit information for all providers
     */
    public function getAllRateLimitInfo(): array
    {
        $info = [];

        foreach ($this->providers as $providerCode => $provider) {
            $info[$providerCode] = $provider->getRateLimitInfo();
        }

        return $info;
    }
}