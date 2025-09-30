<?php

namespace App\Analysis;

use App\Analysis\Contract\AnalysisAbstract;
use Illuminate\Support\Facades\Log;

class DefaultAnalysis extends AnalysisAbstract
{
    private $indicators;
    private $notes;

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        try {
            // Get historical data
            $historicalData = $this->getHistoricalData($symbol, $timeframe, 100);

            if (empty($historicalData) || count($historicalData) < 30) {
                throw new \Exception('Insufficient historical data. Need at least 30 data points.');
            }

            // Get current price
            $currentPrice = $this->getPrice($symbol);

            // Extract prices from historical data
            $closePrices = array_map(fn($candle) => (float) $candle[4], $historicalData);
            $highPrices = array_map(fn($candle) => (float) $candle[2], $historicalData);
            $lowPrices = array_map(fn($candle) => (float) $candle[3], $historicalData);

            // Calculate indicators for Dynamic RR
            $atr = $this->calculateATR($highPrices, $lowPrices, $closePrices, 14);
            $fibonacciLevels = $this->calculateFibonacciLevels($highPrices, $lowPrices);
            $supportResistance = $this->calculateSupportResistance($highPrices, $lowPrices, $closePrices);

            // Determine signal based on Dynamic RR analysis
            [$signal, $confidence] = $this->getDynamicRRSignal($currentPrice, $atr, $fibonacciLevels, $supportResistance, $closePrices);

            // Calculate entry price based on signal
            $suggestedEntry = $this->calculateDynamicRREntry($signal, $currentPrice, $atr, $fibonacciLevels, $supportResistance);

            // Calculate dynamic trading levels
            $levels = $this->calculateDynamicRRLevels($signal, $suggestedEntry, $currentPrice, $atr, $fibonacciLevels, $supportResistance);

            // Set indicators
            $this->indicators = [
                'Current_Price' => round($currentPrice, 4),
                'Suggested_Entry' => round($suggestedEntry, 4),
                'ATR' => round($atr, 4),
                'Fib_236' => round($fibonacciLevels['236'], 4),
                'Fib_382' => round($fibonacciLevels['382'], 4),
                'Fib_618' => round($fibonacciLevels['618'], 4),
                'SR_Pivot' => round($supportResistance['pivot'], 4),
                'Dynamic_RR' => $levels['risk_reward']
            ];

            Log::info("DynamicRRService: Analysis completed", [
                'signal' => $signal,
                'confidence' => $confidence,
                'entry' => $suggestedEntry
            ]);

            return (object)[
                'title' => "Dynamic Risk-Reward Analysis for {$symbol} ({$timeframe})",
                'description' => $this->getDescription(),
                'signal' => $signal,
                'confidence' => $confidence,
                'entry' => $suggestedEntry,
                'price' => $currentPrice,
                'stop_loss' => $levels['stop_loss'],
                'take_profit' => $levels['take_profit'],
                'risk_reward' => $levels['risk_reward'],
                'indicators' => $this->indicators,
                'historical' => $historicalData,
                'notes' => $this->getNotes(),
            ];

        } catch (\Exception $e) {
            Log::error("DynamicRRService: Analysis failed", [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);

            return (object)[
                'title' => "Dynamic Risk-Reward Analysis for {$symbol} ({$timeframe}) - Limited Data",
                'description' => $this->getDescription(),
                'signal' => 'NEUTRAL',
                'confidence' => 30,
                'entry' => $currentPrice ?? 0,
                'price' => $currentPrice ?? 0,
                'stop_loss' => ($currentPrice ?? 0) * 0.98,
                'take_profit' => ($currentPrice ?? 0) * 1.02,
                'risk_reward' => '1:1',
                'indicators' => [],
                'historical' => [],
                'notes' => $this->getNotes(),
            ];
        }
    }

    public function getCode(): string
    {
        return 'default_analysis';
    }

    public function getName(): string
    {
        return 'Default Analysis';
    }

    private function getDescription(): array
    {
        return [
            'analysis_type' => 'Dynamic Risk-Reward Analysis',
            'indicators' => 'ATR, Fibonacci retracements, Support/Resistance, Dynamic RR ratios',
            'features' => 'Adaptive position sizing, Multiple timeframe confluence',
            'signal_logic' => 'BUY when risk-reward favors upside, SELL when favors downside',
            'risk_management' => 'Dynamic stop loss and take profit based on market volatility'
        ];
    }

    private function getNotes(): array
    {
        return [
            'main_notes' => $this->notes,
            'signal_strength' => $this->calculateDynamicRRSignalStrength(),
            'market_condition' => $this->analyzeDynamicRRMarketCondition(),
            'risk_level' => $this->assessDynamicRRRiskLevel(),
            'execution_tips' => $this->getDynamicRRExecutionTips()
        ];
    }

    /**
     * Calculate ATR (Average True Range)
     */
    private function calculateATR(array $highs, array $lows, array $closes, int $period): float
    {
        if (count($closes) < 2) return 0;

        $trs = [];
        for ($i = 1; $i < min(count($closes), $period + 1); $i++) {
            $tr = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
            $trs[] = $tr;
        }

        if (count($trs) < $period) {
            return array_sum($trs) / max(1, count($trs));
        }

        return array_sum(array_slice($trs, -$period)) / $period;
    }

    /**
     * Calculate Fibonacci retracement levels
     */
    private function calculateFibonacciLevels(array $highs, array $lows): array
    {
        $recentHighs = array_slice($highs, -20);
        $recentLows = array_slice($lows, -20);

        $swingHigh = max($recentHighs);
        $swingLow = min($recentLows);
        $range = $swingHigh - $swingLow;

        return [
            '236' => $swingHigh - ($range * 0.236),
            '382' => $swingHigh - ($range * 0.382),
            '500' => $swingHigh - ($range * 0.500),
            '618' => $swingHigh - ($range * 0.618),
            '786' => $swingHigh - ($range * 0.786)
        ];
    }

    /**
     * Calculate Support and Resistance levels
     */
    private function calculateSupportResistance(array $highs, array $lows, array $closes): array
    {
        $recentHighs = array_slice($highs, -20);
        $recentLows = array_slice($lows, -20);
        $recentCloses = array_slice($closes, -20);

        $high = max($recentHighs);
        $low = min($recentLows);
        $close = end($recentCloses);

        $pivot = ($high + $low + $close) / 3;

        return [
            'pivot' => $pivot,
            'resistance1' => (2 * $pivot) - $low,
            'support1' => (2 * $pivot) - $high
        ];
    }

    /**
     * Get Dynamic RR signal based on multiple indicators
     */
    private function getDynamicRRSignal(float $currentPrice, float $atr, array $fibLevels, array $srLevels, array $closePrices): array
    {
        $confidence = 50;
        $signal = 'NEUTRAL';

        // Check Fibonacci confluence
        $fibConfluence = $this->checkFibonacciConfluence($currentPrice, $fibLevels);

        // Check S/R confluence
        $srConfluence = $this->checkSRConfluence($currentPrice, $srLevels);

        // Check trend and momentum
        $trend = $this->calculateTrend($closePrices);
        $momentum = $this->calculateMomentum($closePrices);

        // Calculate dynamic confidence based on multiple factors
        $totalScore = 0;

        if ($fibConfluence['signal'] === 'BUY') $totalScore += 30;
        elseif ($fibConfluence['signal'] === 'SELL') $totalScore -= 30;

        if ($srConfluence['signal'] === 'BUY') $totalScore += 25;
        elseif ($srConfluence['signal'] === 'SELL') $totalScore -= 25;

        if ($trend > 0.01) $totalScore += 20; // Strong uptrend
        elseif ($trend < -0.01) $totalScore -= 20; // Strong downtrend

        if ($momentum > 0.02) $totalScore += 15; // Strong positive momentum
        elseif ($momentum < -0.02) $totalScore -= 15; // Strong negative momentum

        // Determine signal based on total score
        if ($totalScore > 40) {
            $signal = 'BUY';
            $confidence = min(95, 50 + $totalScore);
            $this->notes = "BUY: Multiple confluence factors align (Fib: {$fibConfluence['level']}, SR: {$srConfluence['level']}, Trend: " . round($trend * 100, 2) . "%)";
        } elseif ($totalScore < -40) {
            $signal = 'SELL';
            $confidence = min(95, 50 + abs($totalScore));
            $this->notes = "SELL: Multiple confluence factors align (Fib: {$fibConfluence['level']}, SR: {$srConfluence['level']}, Trend: " . round($trend * 100, 2) . "%)";
        } else {
            $this->notes = "NEUTRAL: Mixed signals or weak confluence";
        }

        return [$signal, round($confidence)];
    }

    /**
     * Check Fibonacci level confluence
     */
    private function checkFibonacciConfluence(float $currentPrice, array $fibLevels): array
    {
        foreach ($fibLevels as $level => $price) {
            $deviation = abs($currentPrice - $price) / $price;
            if ($deviation < 0.02) { // Within 2% of level
                return [
                    'signal' => ($level === '236' || $level === '382') ? 'BUY' : 'SELL',
                    'level' => "Fib {$level}"
                ];
            }
        }
        return ['signal' => 'NEUTRAL', 'level' => 'None'];
    }

    /**
     * Check Support/Resistance confluence
     */
    private function checkSRConfluence(float $currentPrice, array $srLevels): array
    {
        $pivot = $srLevels['pivot'];
        $resistance1 = $srLevels['resistance1'];
        $support1 = $srLevels['support1'];

        $deviationPivot = abs($currentPrice - $pivot) / $pivot;
        $deviationR1 = abs($currentPrice - $resistance1) / $resistance1;
        $deviationS1 = abs($currentPrice - $support1) / $support1;

        if ($deviationS1 < 0.02) {
            return ['signal' => 'BUY', 'level' => 'Support'];
        } elseif ($deviationR1 < 0.02) {
            return ['signal' => 'SELL', 'level' => 'Resistance'];
        } elseif ($deviationPivot < 0.01) {
            return ['signal' => 'NEUTRAL', 'level' => 'Pivot'];
        }

        return ['signal' => 'NEUTRAL', 'level' => 'None'];
    }

    /**
     * Calculate entry price based on Dynamic RR signal
     * BUY signal: entry price < current price
     * SELL signal (SHORT): entry price > current price
     */
    private function calculateDynamicRREntry(string $signal, float $currentPrice, float $atr, array $fibLevels, array $srLevels): float
    {
        $volatility = $atr / $currentPrice; // Normalized volatility

        if ($signal === 'BUY') {
            // For BUY signal: entry price should be less than current price
            // Use Fibonacci 38.2% or Support level as reference
            $fib382 = $fibLevels['382'];
            $support1 = $srLevels['support1'];

            // Choose the higher level (more conservative entry)
            $referenceLevel = max($fib382, $support1);
            $entryDiscount = $volatility * $currentPrice * 0.4;

            return max($referenceLevel, $currentPrice - $entryDiscount);
        } elseif ($signal === 'SELL') {
            // For SELL signal (SHORT): entry price should be greater than current price
            // Use Fibonacci 61.8% or Resistance level as reference
            $fib618 = $fibLevels['618'];
            $resistance1 = $srLevels['resistance1'];

            // Choose the lower level (more conservative entry)
            $referenceLevel = min($fib618, $resistance1);
            $entryPremium = $volatility * $currentPrice * 0.4;

            return min($referenceLevel, $currentPrice + $entryPremium);
        } else {
            return $currentPrice;
        }
    }

    /**
     * Calculate dynamic trading levels
     */
    private function calculateDynamicRRLevels(string $signal, float $entryPrice, float $currentPrice, float $atr, array $fibLevels, array $srLevels): array
    {
        // Dynamic risk-reward based on ATR and Fibonacci levels
        $baseRiskMultiplier = 1.5;
        $dynamicRiskReward = $this->calculateDynamicRiskReward($signal, $entryPrice, $fibLevels, $srLevels);

        if ($signal === 'BUY') {
            $stopLossDistance = $atr * $baseRiskMultiplier;
            $stopLoss = $entryPrice - $stopLossDistance;

            // Use Fibonacci extension for take profit
            $fib618 = $fibLevels['618'];
            $takeProfit = $fib618 > $entryPrice ? $fib618 : $entryPrice + ($atr * $dynamicRiskReward);
        } elseif ($signal === 'SELL') {
            $stopLossDistance = $atr * $baseRiskMultiplier;
            $stopLoss = $entryPrice + $stopLossDistance;

            // Use Fibonacci extension for take profit
            $fib382 = $fibLevels['382'];
            $takeProfit = $fib382 < $entryPrice ? $fib382 : $entryPrice - ($atr * $dynamicRiskReward);
        } else {
            $stopLoss = $entryPrice - ($atr * $baseRiskMultiplier);
            $takeProfit = $entryPrice + ($atr * $baseRiskMultiplier * $dynamicRiskReward);
        }

        $risk = abs($entryPrice - $stopLoss);
        $reward = abs($takeProfit - $entryPrice);
        $riskReward = $risk > 0 ? round($reward / $risk, 2) . ":1" : "1:1";

        return [
            'stop_loss' => round($stopLoss, 4),
            'take_profit' => round($takeProfit, 4),
            'risk_reward' => $riskReward
        ];
    }

    /**
     * Calculate dynamic risk-reward ratio based on market conditions
     */
    private function calculateDynamicRiskReward(string $signal, float $entryPrice, array $fibLevels, array $srLevels): float
    {
        $baseRR = 2.0;

        // Adjust based on Fibonacci confluence
        $fib618 = $fibLevels['618'];
        $fib382 = $fibLevels['382'];

        if ($signal === 'BUY' && $fib618 > $entryPrice) {
            $fibDistance = ($fib618 - $entryPrice) / $entryPrice;
            $baseRR += $fibDistance * 2; // Increase RR if good Fib target
        } elseif ($signal === 'SELL' && $fib382 < $entryPrice) {
            $fibDistance = ($entryPrice - $fib382) / $entryPrice;
            $baseRR += $fibDistance * 2; // Increase RR if good Fib target
        }

        return max($baseRR, 1.5); // Minimum 1.5 RR
    }

    /**
     * Calculate price trend
     */
    private function calculateTrend(array $prices): float
    {
        if (count($prices) < 10) return 0;

        $firstHalf = array_slice($prices, 0, 5);
        $secondHalf = array_slice($prices, -5);

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        return ($secondAvg - $firstAvg) / $firstAvg;
    }

    /**
     * Calculate price momentum
     */
    private function calculateMomentum(array $prices): float
    {
        if (count($prices) < 5) return 0;

        $recent = array_slice($prices, -3);
        $previous = array_slice($prices, 0, -3);

        $recentAvg = array_sum($recent) / count($recent);
        $previousAvg = array_sum($previous) / count($previous);

        return ($recentAvg - $previousAvg) / $previousAvg;
    }

    private function calculateDynamicRRSignalStrength(): string
    {
        if (empty($this->indicators)) {
            return 'Insufficient data';
        }

        $atr = $this->indicators['ATR'] ?? 0;
        $current = $this->indicators['Current_Price'] ?? 0;

        if ($current == 0) return 'Unknown';

        $atrPercent = ($atr / $current) * 100;

        if ($atrPercent > 4) return 'Very high volatility';
        if ($atrPercent > 3) return 'High volatility';
        if ($atrPercent > 2) return 'Moderate volatility';
        if ($atrPercent > 1) return 'Low volatility';
        return 'Very low volatility';
    }

    private function analyzeDynamicRRMarketCondition(): string
    {
        $signal = $this->getCurrentDynamicRRSignal();

        if ($signal === 'BUY') {
            return 'Multiple timeframe confluence supports upward move';
        } elseif ($signal === 'SELL') {
            return 'Multiple timeframe confluence supports downward move';
        } else {
            return 'Conflicting signals across timeframes';
        }
    }

    private function assessDynamicRRRiskLevel(): string
    {
        if (empty($this->indicators)) {
            return 'Unknown';
        }

        $atr = $this->indicators['ATR'] ?? 0;
        $current = $this->indicators['Current_Price'] ?? 0;
        $rr = $this->indicators['Dynamic_RR'] ?? '1:1';

        if ($current == 0) return 'Unknown';

        $atrPercent = ($atr / $current) * 100;
        $rrRatio = explode(':', $rr)[0] ?? 1;

        if ($atrPercent > 5 || $rrRatio < 1.5) return 'Very high risk';
        if ($atrPercent > 3 || $rrRatio < 2) return 'High risk';
        if ($atrPercent > 2 || $rrRatio < 2.5) return 'Medium risk';
        if ($atrPercent > 1 || $rrRatio < 3) return 'Low risk';
        return 'Very low risk';
    }

    private function getDynamicRRExecutionTips(): string
    {
        $signal = $this->getCurrentDynamicRRSignal();

        if ($signal === 'BUY') {
            return 'Wait for price to pullback to Fibonacci/support confluence. Scale in if RR improves.';
        } elseif ($signal === 'SELL') {
            return 'Wait for price to rally to Fibonacci/resistance confluence. Scale in if RR improves.';
        } else {
            return 'Wait for clearer multi-timeframe alignment before entering positions.';
        }
    }

    private function getCurrentDynamicRRSignal(): string
    {
        if (strpos($this->notes, 'BUY') !== false) return 'BUY';
        if (strpos($this->notes, 'SELL') !== false) return 'SELL';
        return 'NEUTRAL';
    }
}