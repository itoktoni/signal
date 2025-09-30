<?php

namespace App\Analysis\Contract;

interface MarketDataInterface
{
     /**
     * Retrieve historical OHLCV (Open, High, Low, Close, Volume) data
     *
     * Expected format:
     * [
     *   [
     *     (string) open,
     *     (string) high,
     *     (string) low,
     *     (string) close,
     *     (string) volume,
     *     (int) closeTime,
     *     (string) quoteAssetVolume,
     *     (int) numberOfTrades,
     *     (string) takerBuyBaseVolume,
     *     (string) takerBuyQuoteVolume
     *   ],
     *   ...
     * ]
     *
     * @param string $symbol    The trading pair
     * @param string $timeframe The timeframe (default '1h')
     * @param int    $limit     Number of data points (default 200)
     *
     * @return array
     */
    public function getHistoricalData(
        string $symbol,
        string $timeframe = '1h',
        int $limit = 200
    ): array;

    /**
     * Get the current market price for a symbol
     *
     * @param string $symbol The trading pair
     * @return float Current price in USD
     */
    public function getPrice(string $symbol): float;
    public function getCode(): string;
    public function getName(): string;
}
