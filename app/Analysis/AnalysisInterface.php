<?php

namespace App\Analysis;

interface AnalysisInterface
{
    /**
     * Analyze a cryptocurrency symbol and return a standardized result object
     *
     * @param string $symbol The cryptocurrency symbol to analyze (e.g., 'BTCUSDT')
     * @param float $amount The trading amount in USD
     * @return object Standardized result object containing:
     *   - title: string - Analysis title
     *   - signal: string - Trading signal ('BUY', 'SELL', or 'NEUTRAL')
     *   - confidence: float - Confidence percentage (0-100)
     *   - entry: array - Entry price with 'usd' and 'rupiah' values
     *   - stop_loss: array - Stop loss price with 'usd' and 'rupiah' values
     *   - take_profit: array - Take profit price with 'usd' and 'rupiah' values
     *   - risk_reward: float - Risk-reward ratio
     *   - fee: array - Fee details with 'usd' and 'rupiah' values
     *   - potential_profit: array - Potential profit with 'usd' and 'rupiah' values
     *   - potential_loss: array - Potential loss with 'usd' and 'rupiah' values
     *   - indicators: array - Technical indicators for display
     *   - indicator_config: array - Configuration for displaying indicators
     *   - conclusion: array - Analysis conclusion and recommendations
     *   - formatted: array - Formatted values for display
     */
    public function analyze(string $symbol, float $amount = 100): object;

    /**
     * Get the name/identifier of this analysis method
     *
     * @return string The analysis method name
     */
    public function getName(): string;
}