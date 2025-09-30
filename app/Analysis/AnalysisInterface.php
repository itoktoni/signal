<?php

namespace App\Analysis;

interface AnalysisInterface
{
     /**
     * Get the unique code identifier for this analysis method
     * (used in UI dropdowns or database storage)
     *
     * Example: 'moving_average', 'support_resistance'
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Get the human-readable name of this analysis method
     *
     * Example: 'Moving Average Analysis'
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Perform a cryptocurrency analysis and return a standardized result object
     *
     * @param string      $symbol     The trading pair to analyze (e.g., 'BTCUSDT')
     * @param float       $amount     Trading amount in USD
     * @param string      $timeframe  The timeframe for analysis (e.g., '1h', '4h', '1d')
     * @param string|null $forcedApi  Force a specific API provider (optional)
     *
     * @return object {
     *   title: string,          // Analysis title
     *   description: array,     // Step-by-step explanation of the analysis flow
     *   signal: string,         // Trading signal: 'BUY' | 'SELL' | 'NEUTRAL'
     *   confidence: float,      // Confidence level (0–100)
     *   price: float,           // Current market price in USD
     *   entry: float,           // Suggested entry price
     *   stop_loss: float,       // Suggested stop loss price
     *   take_profit: float,     // Suggested take profit price
     *   risk_reward: string,    // Risk-reward ratio (e.g., '1:2')
     *   indicators: array,      // Indicators used, key-value pairs (e.g., ['SMA' => 100, 'EMA' => 50])
     *   notes: array            // Extra notes or recommendations
     * }
     */
    public function analyze(
        string $symbol,
        float $amount = 100,
        string $timeframe = '1h',
        ?string $forcedApi = null
    ): object;

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
}
