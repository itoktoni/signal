<?php

namespace App\Analysis;

use App\Analysis\AnalysisService;
use App\Enums\AnalysisType;
use App\Enums\TimeIntervalType;
use App\Enums\SignalType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DynamicRRService extends AnalysisService
{
    public function getName(): string
    {
        return AnalysisType::DYNAMIC_RR;
    }

    public function analyze(string $symbol, float $amount = 100): object
    {
        // Get market data for multiple timeframes
        $klines1h = $this->getKlines($symbol, TimeIntervalType::ONE_HOUR, 100);
        $klines4h = $this->getKlines($symbol, TimeIntervalType::FOUR_HOURS, 100);
        $klines1d = $this->getKlines($symbol, TimeIntervalType::ONE_DAY, 100);

        if (!$klines1h || !$klines4h || !$klines1d) {
            throw new \Exception("Failed to fetch market data for {$symbol}");
        }

        // Calculate ATR for volatility-based stop loss
        $atr = $this->calculateATR($klines1h, 14);

        // Calculate Fibonacci retracement levels
        $fibLevels = $this->calculateFibonacciLevels($klines1d);

        // Identify support and resistance levels
        $srLevels = $this->identifySupportResistance($klines1d);

        // Determine market trend
        $trend = $this->determineTrend($klines1d);

        // Determine signal based on combined analysis
        $signal = $this->determineSignal($klines1h, $trend, $srLevels, $fibLevels);

        // Calculate dynamic entry, stop loss, and take profit levels
        $levels = $this->calculateDynamicLevels($klines1h, $atr, $fibLevels, $srLevels, $signal);

        // Calculate confidence based on multiple factors
        $confidence = $this->calculateConfidence($trend, $srLevels, $fibLevels, $signal);

        // Calculate dynamic risk-reward ratio
        $riskReward = $this->calculateDynamicRR($levels['entry'], $levels['stop_loss'], $levels['take_profit']);

        // Calculate additional indicators for display
        $closes = array_map(fn($k) => floatval($k[4]), $klines1h);
        $ema20 = $this->calculateEMA($closes, 20);
        $ema50 = $this->calculateEMA($closes, 50);
        $rsi = $this->calculateRSI($closes, 14);

        $displayIndicators = [
            'ema20' => $ema20,
            'ema50' => $ema50,
            'rsi' => $rsi,
            'atr' => $atr,
            'support' => $srLevels['support'],
            'resistance' => $srLevels['resistance']
        ];

        // Format the result with dynamic amount
        return $this->formatResult(
            "Dynamic RR Analysis for {$symbol}",
            $signal,
            $confidence,
            $levels['entry'],
            $levels['stop_loss'],
            $levels['take_profit'],
            $levels['risk_reward'] !== '1:1' ? floatval(str_replace('1:', '', $levels['risk_reward'])) : 1.0,
            $amount, // dynamic position size in USD
            'dynamic_rr', // analyst method parameter
            $displayIndicators // pass indicators
        );
    }

    /**
     * Get current market price from Binance API
     */
    private function getCurrentMarketPrice(string $symbol): ?float
    {
        try {
            $binanceApi = env('BINANCE_API', 'https://data-api.binance.vision');
            $url = "{$binanceApi}/api/v3/ticker/price?symbol={$symbol}";

            $response = Http::withoutVerifying()->timeout(10)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return floatval($data['price'] ?? 0);
            }
        } catch (\Exception $e) {
            // Log error but don't throw exception
            Log::warning("Failed to get current market price for {$symbol}", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get klines data from Binance
     */
    private function getKlines(string $symbol, string $interval, int $limit): ?array
    {
        $binanceApi = env('BINANCE_API', 'https://data-api.binance.vision');
        $url = "{$binanceApi}/api/v3/klines?symbol={$symbol}&interval={$interval}&limit={$limit}";

        $response = Http::withoutVerifying()->timeout(15)->get($url);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    /**
     * Calculate ATR (Average True Range) for volatility measurement
     */
    private function calculateATR(array $klines, int $period = 14): float
    {
        if (count($klines) < $period + 1) {
            return 0.01; // Default ATR
        }

        $trs = [];
        for ($i = 1; $i < count($klines); $i++) {
            $h = floatval($klines[$i][2]);
            $l = floatval($klines[$i][3]);
            $pc = floatval($klines[$i - 1][4]);
            $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
        }

        $slice = array_slice($trs, -$period);
        return array_sum($slice) / count($slice);
    }

    /**
     * Calculate Fibonacci retracement levels
     */
    private function calculateFibonacciLevels(array $klines): array
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $highs = array_map(fn($k) => floatval($k[2]), $klines);
        $lows = array_map(fn($k) => floatval($k[3]), $klines);

        $period = 50; // Look at last 50 candles
        $slice = array_slice($closes, -$period);

        $highest = max($slice);
        $lowest = min($slice);
        $range = $highest - $lowest;

        return [
            '23.6%' => $highest - ($range * 0.236),
            '38.2%' => $highest - ($range * 0.382),
            '50.0%' => $highest - ($range * 0.5),
            '61.8%' => $highest - ($range * 0.618),
            '78.6%' => $highest - ($range * 0.786),
        ];
    }

    /**
     * Identify key support and resistance levels
     */
    private function identifySupportResistance(array $klines): array
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $highs = array_map(fn($k) => floatval($k[2]), $klines);
        $lows = array_map(fn($k) => floatval($k[3]), $klines);

        // Simple approach: use recent highs and lows as support/resistance
        $period = 30;
        $recentHighs = array_slice($highs, -$period);
        $recentLows = array_slice($lows, -$period);

        return [
            'support' => min($recentLows),
            'resistance' => max($recentHighs)
        ];
    }

    /**
     * Determine market trend
     */
    private function determineTrend(array $klines): string
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);

        // Calculate EMA 50 and EMA 200
        $ema50 = $this->calculateEMA($closes, 50);
        $ema200 = $this->calculateEMA($closes, 200);

        $currentPrice = end($closes);

        if ($currentPrice > $ema50 && $ema50 > $ema200) {
            return SignalType::LONG;
        } elseif ($currentPrice < $ema50 && $ema50 < $ema200) {
            return SignalType::SHORT;
        } else {
            return SignalType::NEUTRAL;
        }
    }

    /**
     * Determine signal based on combined analysis
     */
    private function determineSignal(array $klines, string $trend, array $srLevels, array $fibLevels): string
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $currentPrice = end($closes);

        // Calculate RSI for momentum confirmation
        $rsi = $this->calculateRSI($closes, 14);

        // Check if price is near support and conditions favor long
        if ($trend === SignalType::LONG && $rsi < 70) {
            $distanceToSupport = abs($currentPrice - $srLevels['support']);
            $supportThreshold = $currentPrice * 0.01; // 1% of price

            if ($distanceToSupport <= $supportThreshold) {
                return SignalType::LONG;
            }
        }

        // Check if price is near resistance and conditions favor short
        if ($trend === SignalType::SHORT && $rsi > 30) {
            $distanceToResistance = abs($srLevels['resistance'] - $currentPrice);
            $resistanceThreshold = $currentPrice * 0.01; // 1% of price

            if ($distanceToResistance <= $resistanceThreshold) {
                return SignalType::SHORT;
            }
        }

        return SignalType::NEUTRAL;
    }

    /**
     * Calculate dynamic entry, stop loss, and take profit levels
     */
    private function calculateDynamicLevels(array $klines, float $atr, array $fibLevels, array $srLevels, string $signal): array
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $currentPrice = end($closes);

        // Use the current 1h close price as reference (not live market price)
        $referencePrice = $currentPrice;

        if ($signal === SignalType::NEUTRAL) {
            // Use safe defaults to prevent division by zero
            $safePrice = max($referencePrice, 1); // Ensure price is never 0
            return [
                'entry' => $safePrice,
                'stop_loss' => $safePrice * 0.99, // 1% below entry
                'take_profit' => $safePrice * 1.01, // 1% above entry
                'risk_reward' => '1:1'
            ];
        }

        if ($signal === SignalType::LONG) {
            // Entry near support
            $entry = $srLevels['support'];

            // Stop loss based on ATR (1.5x ATR below entry)
            $stopLoss = $entry - ($atr * 1.5);

            // Take profit based on Fibonacci levels or resistance
            $takeProfit = $this->determineTakeProfitForLong($entry, $fibLevels, $srLevels['resistance'], $atr);

            // Ensure we have valid levels
            if ($entry <= 0 || $stopLoss <= 0 || $takeProfit <= 0) {
                return [
                    'entry' => $referencePrice,
                    'stop_loss' => $referencePrice * 0.99,
                    'take_profit' => $referencePrice * 1.01,
                    'risk_reward' => '1:1'
                ];
            }

            $risk = abs($entry - $stopLoss);
            $reward = abs($takeProfit - $entry);
            $riskReward = $risk > 0 ? round($reward / $risk, 1) : 1;

            return [
                'entry' => $entry,
                'stop_loss' => $stopLoss,
                'take_profit' => $takeProfit,
                'risk_reward' => "1:{$riskReward}"
            ];
        } else { // short
            // Entry near resistance
            $entry = $srLevels['resistance'];

            // Stop loss based on ATR (1.5x ATR above entry)
            $stopLoss = $entry + ($atr * 1.5);

            // Take profit based on Fibonacci levels or support
            $takeProfit = $this->determineTakeProfitForShort($entry, $fibLevels, $srLevels['support'], $atr);

            // Ensure we have valid levels
            if ($entry <= 0 || $stopLoss <= 0 || $takeProfit <= 0) {
                return [
                    'entry' => $referencePrice,
                    'stop_loss' => $referencePrice * 1.01,
                    'take_profit' => $referencePrice * 0.99,
                    'risk_reward' => '1:1'
                ];
            }

            $risk = abs($entry - $stopLoss);
            $reward = abs($takeProfit - $entry);
            $riskReward = $risk > 0 ? round($reward / $risk, 1) : 1;

            return [
                'entry' => $entry,
                'stop_loss' => $stopLoss,
                'take_profit' => $takeProfit,
                'risk_reward' => "1:{$riskReward}"
            ];
        }
    }

    /**
     * Determine take profit level for long positions
     */
    private function determineTakeProfitForLong(float $entry, array $fibLevels, float $resistance, float $atr): float
    {
        // Check Fibonacci levels for potential take profit targets
        $fibTargets = [];
        foreach ($fibLevels as $level) {
            if ($level > $entry) {
                $fibTargets[] = $level;
            }
        }

        // If we have Fibonacci targets, use the closest one that gives good RR
        if (!empty($fibTargets)) {
            sort($fibTargets);
            foreach ($fibTargets as $target) {
                $risk = 1.5 * $atr; // Assuming 1.5x ATR stop loss
                $reward = $target - $entry;
                $rr = $reward / $risk;

                // If this target gives at least 1.5:1 RR, use it
                if ($rr >= 1.5) {
                    return $target;
                }
            }
        }

        // Fallback to resistance level or fixed RR
        $risk = 1.5 * $atr;
        $minimumReward = $risk * 1.5; // At least 1.5:1 RR
        $dynamicTP = $entry + $minimumReward;

        // But don't exceed resistance
        return min($dynamicTP, $resistance);
    }

    /**
     * Determine take profit level for short positions
     */
    private function determineTakeProfitForShort(float $entry, array $fibLevels, float $support, float $atr): float
    {
        // Check Fibonacci levels for potential take profit targets
        $fibTargets = [];
        foreach ($fibLevels as $level) {
            if ($level < $entry) {
                $fibTargets[] = $level;
            }
        }

        // If we have Fibonacci targets, use the closest one that gives good RR
        if (!empty($fibTargets)) {
            rsort($fibTargets);
            foreach ($fibTargets as $target) {
                $risk = 1.5 * $atr; // Assuming 1.5x ATR stop loss
                $reward = $entry - $target;
                $rr = $reward / $risk;

                // If this target gives at least 1.5:1 RR, use it
                if ($rr >= 1.5) {
                    return $target;
                }
            }
        }

        // Fallback to support level or fixed RR
        $risk = 1.5 * $atr;
        $minimumReward = $risk * 1.5; // At least 1.5:1 RR
        $dynamicTP = $entry - $minimumReward;

        // But don't go below support
        return max($dynamicTP, $support);
    }

    /**
     * Calculate confidence percentage
     */
    private function calculateConfidence(string $trend, array $srLevels, array $fibLevels, string $signal): float
    {
        if ($signal === 'hold') {
            return 0;
        }

        $confidence = 50; // Base confidence

        // Adjust based on trend alignment
        if (($signal === SignalType::LONG && $trend === SignalType::LONG) ||
            ($signal === SignalType::SHORT && $trend === SignalType::SHORT)) {
            $confidence += 15;
        } elseif ($trend === SignalType::NEUTRAL) {
            $confidence -= 10;
        }

        // Adjust based on support/resistance strength
        if (isset($srLevels['support']) && isset($srLevels['resistance'])) {
            $srRange = $srLevels['resistance'] - $srLevels['support'];
            $priceRange = $srLevels['resistance'] * 0.1; // 10% of price

            if ($srRange < $priceRange) {
                $confidence += 10; // Tight range means strong levels
            }
        }

        // Adjust based on Fibonacci alignment
        $fibCount = count($fibLevels);
        if ($fibCount >= 4) {
            $confidence += 10;
        } elseif ($fibCount >= 2) {
            $confidence += 5;
        }

        return min(95, max(0, $confidence)); // Cap between 0-95%
    }

    /**
     * Calculate dynamic risk-reward ratio
     */
    private function calculateDynamicRR(float $entry, float $stopLoss, float $takeProfit): float
    {
        $risk = abs($entry - $stopLoss);
        $reward = abs($takeProfit - $entry);

        if ($risk == 0) {
            return 0;
        }

        return round($reward / $risk, 2);
    }

    /**
     * Calculate EMA
     */
    private function calculateEMA(array $prices, int $period): float
    {
        if (count($prices) < $period) {
            return end($prices); // Return last price if not enough data
        }

        $multiplier = 2 / ($period + 1);
        $ema = $prices[0];

        for ($i = 1; $i < count($prices); $i++) {
            $ema = ($prices[$i] - $ema) * $multiplier + $ema;
        }

        return $ema;
    }

    /**
     * Calculate RSI
     */
    private function calculateRSI(array $prices, int $period = 14): float
    {
        if (count($prices) < $period + 1) {
            return 50; // Neutral RSI
        }

        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;
        }

        if ($avgLoss == 0) {
            return 100;
        }

        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

}