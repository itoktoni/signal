<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CurrencyRateService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected int $cacheDuration = 21600; // 6 hours in seconds

    public function __construct()
    {
        $this->apiUrl = config('crypto.currency_api.url', 'https://api.exchangerate-api.com/v4/latest/USD');
        $this->apiKey = config('crypto.currency_api.key', '');
        $this->cacheDuration = config('crypto.currency_api.cache_duration', 21600); // 6 hours default
    }

    /**
     * Get USD to IDR exchange rate
     */
    public function getUSDToIDRRate(): float
    {
        $cacheKey = 'usd_to_idr_rate';

        // Try to get from cache first
        $cachedRate = Cache::get($cacheKey);
        if ($cachedRate !== null) {
            Log::info('Using cached USD to IDR rate', ['rate' => $cachedRate]);
            return $cachedRate;
        }

        // Fetch from API
        $rate = $this->fetchFromAPI();
        if ($rate > 0) {
            // Cache the rate for 6 hours
            Cache::put($cacheKey, $rate, $this->cacheDuration);
            Log::info('Fetched and cached new USD to IDR rate', ['rate' => $rate]);
            return $rate;
        }

        // Fallback to static rate
        $fallbackRate = config('crypto.usd_to_idr', 16000);
        Log::warning('Using fallback USD to IDR rate', ['rate' => $fallbackRate]);
        return $fallbackRate;
    }

    /**
     * Fetch rate from free API
     */
    protected function fetchFromAPI(): float
    {
        try {
            $url = $this->buildApiUrl();

            Log::info('Fetching USD to IDR rate from API', ['url' => $url]);

            $response = Http::timeout(10)
                ->retry(3, 1000)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['rates']['IDR'])) {
                    $rate = floatval($data['rates']['IDR']);
                    Log::info('Successfully fetched USD to IDR rate', ['rate' => $rate]);
                    return $rate;
                }

                Log::warning('IDR rate not found in API response', ['response' => $data]);
            } else {
                Log::error('API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch currency rate from API', [
                'error' => $e->getMessage(),
                'url' => $url ?? 'unknown'
            ]);
        }

        return 0;
    }

    /**
     * Build API URL based on configuration
     */
    protected function buildApiUrl(): string
    {
        if (empty($this->apiKey)) {
            // Use free API without key
            return $this->apiUrl;
        }

        // Use API with key if available
        return str_replace('{API_KEY}', $this->apiKey, $this->apiUrl);
    }

    /**
     * Clear cached rate
     */
    public function clearCache(): void
    {
        Cache::forget('usd_to_idr_rate');
        Log::info('Cleared USD to IDR rate cache');
    }

    /**
     * Get cache expiration time
     */
    public function getCacheExpiration(): \DateTime
    {
        return now()->addSeconds($this->cacheDuration);
    }

    /**
     * Check if rate is cached
     */
    public function isRateCached(): bool
    {
        return Cache::has('usd_to_idr_rate');
    }

    /**
     * Get cached rate without fetching
     */
    public function getCachedRate(): ?float
    {
        return Cache::get('usd_to_idr_rate');
    }
}