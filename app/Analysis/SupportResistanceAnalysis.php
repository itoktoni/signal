<?php

namespace App\Analysis;

class SupportResistanceAnalysis implements AnalysisInterface
{
    protected array $indicators = [];
    protected string $notes = '';
    protected ?ApiProviderInterface $apiProvider = null;

    public function setApiProvider(ApiProviderInterface $apiProvider): void
    {
        $this->apiProvider = $apiProvider;
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        if (!$this->apiProvider) {
            throw new \Exception('API Provider not set for SupportResistanceAnalysis');
        }

        // Get historical data for analysis
        $historicalData = $this->apiProvider->getHistoricalData($symbol, $timeframe, 200);

        if (empty($historicalData)) {
            throw new \Exception('No historical data available for analysis');
        }

        // Extract close prices from historical data
        $closePrices = array_map(function($candle) {
            return (float) $candle[4]; // Close price is at index 4
        }, $historicalData);

        // Get current price
        $currentPrice = end($closePrices);

        // Calculate moving averages
        $ma20 = $this->calculateSMA($closePrices, 20);
        $ma50 = $this->calculateSMA($closePrices, 50);

        // Calculate support and resistance from recent price action
        $support = $this->calculateSupport($closePrices, 50);
        $resistance = $this->calculateResistance($closePrices, 50);

        // Detect MA crossover signal
        $signal = $this->detectMACrossoverSignal($ma20, $ma50, $closePrices);

        // Calculate entry, stop loss, and take profit based on signal
        $analysis = $this->calculateEntryExitLevels($signal, $currentPrice, $support, $resistance, $ma20, $ma50);

        // Update indicators
        $this->indicators = [
            'MA20' => round($ma20, 4),
            'MA50' => round($ma50, 4),
            'Support' => round($support, 4),
            'Resistance' => round($resistance, 4),
            'Current_Price' => round($currentPrice, 4)
        ];

        return (object)[
            'title' => "Support & Resistance Analysis for {$symbol} ({$timeframe})",
            'description' => $this->getDescription(),
            'signal' => $analysis['signal'],
            'confidence' => $analysis['confidence'],
            'entry' => round($analysis['entry'], 4),
            'stop_loss' => round($analysis['stop_loss'], 4),
            'take_profit' => round($analysis['take_profit'], 4),
            'risk_reward' => $analysis['risk_reward'],
        ];
    }

    public function getCode(): string
    {
        return 'support_resistance';
    }

    public function getName(): string
    {
        return 'Support & Resistance Strategy';
    }

    public function getDescription(): string
    {
        return 'Menggunakan MA 20/50 crossover dengan support/resistance analysis untuk mendeteksi sinyal trading. '
              . 'Entry point ditentukan saat MA20 cross di atas MA50 (BUY) atau di bawah MA50 (SELL). '
              . 'Stop loss dan take profit dihitung berdasarkan level support/resistance terdekat dengan risk:reward ratio optimal.';
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
            return end($prices); // Return last price if not enough data
        }

        $sum = 0;
        $count = 0;
        for ($i = count($prices) - $period; $i < count($prices); $i++) {
            $sum += $prices[$i];
            $count++;
        }

        return $sum / $count;
    }

    /**
     * Calculate support level from recent price action
     */
    private function calculateSupport(array $prices, int $lookback): float
    {
        $recentPrices = array_slice($prices, -$lookback);
        $min = min($recentPrices);
        $max = max($recentPrices);

        // Support is typically the lowest low in the recent range
        return $min;
    }

    /**
     * Calculate resistance level from recent price action
     */
    private function calculateResistance(array $prices, int $lookback): float
    {
        $recentPrices = array_slice($prices, -$lookback);
        $min = min($recentPrices);
        $max = max($recentPrices);

        // Resistance is typically the highest high in the recent range
        return $max;
    }

    /**
     * Detect MA crossover signal
     */
    private function detectMACrossoverSignal(float $ma20, float $ma50, array $prices): string
    {
        $currentPrice = end($prices);

        // Get previous MA values (approximate)
        $prevPrices = array_slice($prices, 0, -1);
        if (count($prevPrices) >= 50) {
            $prevMa20 = $this->calculateSMA($prevPrices, 20);
            $prevMa50 = $this->calculateSMA($prevPrices, 50);

            // Bullish crossover: MA20 crosses above MA50
            if ($prevMa20 <= $prevMa50 && $ma20 > $ma50) {
                return 'BUY';
            }

            // Bearish crossover: MA20 crosses below MA50
            if ($prevMa20 >= $prevMa50 && $ma20 < $ma50) {
                return 'SELL';
            }
        }

        // No clear crossover, check current position
        if ($ma20 > $ma50 && $currentPrice > $ma20) {
            return 'BUY';
        } elseif ($ma20 < $ma50 && $currentPrice < $ma20) {
            return 'SELL';
        }

        return 'NEUTRAL';
    }

    /**
     * Calculate entry, stop loss, and take profit levels
     */
    private function calculateEntryExitLevels(string $signal, float $currentPrice, float $support, float $resistance, float $ma20, float $ma50): array
    {
        $entry = $currentPrice;
        $stopLoss = 0;
        $takeProfit = 0;
        $confidence = 50;
        $this->notes = '';

        if ($signal === 'BUY') {
            // Entry at current price (immediate entry on crossover)
            $entry = $currentPrice;

            // Stop loss below recent support or MA50
            $stopLoss = min($support * 0.98, $ma50 * 0.95);

            // Take profit at resistance level or 2x risk
            $risk = $entry - $stopLoss;
            $takeProfit = min($resistance * 1.02, $entry + ($risk * 2));

            $confidence = 75;
            $this->notes = "MA20 crossed above MA50 - bullish signal. Entry at current price with stop loss below support.";

        } elseif ($signal === 'SELL') {
            // Entry at current price (immediate entry on crossover)
            $entry = $currentPrice;

            // Stop loss above recent resistance or MA50
            $stopLoss = max($resistance * 1.02, $ma50 * 1.05);

            // Take profit at support level or 2x risk
            $risk = $stopLoss - $entry;
            $takeProfit = max($support * 0.98, $entry - ($risk * 2));

            $confidence = 75;
            $this->notes = "MA20 crossed below MA50 - bearish signal. Entry at current price with stop loss above resistance.";

        } else {
            // Neutral - no clear signal
            $entry = $currentPrice;
            $stopLoss = $support * 0.95;
            $takeProfit = $resistance * 1.05;
            $confidence = 30;
            $this->notes = "No clear MA crossover signal. Price in consolidation phase.";
        }

        $risk = abs($entry - $stopLoss);
        $reward = abs($takeProfit - $entry);
        $riskReward = $risk > 0 ? round($reward / $risk, 2) . ":1" : "N/A";

        return [
            'signal' => $signal,
            'confidence' => $confidence,
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward' => $riskReward
        ];
    }
}
