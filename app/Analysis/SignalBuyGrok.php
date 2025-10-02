<?php

namespace App\Analysis;

use App\Analysis\Contract\AnalysisAbstract;
use Exception;
use Illuminate\Support\Facades\Log;

class SignalBuyGrok extends AnalysisAbstract
{
    private $indicators;
    private $notes = [];

    public function getCode(): string
    {
        return 'grok_advanced_pattern_analysis';
    }

    public function getName(): string
    {
        return 'Grok Advanced Pattern Analysis for Crypto Trading Signals';
    }

    public function analyze(
        string $symbol,
        float $amount = 100,
        string $timeframe = '1h',
        ?string $forcedApi = null
    ): object
    {
        try {
            $historicalData = $this->getHistoricalData($symbol, $timeframe, 500);

            if (empty($historicalData) || count($historicalData) < 50) {
                throw new Exception('Insufficient historical data. Need at least 50 data points.');
            }

            $currentPrice = $this->getPrice($symbol);

            $opens = array_column($historicalData, 'open');
            $highs = array_column($historicalData, 'high');
            $lows = array_column($historicalData, 'low');
            $closes = array_column($historicalData, 'close');
            $volumes = array_column($historicalData, 'volume');

            $ema20 = $this->calculateEMA($closes, 20);
            $ema50 = $this->calculateEMA($closes, 50);
            $ema200 = $this->calculateEMA($closes, 200);
            $rsi = $this->calculateRSI($closes);
            $atr = $this->calculateATR($highs, $lows, $closes);

            $lastEma20 = end($ema20);
            $lastEma50 = end($ema50);
            $lastEma200 = end($ema200);
            $lastRsi = end($rsi);
            $lastAtr = end($atr);

            $swingHighs = $this->findSwingHighs($highs);
            $swingLows = $this->findSwingLows($lows);

            $trendDirection = 'Sideways';
            $isBullish = false;
            $isBearish = false;
            if (count($swingHighs) >= 2 && count($swingLows) >= 2) {
                $lastHigh = end($swingHighs)['price'];
                $prevHigh = $swingHighs[count($swingHighs) - 2]['price'];
                $lastLow = end($swingLows)['price'];
                $prevLow = $swingLows[count($swingLows) - 2]['price'];

                if ($currentPrice > $lastEma50 && $lastEma50 > $lastEma200 && $lastRsi > 50 &&
                    $lastHigh > $prevHigh && $lastLow > $prevLow) {
                    $trendDirection = 'Bullish';
                    $isBullish = true;
                } elseif ($currentPrice < $lastEma50 && $lastEma50 < $lastEma200 && $lastRsi < 50 &&
                    $lastHigh < $prevHigh && $lastLow < $prevLow) {
                    $trendDirection = 'Bearish';
                    $isBearish = true;
                }
            }

            $trendStrength = abs($lastRsi - 50) * 2;
            $volatilityFactor = $lastAtr / $currentPrice * 100;

            $supportLevels = $this->identifySupportLevels($lows);
            $resistanceLevels = $this->identifyResistanceLevels($highs);

            $candlePatterns = $this->detectCandlestickPatterns($opens, $highs, $lows, $closes, count($closes) - 1);
            $chartPatterns = $this->detectChartPatterns($swingHighs, $swingLows);
            $patterns = array_merge($candlePatterns, $chartPatterns);

            $lastSwingLow = end($swingLows)['price'] ?? min($lows);
            $lastSwingHigh = end($swingHighs)['price'] ?? max($highs);
            $fibLevels = $this->calculateFibRetracement($lastSwingLow, $lastSwingHigh);

            [$signal, $confidence] = $this->getDynamicRRSignal($currentPrice, $lastAtr, $fibLevels, ['support1' => $this->findNearestSupport($supportLevels, $currentPrice), 'resistance1' => $this->findNearestResistance($resistanceLevels, $currentPrice), 'pivot' => ($lastSwingHigh + $lastSwingLow + $currentPrice)/3], $closes);
            $entry = $this->calculateDynamicRREntry($signal, $currentPrice, $lastAtr, $fibLevels, ['support1' => $this->findNearestSupport($supportLevels, $currentPrice), 'resistance1' => $this->findNearestResistance($resistanceLevels, $currentPrice)]);
            $levels = $this->calculateDynamicRRLevels($signal, $entry, $currentPrice, $lastAtr, $fibLevels, ['support1' => $this->findNearestSupport($supportLevels, $currentPrice), 'resistance1' => $this->findNearestResistance($resistanceLevels, $currentPrice)]);

            $this->indicators = [
                'EMA20' => $lastEma20,
                'EMA50' => $lastEma50,
                'EMA200' => $lastEma200,
                'RSI' => $lastRsi,
                'ATR' => $lastAtr,
            ];

            $this->setConclusionAndSuggestions($signal, $trendDirection, $patterns, $this->findNearestSupport($supportLevels, $currentPrice), $this->findNearestResistance($resistanceLevels, $currentPrice));

            $description = $this->getDescription($symbol, $timeframe, $trendDirection, $patterns, $signal);

            Log::info("AdvancedPatternAnalysis: Analysis completed", [
                'symbol' => $symbol,
                'signal' => $signal,
                'confidence' => $confidence,
            ]);

            return (object) [
                'title' => $this->getName(),
                'description' => $description,
                'signal' => $signal,
                'confidence' => $confidence,
                'score' => $this->calculateScore($signal, $confidence),
                'price' => $currentPrice,
                'entry' => $entry,
                'stop_loss' => $levels['stop_loss'],
                'take_profit' => $levels['take_profit'],
                'risk_reward' => $levels['risk_reward'],
                'indicators' => $this->indicators,
                'historical' => $historicalData,
                'notes' => $this->notes,
                'patterns' => $patterns,
                'market_phase' => $trendDirection,
                'volatility_factor' => $volatilityFactor,
                'support_levels' => $supportLevels,
                'resistance_levels' => $resistanceLevels,
                'trend_direction' => $trendDirection,
                'trend_strength' => $trendStrength,
            ];
        } catch (Exception $e) {
            Log::error("AdvancedPatternAnalysis: Analysis failed", [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);

            return (object)[
                'title' => $this->getName() . ' - Limited Data',
                'description' => $this->getDescription($symbol, $timeframe, 'Unknown', [], 'NEUTRAL'),
                'signal' => 'NEUTRAL',
                'confidence' => 30,
                'score' => 30,
                'price' => $currentPrice ?? 0,
                'entry' => $currentPrice ?? 0,
                'stop_loss' => ($currentPrice ?? 0) * 0.98,
                'take_profit' => ($currentPrice ?? 0) * 1.02,
                'risk_reward' => '1:1',
                'indicators' => [],
                'historical' => $historicalData ?? [],
                'notes' => $this->notes,
                'patterns' => [],
                'market_phase' => 'sideways',
                'volatility_factor' => 0.02,
                'support_levels' => [],
                'resistance_levels' => [],
                'trend_direction' => 'neutral',
                'trend_strength' => 0,
            ];
        }
    }

    private function getDescription(string $symbol, string $timeframe, string $trendDirection, array $patterns, string $signal): array
    {
        $description = [];
        $description[] = "Step 1: Mengambil data historis untuk {$symbol} pada timeframe {$timeframe} dengan " . count($historicalData ?? []) . " candles.";
        $description[] = "Step 2: Menghitung indikator teknikal: EMA (20,50,200), RSI, ATR.";
        $description[] = "Step 3: Mengidentifikasi swing highs/lows dan arah trend sebagai {$trendDirection}.";
        $description[] = "Step 4: Mendeteksi pola candlestick dan chart: " . implode(', ', $patterns) . ".";
        $description[] = "Step 5: Menghitung level support/resistance dan Fibonacci retracement.";
        $description[] = "Step 6: Menganalisis konfirmasi dari volume dan momentum.";
        $description[] = "Step 7: Menghasilkan signal trading: {$signal} dengan confidence level.";
        $description[] = "Step 8: Menentukan entry, stop loss, take profit berdasarkan risk-reward dinamis.";
        $description[] = "Step 9: Memberikan kesimpulan dan saran berdasarkan signal.";
        return $description;
    }

    private function setConclusionAndSuggestions(string $signal, string $trendDirection, array $patterns, float $nearestSupport, float $nearestResistance): void
    {
        $this->notes[] = "Kesimpulan: Signal {$signal} pada trend {$trendDirection} dengan pola terdeteksi: " . implode(', ', $patterns) . ".";

        if ($signal === 'BUY') {
            $this->notes[] = "Saran: Tunggu pullback ke support terdekat di {$nearestSupport} untuk entry optimal, atau konfirmasi breakout resistance di {$nearestResistance}.";
        } elseif ($signal === 'SELL') {
            $this->notes[] = "Saran: Tunggu breakout support terdekat di {$nearestSupport} untuk entry, atau rejection di resistance {$nearestResistance}.";
        } elseif ($signal === 'SIDEWAYS' || $signal === 'NEUTRAL') {
            $this->notes[] = "Saran: Hindari entry besar; trade range-bound antara support {$nearestSupport} dan resistance {$nearestResistance}, tunggu breakout.";
        } else {
            $this->notes[] = "Saran: Monitor pasar untuk konfirmasi lebih lanjut.";
        }
    }

    private function findNearestSupport(array $supportLevels, float $currentPrice): float
    {
        $belowLevels = array_filter($supportLevels, function($level) use ($currentPrice) {
            return $level <= $currentPrice;
        });
        return !empty($belowLevels) ? max($belowLevels) : min($supportLevels);
    }

    private function findNearestResistance(array $resistanceLevels, float $currentPrice): float
    {
        $aboveLevels = array_filter($resistanceLevels, function($level) use ($currentPrice) {
            return $level >= $currentPrice;
        });
        return !empty($aboveLevels) ? min($aboveLevels) : max($resistanceLevels);
    }

    private function calculateEMA(array $closes, int $period): array
    {
        $ema = [];
        if (count($closes) < $period) return $ema;

        $sma = array_sum(array_slice($closes, 0, $period)) / $period;
        $ema[] = $sma;

        $multiplier = 2 / ($period + 1);

        for ($i = $period; $i < count($closes); $i++) {
            $ema[] = ($closes[$i] - $ema[count($ema) - 1]) * $multiplier + $ema[count($ema) - 1];
        }

        return $ema;
    }

    private function calculateRSI(array $closes, int $period = 14): array
    {
        $rsis = [];
        $changes = [];
        for ($i = 1; $i < count($closes); $i++) {
            $changes[] = $closes[$i] - $closes[$i - 1];
        }

        if (count($changes) < $period) return [];

        $gains = [];
        $losses = [];
        for ($i = 0; $i < $period; $i++) {
            $change = $changes[$i];
            $gains[] = max($change, 0);
            $losses[] = abs(min($change, 0));
        }

        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;

        $rsi = $avgLoss == 0 ? 100 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
        $rsis[] = $rsi;

        for ($i = $period; $i < count($changes); $i++) {
            $currentGain = max($changes[$i], 0);
            $currentLoss = abs(min($changes[$i], 0));

            $avgGain = (($avgGain * ($period - 1)) + $currentGain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $currentLoss) / $period;

            $rsi = $avgLoss == 0 ? 100 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
            $rsis[] = $rsi;
        }

        return $rsis;
    }

    private function calculateATR(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $atrs = [];
        if (count($closes) < $period + 1) return $atrs;

        $trs = [];
        for ($i = 1; $i < count($closes); $i++) {
            $tr1 = $highs[$i] - $lows[$i];
            $tr2 = abs($highs[$i] - $closes[$i - 1]);
            $tr3 = abs($lows[$i] - $closes[$i - 1]);
            $trs[] = max($tr1, $tr2, $tr3);
        }

        $sum = array_sum(array_slice($trs, 0, $period));
        $atrs[] = $sum / $period;

        for ($i = $period; $i < count($trs); $i++) {
            $prevAtr = end($atrs);
            $atr = (($prevAtr * ($period - 1)) + $trs[$i]) / $period;
            $atrs[] = $atr;
        }

        return $atrs;
    }

    private function findSwingHighs(array $highs, int $lookback = 5): array
    {
        $swings = [];
        for ($i = $lookback; $i < count($highs) - $lookback; $i++) {
            $isHigh = true;
            for ($j = 1; $j <= $lookback; $j++) {
                if ($highs[$i] <= $highs[$i - $j] || $highs[$i] <= $highs[$i + $j]) {
                    $isHigh = false;
                    break;
                }
            }
            if ($isHigh) {
                $swings[] = ['index' => $i, 'price' => $highs[$i]];
            }
        }
        return $swings;
    }

    private function findSwingLows(array $lows, int $lookback = 5): array
    {
        $swings = [];
        for ($i = $lookback; $i < count($lows) - $lookback; $i++) {
            $isLow = true;
            for ($j = 1; $j <= $lookback; $j++) {
                if ($lows[$i] >= $lows[$i - $j] || $lows[$i] >= $lows[$i + $j]) {
                    $isLow = false;
                    break;
                }
            }
            if ($isLow) {
                $swings[] = ['index' => $i, 'price' => $lows[$i]];
            }
        }
        return $swings;
    }

    private function detectCandlestickPatterns(array $opens, array $highs, array $lows, array $closes, int $index): array
    {
        $patterns = [];

        if ($index < 1) return $patterns;

        $open = $opens[$index];
        $high = $highs[$index];
        $low = $lows[$index];
        $close = $closes[$index];

        $body = abs($open - $close);
        $totalRange = $high - $low ?: 0.0001; // avoid division by zero

        $lowerWick = min($open, $close) - $low;
        $upperWick = $high - max($open, $close);

        // Bullish
        if ($lowerWick >= 2 * $body && $upperWick <= 0.5 * $body && $body > 0 && $close > $open) {
            $patterns[] = 'Hammer';
        }

        if ($index > 0) {
            $prevOpen = $opens[$index - 1];
            $prevClose = $closes[$index - 1];
            if ($prevClose < $prevOpen && $close > $open && $open <= $prevClose && $close >= $prevOpen) {
                $patterns[] = 'Bullish Engulfing';
            }
        }

        if ($lowerWick >= 2 * $body && $upperWick < $body && $close > $open) {
            $patterns[] = 'Bullish Pin Bar';
        }

        if (abs($open - $close) / $totalRange < 0.1 && $high == max($open, $close) && $lowerWick > 0.7 * $totalRange) {
            $patterns[] = 'Dragonfly Doji';
        }

        if ($index > 0) {
            $prevOpen = $opens[$index - 1];
            $prevClose = $closes[$index - 1];
            if ($prevClose < $prevOpen && $close > $open && $open > $prevClose && $close < $prevOpen) {
                $patterns[] = 'Bullish Harami';
            }
        }

        if ($index > 1) {
            if ($closes[$index - 2] > $opens[$index - 2] && $closes[$index - 1] > $opens[$index - 1] && $close > $open &&
                $closes[$index - 1] > $closes[$index - 2] && $close > $closes[$index - 1] &&
                $opens[$index - 1] > $opens[$index - 2] && $open > $opens[$index - 1]) {
                $patterns[] = 'Three White Soldiers';
            }
        }

        // Bearish
        if ($upperWick >= 2 * $body && $lowerWick <= 0.5 * $body && $body > 0 && $close < $open) {
            $patterns[] = 'Shooting Star';
        }

        if ($index > 0) {
            $prevOpen = $opens[$index - 1];
            $prevClose = $closes[$index - 1];
            if ($prevClose > $prevOpen && $close < $open && $open >= $prevClose && $close <= $prevOpen) {
                $patterns[] = 'Bearish Engulfing';
            }
        }

        if ($lowerWick >= 2 * $body && $upperWick <= 0.5 * $body && $body > 0 && $close < $open) {
            $patterns[] = 'Hanging Man';
        }

        if (abs($open - $close) / $totalRange < 0.1 && $low == min($open, $close) && $upperWick > 0.7 * $totalRange) {
            $patterns[] = 'Gravestone Doji';
        }

        if ($index > 0) {
            $prevOpen = $opens[$index - 1];
            $prevClose = $closes[$index - 1];
            if ($prevClose > $prevOpen && $close < $open && $open < $prevClose && $close > $prevOpen) {
                $patterns[] = 'Bearish Harami';
            }
        }

        if ($index > 1) {
            if ($closes[$index - 2] < $opens[$index - 2] && $closes[$index - 1] < $opens[$index - 1] && $close < $open &&
                $closes[$index - 1] < $closes[$index - 2] && $close < $closes[$index - 1] &&
                $opens[$index - 1] < $opens[$index - 2] && $open < $opens[$index - 1]) {
                $patterns[] = 'Three Black Crows';
            }
        }

        // Indecision
        if (abs($open - $close) / $totalRange < 0.1) {
            $patterns[] = 'Doji';
        }

        if ($body / $totalRange < 0.3 && $upperWick > 0.3 * $totalRange && $lowerWick > 0.3 * $totalRange) {
            $patterns[] = 'Spinning Top';
        }

        if ($index > 0) {
            $prevHigh = $highs[$index - 1];
            $prevLow = $lows[$index - 1];
            if ($high < $prevHigh && $low > $prevLow) {
                $patterns[] = 'Inside Bar';
            }
        }

        return $patterns;
    }

    private function detectChartPatterns(array $swingHighs, array $swingLows): array
    {
        $patterns = [];

        if (count($swingHighs) >= 2) {
            $lastHigh = end($swingHighs)['price'];
            $prevHigh = $swingHighs[count($swingHighs) - 2]['price'];
            if (abs($lastHigh - $prevHigh) / $prevHigh < 0.01) {
                $patterns[] = 'Double Top';
            }
        }

        if (count($swingLows) >= 2) {
            $lastLow = end($swingLows)['price'];
            $prevLow = $swingLows[count($swingLows) - 2]['price'];
            if (abs($lastLow - $prevLow) / $prevLow < 0.01) {
                $patterns[] = 'Double Bottom';
            }
        }

        if (count($swingHighs) >= 2 && count($swingLows) >= 2) {
            $highTrend = $swingHighs[count($swingHighs)-1]['price'] < $swingHighs[count($swingHighs)-2]['price'];
            $lowTrend = $swingLows[count($swingLows)-1]['price'] > $swingLows[count($swingLows)-2]['price'];
            if ($highTrend && $lowTrend) {
                $patterns[] = 'Ascending Triangle';
            } elseif (!$highTrend && !$lowTrend) {
                $patterns[] = 'Descending Triangle';
            }
        }

        return $patterns;
    }

    private function calculateFibRetracement(float $swingLow, float $swingHigh): array
    {
        $diff = $swingHigh - $swingLow;
        return [
            '0.382' => $swingHigh - 0.382 * $diff,
            '0.5' => $swingHigh - 0.5 * $diff,
            '0.618' => $swingHigh - 0.618 * $diff,
        ];
    }

    private function identifySupportLevels(array $lowPrices): array
    {
        $levels = [];
        $recent = array_slice($lowPrices, -50);
        for ($i = 1; $i < count($recent) - 1; $i++) {
            if ($recent[$i] < $recent[$i-1] && $recent[$i] < $recent[$i+1]) {
                $levels[] = $recent[$i];
            }
        }
        $levels = array_unique($levels);
        sort($levels);
        return $levels; // return all sorted ascending
    }

    private function identifyResistanceLevels(array $highPrices): array
    {
        $levels = [];
        $recent = array_slice($highPrices, -50);
        for ($i = 1; $i < count($recent) - 1; $i++) {
            if ($recent[$i] > $recent[$i-1] && $recent[$i] > $recent[$i+1]) {
                $levels[] = $recent[$i];
            }
        }
        $levels = array_unique($levels);
        sort($levels); // sort ascending for consistency
        return $levels;
    }

    private function getDynamicRRSignal(float $currentPrice, float $atr, array $fibLevels, array $srLevels, array $closePrices): array
    {
        $confidence = 50;
        $signal = 'NEUTRAL';

        $fibConfluence = $this->checkFibonacciConfluence($currentPrice, $fibLevels);
        $srConfluence = $this->checkSRConfluence($currentPrice, $srLevels);
        $trend = $this->calculateTrend($closePrices);
        $momentum = $this->calculateMomentum($closePrices);

        $totalScore = 0;

        if ($fibConfluence['signal'] === 'BUY') $totalScore += 30;
        elseif ($fibConfluence['signal'] === 'SELL') $totalScore -= 30;

        if ($srConfluence['signal'] === 'BUY') $totalScore += 25;
        elseif ($srConfluence['signal'] === 'SELL') $totalScore -= 25;

        if ($trend > 0.01) $totalScore += 20;
        elseif ($trend < -0.01) $totalScore -= 20;

        if ($momentum > 0.02) $totalScore += 15;
        elseif ($momentum < -0.02) $totalScore -= 15;

        if ($totalScore > 40) {
            $signal = 'BUY';
            $confidence = min(95, 50 + $totalScore);
        } elseif ($totalScore < -40) {
            $signal = 'SELL';
            $confidence = min(95, 50 + abs($totalScore));
        }

        return [$signal, round($confidence)];
    }

    private function checkFibonacciConfluence(float $currentPrice, array $fibLevels): array
    {
        foreach ($fibLevels as $level => $price) {
            $deviation = abs($currentPrice - $price) / $price;
            if ($deviation < 0.02) {
                return [
                    'signal' => (strpos($level, '0.3') !== false || strpos($level, '0.5') !== false) ? 'BUY' : 'SELL',
                    'level' => "Fib {$level}"
                ];
            }
        }
        return ['signal' => 'NEUTRAL', 'level' => 'None'];
    }

    private function checkSRConfluence(float $currentPrice, array $srLevels): array
    {
        $pivot = $srLevels['pivot'] ?? 0;
        $resistance1 = $srLevels['resistance1'] ?? 0;
        $support1 = $srLevels['support1'] ?? 0;

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

    private function calculateDynamicRREntry(string $signal, float $currentPrice, float $atr, array $fibLevels, array $srLevels): float
    {
        if ($signal === 'BUY') {
            $fib = $fibLevels['0.382'] ?? 0;
            $support = $srLevels['support1'] ?? 0;
            $reference = max($fib, $support);
            $discount = $currentPrice * 0.08;
            return max($reference, $currentPrice - $discount);
        } elseif ($signal === 'SELL') {
            $fib = $fibLevels['0.618'] ?? 0;
            $resistance = $srLevels['resistance1'] ?? 0;
            $reference = min($fib, $resistance);
            $premium = $currentPrice * 0.08;
            return min($reference, $currentPrice + $premium);
        } else {
            $offset = $currentPrice * 0.02;
            return $currentPrice + $offset * (rand(0, 1) ? 1 : -1);
        }
    }

    private function calculateDynamicRRLevels(string $signal, float $entryPrice, float $currentPrice, float $atr, array $fibLevels, array $srLevels): array
    {
        $baseRiskMultiplier = 1.5;
        $dynamicRiskReward = $this->calculateDynamicRiskReward($signal, $entryPrice, $fibLevels, $srLevels);

        if ($signal === 'BUY') {
            $stopLoss = $entryPrice - $atr * $baseRiskMultiplier;
            $takeProfit = $fibLevels['0.618'] > $entryPrice ? $fibLevels['0.618'] : $entryPrice + ($atr * $dynamicRiskReward);
        } elseif ($signal === 'SELL') {
            $stopLoss = $entryPrice + $atr * $baseRiskMultiplier;
            $takeProfit = $fibLevels['0.382'] < $entryPrice ? $fibLevels['0.382'] : $entryPrice - ($atr * $dynamicRiskReward);
        } else {
            $stopLoss = $entryPrice - ($atr * $baseRiskMultiplier);
            $takeProfit = $entryPrice + ($atr * $baseRiskMultiplier * $dynamicRiskReward);
        }

        $risk = abs($entryPrice - $stopLoss);
        $reward = abs($takeProfit - $entryPrice);
        $riskReward = $risk > 0 ? round($reward / $risk, 1) . ':1' : '1:1';

        return [
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward' => $riskReward
        ];
    }

    private function calculateDynamicRiskReward(string $signal, float $entryPrice, array $fibLevels, array $srLevels): float
    {
        $baseRR = 2.0;

        if ($signal === 'BUY' && $fibLevels['0.618'] > $entryPrice) {
            $baseRR += (($fibLevels['0.618'] - $entryPrice) / $entryPrice) * 2;
        } elseif ($signal === 'SELL' && $fibLevels['0.382'] < $entryPrice) {
            $baseRR += (($entryPrice - $fibLevels['0.382']) / $entryPrice) * 2;
        }

        return max($baseRR, 1.5);
    }

    private function calculateTrend(array $prices): float
    {
        if (count($prices) < 10) return 0;

        $firstHalf = array_slice($prices, 0, 5);
        $secondHalf = array_slice($prices, -5);

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        return ($secondAvg - $firstAvg) / $firstAvg;
    }

    private function calculateMomentum(array $prices): float
    {
        if (count($prices) < 5) return 0;

        $recent = array_slice($prices, -3);
        $previous = array_slice($prices, 0, -3);

        $recentAvg = array_sum($recent) / count($recent);
        $previousAvg = array_sum($previous) / count($previous);

        return ($recentAvg - $previousAvg) / $previousAvg;
    }

    private function calculateScore(string $signal, float $confidence): int
    {
        $baseScore = $confidence;
        if ($signal === 'BUY' || $signal === 'SELL') {
            return min(100, $baseScore + 10);
        }
        return min(100, $baseScore);
    }
}