<?php

if (! function_exists('sortUrl')) {
    function sortUrl($sort, $route)
    {
        $direction = request('sort') === $sort && request('direction') === 'asc' ? 'desc' : 'asc';

        return route($route, array_merge(request()->query(), ['sort' => $sort, 'direction' => $direction]));
    }
}

if (! function_exists('module')) {
    function module($action = null)
    {
        $route = request()->route();
        if ($route) {
            $controller = $route->getController();
            if (method_exists($controller, 'module')) {
                return $controller->module($action);
            }
        }
        return null;
    }
}

if (! function_exists('safeValue')) {
    /**
     * Get a safe value from object property or array key, return 0 if null or not exists
     *
     * @param mixed $data The data source (object or array)
     * @param string $key The property/key name
     * @param mixed $default Default value to return if not found (default: 0)
     * @return mixed
     */
    function safeValue($data, string $key, $default = 0)
    {
        if ($data === null) {
            return $default;
        }

        // Handle object properties
        if (is_object($data)) {
            return $data->$key ?? $default;
        }

        // Handle array keys
        if (is_array($data)) {
            return $data[$key] ?? $default;
        }

        return $default;
    }
}

if (! function_exists('usdToIdr')) {
    /**
     * Convert USD to IDR using Frankfurter API with 4-hour Laravel cache
     *
     * @param float $usdAmount The amount in USD to convert
     * @param bool $forceRefresh Force refresh cache (default: false)
     * @return float The equivalent amount in IDR
     */
    function usdToIdr(float $usdAmount, bool $forceRefresh = false): float
    {
        $rate = getUsdToIdrRate($forceRefresh);
        return $usdAmount * $rate;
    }
}

if (! function_exists('getUsdToIdrRate')) {
    /**
     * Get current USD to IDR exchange rate with 4-hour Laravel cache
     *
     * @param bool $forceRefresh Force refresh cache (default: false)
     * @return float The current USD to IDR exchange rate
     */
    function getUsdToIdrRate(bool $forceRefresh = false): float
    {
        $cacheKey = 'usd_to_idr_rate';
        $cacheDuration = 14400; // 4 hours in seconds

        // Get cached rate or fetch new one
        $rate = $forceRefresh
            ? null
            : \Illuminate\Support\Facades\Cache::get($cacheKey);

        if ($rate === null) {
            $rate = fetchFreshUsdToIdrRate();

            // Cache the rate for 4 hours
            \Illuminate\Support\Facades\Cache::put($cacheKey, $rate, $cacheDuration);
        }

        return $rate;
    }
}

if (! function_exists('fetchFreshUsdToIdrRate')) {
    /**
     * Fetch fresh USD to IDR rate from Frankfurter API
     *
     * @return float The current USD to IDR exchange rate
     */
    function fetchFreshUsdToIdrRate(): float
    {
        try {
            // Fetch from Frankfurter API
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->retry(3, 1000)
                ->get('https://api.frankfurter.app/latest?from=USD&to=IDR');

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['rates']['IDR'])) {
                    $rate = floatval($data['rates']['IDR']);

                    \Illuminate\Support\Facades\Log::info('USD to IDR rate updated', [
                        'rate' => $rate,
                        'source' => 'frankfurter_api'
                    ]);

                    return $rate;
                }
            }

            // If API fails, log error and use fallback
            \Illuminate\Support\Facades\Log::warning('Frankfurter API failed, using fallback rate', [
                'response_status' => $response->status(),
                'response_body' => $response->body()
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to fetch USD to IDR rate from Frankfurter API', [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to static rate (16000) if API fails
        $fallbackRate = 16000;

        \Illuminate\Support\Facades\Log::warning('Using fallback USD to IDR rate', [
            'rate' => $fallbackRate
        ]);

        return $fallbackRate;
    }
}


if (! function_exists('safeNumericValue')) {
    /**
     * Get a safe numeric value from object property or array key, return 0 if null, not exists, or not numeric
     *
     * @param mixed $data The data source (object or array)
     * @param string $key The property/key name
     * @param float $default Default value to return if not found (default: 0)
     * @return float
     */
    function safeNumericValue($data, string $key, float $default = 0.0): float
    {
        $value = safeValue($data, $key, null);

        if ($value === null || $value === '') {
            return $default;
        }

        // Convert to float if it's numeric
        if (is_numeric($value)) {
            return floatval($value);
        }

        return $default;
    }
}

if (! function_exists('numberFormat')) {
    function numberFormat($value, $comma = 3)
    {
        if(!empty($value) && is_numeric($value))
        {
            return number_format($value, $comma, ',', '.');
        }

        return 0;
    }
}
