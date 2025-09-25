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
     *   - description: string - technology that used in the analysis with flow to reach the conclusion
     *   - signal: string - Trading signal ('BUY', 'SELL', or 'NEUTRAL')
     *   - confidence: float - Confidence percentage (0-100)
     *   - entry_usd: float - Entry price in USD
     *   - entry_idr: float - Entry price in Rupiah
     *   - stop_loss_usd: float - Stop loss price in USD
     *   - stop_loss_idr: float - Stop loss price in Rupiah
     *   - take_profit_usd: float - Take profit price in USD
     *   - take_profit_idr: float - Take profit price in Rupiah
     *   - risk_reward: string - Risk-reward ratio
     *   - fee_usd: float - Fee details in USD
     *   - fee_idr: float - Fee details in Rupiah
     *   - potential_profit_usd: float - Potential profit in USD
     *   - potential_profit_idr: float - Potential profit in Rupiah
     *   - potential_loss_usd: float - Potential loss in USD
     *   - potential_loss_idr: float - Potential loss in Rupiah
     */
    public function analyze(string $symbol, float $amount = 100): object;

     /**
     * Get the name/identifier of this analysis method
     *
     * @return string The code name that used in ui dropdown and database (e.g., 'support_resistance', 'moving_average')
     */
    public function getCode(): string;

    /**
     * Get the name/identifier of this analysis method
     *
     * @return string The analysis method name
     */
    public function getName(): string;

    /**
     * Get the description of this analysis method
     *
     * @return string The technology that used in the analysis with flow to reach the conclusion
     */
    public function getDescription(): string;

    /**
     * Get the indicators used in the analysis
     *
     * @return array The indicators used in the analysis using key value pair ['name' => value] eg ['SMA' => 100, 'EMA' => 50]
     */
    public function getIndicators(): array;

    /**
     * Get any notes or results or suggestions from any calculation and analysis
     *
     * @return string Any notes or results or suggestions from any calculation and analysis
     */
    public function getNotes(): string;
}