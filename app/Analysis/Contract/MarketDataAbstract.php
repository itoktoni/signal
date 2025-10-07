<?php

namespace App\Analysis\Contract;

abstract class AbstractAnalysis implements MarketDataInterface
{
    protected MarketDataInterface $provider;

    public function __construct(MarketDataInterface $provider)
    {
        $this->provider = $provider;
    }

    public function getHistoricalData(string $symbol, string $timeframe = '1h', int $limit = 200): array
    {
        return $this->provider->getHistoricalData($symbol, $timeframe, $limit);
    }

    /**
     * Get the current market price for a symbol
     *
     * @param string $symbol The trading pair
     * @return float Current price in USD
     */
    public function getPrice(string $symbol): float
    {
        return $this->provider->getPrice($symbol);
    }

    abstract public function getCode(): string;
    abstract public function getName(): string;

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
    abstract public function analyze(
        string $symbol,
        float $amount = 100,
        string $timeframe = '1h',
        ?string $forcedApi = null
    ): object;
}
