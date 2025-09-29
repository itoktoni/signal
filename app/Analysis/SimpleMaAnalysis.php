<?php

namespace App\Analysis;

class SimpleMaAnalysis implements AnalysisInterface
{
    protected array $indicators = [];
    protected string $notes = '';
    protected ?ApiProviderInterface $apiProvider = null;
    protected float $currentPrice = 0.0;

    public function setApiProvider(ApiProviderInterface $apiProvider): void
    {
        $this->apiProvider = $apiProvider;
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        if (!$this->apiProvider) {
            throw new \Exception('API Provider not set');
        }

        // Get historical data
        $historicalData = $this->apiProvider->getHistoricalData($symbol, $timeframe, 100);

        if (empty($historicalData)) {
            throw new \Exception('No historical data available');
        }

        // Get current price
        $currentPrice = $this->apiProvider->getCurrentPrice($symbol);
        $this->currentPrice = $currentPrice;

        // Extract close prices from historical data
        $closePrices = array_map(fn($candle) => (float) $candle[4], $historicalData);

        // Calculate indicators
        $ma20 = $this->calculateSMA($closePrices, 20);
        $ma50 = $this->calculateSMA($closePrices, 50);

        // Determine signal
        $signal = $this->getSignal($ma20, $ma50, $closePrices);

        // Calculate suggested entry price (different from current price)
        $suggestedEntry = $this->calculateSuggestedEntry($signal, $currentPrice);

        // Calculate trading levels (simple version)
        $levels = $this->calculateLevels($signal, $suggestedEntry, $ma20, $ma50);

        // Set indicators
        $this->indicators = [
            'MA20' => round($ma20, 4),
            'MA50' => round($ma50, 4),
            'Current_Price' => round($currentPrice, 4),
            'Suggested_Entry' => round($suggestedEntry, 4)
        ];

        return (object)[
            'title' => "Simple MA Analysis for {$symbol} ({$timeframe})",
            'description' => $this->getDescription(),
            'signal' => $signal,
            'confidence' => 70,
            'entry' => $suggestedEntry,  // Suggested entry price
            'price' => $currentPrice,    // Current market price
            'stop_loss' => $levels['stop_loss'],
            'take_profit' => $levels['take_profit'],
            'risk_reward' => $levels['risk_reward'],
            'indicators' => $this->indicators,
            'notes' => $this->notes
        ];
    }

    public function getCode(): string
    {
        return 'simple_ma';
    }

    public function getName(): string
    {
        return 'Simple MA 20/50 Analysis';
    }

    public function getDescription(): string
    {
        return 'Simple Moving Average analysis using MA20 and MA50. '
              . 'BUY signal when MA20 crosses above MA50 and price is above MA20. '
              . 'SELL signal when MA20 crosses below MA50 and price is below MA20.';
    }

    public function getIndicators(): array
    {
        return $this->indicators;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    /**
     * Calculate Simple Moving Average
     */
    private function calculateSMA(array $prices, int $period): float
    {
        if (count($prices) < $period) {
            return end($prices);
        }

        $sum = 0;
        for ($i = count($prices) - $period; $i < count($prices); $i++) {
            $sum += $prices[$i];
        }

        return $sum / $period;
    }

    /**
     * Get signal based on MA crossover and price position
     */
    private function getSignal(float $ma20, float $ma50, array $prices): string
    {
        $currentPrice = end($prices);

        // Check for crossover if we have enough data
        if (count($prices) >= 51) { // Need at least 51 candles for previous calculation
            $prevPrices = array_slice($prices, 0, -1);
            $prevMa20 = $this->calculateSMA($prevPrices, 20);
            $prevMa50 = $this->calculateSMA($prevPrices, 50);

            // Bullish crossover: MA20 crosses above MA50 and price > MA20
            if ($prevMa20 <= $prevMa50 && $ma20 > $ma50 && $currentPrice > $ma20) {
                $this->notes = "MA20 crossed above MA50 and price is above MA20 - BUY signal";
                return 'BUY';
            }

            // Bearish crossover: MA20 crosses below MA50 and price < MA20
            if ($prevMa20 >= $prevMa50 && $ma20 < $ma50 && $currentPrice < $ma20) {
                $this->notes = "MA20 crossed below MA50 and price is below MA20 - SELL signal";
                return 'SELL';
            }
        }

        // No crossover, check current trend
        if ($ma20 > $ma50 && $currentPrice > $ma20) {
            $this->notes = "MA20 > MA50 and price > MA20 - bullish trend";
            return 'BUY';
        } elseif ($ma20 < $ma50 && $currentPrice < $ma20) {
            $this->notes = "MA20 < MA50 and price < MA20 - bearish trend";
            return 'SELL';
        }

        $this->notes = "No clear signal - waiting for crossover";
        return 'NEUTRAL';
    }

    /**
     * Calculate suggested entry price (different from current price)
     */
    private function calculateSuggestedEntry(string $signal, float $currentPrice): float
    {
        // Add a small buffer to current price as entry suggestion
        // This accounts for slippage and provides a more realistic entry point
        $buffer = 0.001; // 0.1% buffer

        if ($signal === 'BUY') {
            // For BUY, suggest entry slightly above current price
            return $currentPrice * (1 + $buffer);
        } elseif ($signal === 'SELL') {
            // For SELL, suggest entry slightly below current price
            return $currentPrice * (1 - $buffer);
        } else {
            // For NEUTRAL, use current price
            return $currentPrice;
        }
    }

    /**
     * Calculate trading levels (simplified)
     */
    private function calculateLevels(string $signal, float $entryPrice, float $ma20, float $ma50): array
    {
        $stopLoss = 0;
        $takeProfit = 0;

        if ($signal === 'BUY') {
            $stopLoss = min($ma50, $ma20) * 0.98; // Below the lower MA
            $takeProfit = $entryPrice * 1.05; // 5% profit target from entry
        } elseif ($signal === 'SELL') {
            $stopLoss = max($ma50, $ma20) * 1.02; // Above the higher MA
            $takeProfit = $entryPrice * 0.95; // 5% profit target from entry
        } else {
            $stopLoss = $entryPrice * 0.95;
            $takeProfit = $entryPrice * 1.05;
        }

        $risk = abs($entryPrice - $stopLoss);
        $reward = abs($takeProfit - $entryPrice);
        $riskReward = $risk > 0 ? round($reward / $risk, 2) . ":1" : "1:1";

        return [
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward' => $riskReward
        ];
    }

    /**
     * Get the current price of the analyzed symbol
     */
    public function getCurrentPrice(): float
    {
        return $this->currentPrice;
    }
}