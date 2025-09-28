<?php

namespace App\Analysis;

interface ApiProviderInterface
{
    /**
     * Get the unique code/name of this API provider
     *
     * @return string The provider code (e.g., 'binance', 'coingecko')
     */
    public function getCode(): string;

    /**
     * Get the display name of this API provider
     *
     * @return string The provider name (e.g., 'Binance API', 'CoinGecko API')
     */
    public function getName(): string;

    /**
     * Get historical candlestick/OHLC data for a symbol
     *
     * @param string $symbol The trading pair symbol (e.g., 'BTCUSDT', 'bitcoin')
     * @param string $interval The time interval (e.g., '1h', '4h', '1d')
     * @param int $limit Number of candles to retrieve
     * @return array Array of candlestick data in provider-specific format
     * @throws \Exception When API call fails or rate limit is exceeded
     */
    public function getHistoricalData(string $symbol, string $interval = '1h', int $limit = 200): array;

    /**
     * Get current/ticker price for a symbol
     *
     * @param string $symbol The trading pair symbol (e.g., 'BTCUSDT', 'bitcoin')
     * @return array Array with price information (format varies by provider)
     * @throws \Exception When API call fails
     */
    public function getTickerData(string $symbol): array;

    /**
     * Get multiple ticker prices at once
     *
     * @param array $symbols Array of trading pair symbols
     * @return array Array of ticker data for all symbols
     * @throws \Exception When API call fails
     */
    public function getMultipleTickers(array $symbols): array;

    /**
     * Get symbol information and trading rules
     *
     * @param string|null $symbol Specific symbol to get info for, null for all
     * @return array Array of symbol information
     * @throws \Exception When API call fails
     */
    public function getSymbolInfo(?string $symbol = null): array;

    /**
     * Check if the API provider is available and not rate limited
     *
     * @return bool True if API is available
     */
    public function isAvailable(): bool;

    /**
     * Get the priority of this API provider (lower number = higher priority)
     *
     * @return int Priority number (0 = highest priority)
     */
    public function getPriority(): int;

    /**
     * Get rate limit information
     *
     * @return array Array with 'requests_per_minute' and 'requests_remaining' keys
     */
    public function getRateLimitInfo(): array;

    /**
     * Get the data format specification for this provider
     *
     * @return array Array describing the data format structure
     */
    public function getDataFormat(): array;

    /**
     * Normalize historical data to a standard format
     *
     * @param array $rawData Raw data from the API
     * @param string $interval Time interval
     * @return array Normalized candlestick data
     */
    public function normalizeHistoricalData(array $rawData, string $interval): array;

    /**
     * Normalize ticker data to a standard format
     *
     * @param array $rawData Raw ticker data from the API
     * @return array Normalized ticker data
     */
    public function normalizeTickerData(array $rawData): array;
}