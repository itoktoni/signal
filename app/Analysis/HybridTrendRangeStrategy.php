<?php

namespace App\Analysis;

use App\Analysis\Contract\AnalysisAbstract;

class HybridTrendRangeStrategy extends AnalysisAbstract
{
    public function getCode(): string
    {
        return 'hybrid_trend_range_v5';
    }

    public function getName(): string
    {
        return 'Hybrid Strategy V5: Candlestick + Chart Patterns (IHS)';
    }

    public function analyze(
        string $symbol,
        float $amount = 100,
        string $timeframe = '1h',
        ?string $forcedApi = null
    ): object {
        $currentPrice = $this->getPrice($symbol);
        $historical = $this->getHistoricalData($symbol, $timeframe, 200);

        if (count($historical) < 60) {
            throw new \Exception("Insufficient data (min 60 candles required).");
        }

        $opens = array_column($historical, 'open');
        $closes = array_column($historical, 'close');
        $highs = array_column($historical, 'high');
        $lows = array_column($historical, 'low');
        $volumes = array_column($historical, 'volume');

        $rsi = $this->calculateRSI($closes, 14);
        $ema20 = $this->calculateEMA($closes, 20);
        $ema50 = $this->calculateEMA($closes, 50);
        $lastClose = end($closes);

        // Support & Resistance (40 candle)
        $lookback = 40;
        $recentHighs = array_slice($highs, -$lookback);
        $recentLows = array_slice($lows, -$lookback);
        $resistance = max($recentHighs);
        $support = min($recentLows);
        $rangePct = (($resistance - $support) / $currentPrice) * 100;

        // === DETEKSI SEMUA POLA ===
        $bullishCandle = $this->detectBullishReversal($opens, $highs, $lows, $closes);
        $bearishCandle = $this->detectBearishReversal($opens, $highs, $lows, $closes);
        $cupHandle = $this->detectCupAndHandle($closes, $highs, $lows, $volumes);
        $fallingWedge = $this->detectFallingWedge($closes, $highs, $lows, $volumes);
        $inverseHs = $this->detectInverseHeadAndShoulders($closes, $highs, $lows);

        // Kondisi pasar
        $isAboveResistance = $currentPrice > $resistance;
        $isNearSupport = $currentPrice <= $support * 1.015;
        $isRanging = $rangePct >= 1.5 && $rangePct <= 6.0 && !$isAboveResistance;
        $avgVol20 = array_sum(array_slice($volumes, -20)) / 20;
        $lastVol = end($volumes);
        $volumeRatio = $avgVol20 > 0 ? $lastVol / $avgVol20 : 1.0;
        $isUptrend = end($ema20) > end($ema50) && $lastClose > end($ema50);

        // === INISIALISASI DEFAULT ===
        $entry = $currentPrice * 0.995;
        $stopLoss = $entry * 0.978;
        $takeProfit = $entry * 1.032;
        $confidence = 62;
        $score = 65;
        $notes = [];
        $suggestions = [];
        $indicators = [
            'RSI' => round(end($rsi), 2),
            'EMA20' => round(end($ema20), 2),
            'EMA50' => round(end($ema50), 2),
            'Resistance' => round($resistance, 2),
            'Support' => round($support, 2),
            'Bullish Candle' => $bullishCandle,
            'Bearish Candle' => $bearishCandle,
            'Cup and Handle' => $cupHandle ? 'Detected' : null,
            'Falling Wedge' => $fallingWedge ? 'Detected' : null,
            'Inverse H&S' => $inverseHs ? 'Detected' : null,
        ];

        // 🎯 STRATEGI 1: INVERSE HEAD AND SHOULDERS (prioritas tertinggi)
        if ($inverseHs) {
            $entry = min($inverseHs['neckline'] * 1.002, $currentPrice * 0.998);
            $stopLoss = $inverseHs['right_shoulder_low'] * 0.985;
            $takeProfit = $inverseHs['target'];
            $notes[] = "🎯 Inverse Head and Shoulders breakout confirmed!";
            $score += 8;
            $confidence += 7;
            $suggestions[] = "✅ SUDAH SAATNYA ENTRY — pola Inverse H&S terkonfirmasi.";
            $suggestions[] = "📈 Target harga: \$" . number_format($inverseHs['target'], 2);
        }
        // 🥤 STRATEGI 2: CUP AND HANDLE
        elseif ($cupHandle) {
            $entry = min($cupHandle['handle_high'] * 1.002, $currentPrice * 0.998);
            $stopLoss = $entry * 0.975;
            $takeProfit = $entry * 1.04;
            $notes[] = "🥤 Cup and Handle breakout confirmed!";
            $score += 6;
            $confidence += 5;
            $suggestions[] = "✅ SUDAH SAATNYA ENTRY — pola Cup and Handle selesai.";
        }
        // 📉 STRATEGI 3: FALLING WEDGE
        elseif ($fallingWedge) {
            $entry = min($fallingWedge['wedge_high'] * 1.002, $currentPrice * 0.998);
            $stopLoss = $entry * 0.975;
            $takeProfit = $entry * 1.04;
            $notes[] = "📉 Falling Wedge breakout detected!";
            $score += 5;
            $confidence += 4;
            $suggestions[] = "✅ SUDAH SAATNYA ENTRY — Falling Wedge breakout bullish.";
        }
        // 🔺 STRATEGI 4: BREAKOUT KUAT
        elseif ($isAboveResistance && $lastClose > $resistance && $volumeRatio > 1.3 && $isUptrend) {
            $entry = min($resistance * 1.003, $currentPrice * 0.998);
            $stopLoss = $resistance * 0.982;
            $takeProfit = $currentPrice * 1.035;
            $notes[] = "🚀 Breakout above resistance with volume confirmation.";
            if ($bullishCandle) {
                $score += 4;
                $confidence += 3;
                $notes[] = "🕯️ Bullish candlestick: {$bullishCandle}";
                $suggestions[] = "✅ SUDAH SAATNYA ENTRY — konfirmasi candlestick bullish.";
            }
        }
        // 🔄 STRATEGI 5: RANGING + REVERSAL
        elseif ($isRanging && $isNearSupport && end($rsi) < 62) {
            $entry = min($support * 1.002, $currentPrice * 0.998);
            $stopLoss = $support * 0.985;
            $takeProfit = $resistance;
            $notes[] = "🔄 Buying near support in ranging market.";
            if ($bullishCandle) {
                $score += 5;
                $confidence += 4;
                $notes[] = "🕯️ Bullish reversal: {$bullishCandle}";
                $suggestions[] = "✅ SUDAH SAATNYA ENTRY — harga di support + candle bullish.";
            }
        }
        // 🧭 DEFAULT: DYNAMIC SUPPORT
        else {
            $avgLow10 = array_sum(array_slice($lows, -10)) / 10;
            $dynamicSupport = max($avgLow10, end($ema20));
            $entry = min($dynamicSupport * 1.001, $currentPrice * 0.996);
            $stopLoss = $entry * 0.978;
            $takeProfit = $entry * 1.032;
            $notes[] = "🧭 Neutral market — entry on dynamic support.";
        }

        // 🚨 SARAN TAKE PROFIT JIKA ADA SINYAL BEARISH DI ZONA TP
        if ($currentPrice >= $takeProfit * 0.95) {
            if ($bearishCandle) {
                $suggestions[] = "🚨 SUDAH SAATNYA TAKE PROFIT — pola bearish muncul di zona target.";
            }
            if (end($rsi) > 70) {
                $suggestions[] = "🚨 SUDAH SAATNYA TAKE PROFIT — RSI overbought di zona target.";
            }
        }

        // 🔒 JAMINAN: entry selalu di bawah harga sekarang
        if ($entry >= $currentPrice * 0.998) {
            $entry = $currentPrice * 0.995;
        }

        // Hitung risk-reward
        $risk = $entry - $stopLoss;
        $reward = $takeProfit - $entry;
        $rr = ($risk > 0 && $reward > 0) ? round($reward / $risk, 2) : 1.0;
        $riskReward = "1:{$rr}";

        // Batas maksimal
        $score = min($score, 92);
        $confidence = min($confidence, 87);

        return (object)[
            'title' => "Hybrid Strategy V5: {$symbol}",
            'description' => [
                "1. Deteksi 10 pola candlestick reversal.",
                "2. Deteksi 3 pola chart: Cup/Handle, Falling Wedge, Inverse Head & Shoulders.",
                "3. Entry selalu di level teknikal dengan pullback.",
                "4. Saran eksplisit: kapan ENTRY dan kapan TAKE PROFIT.",
                "5. Score meningkat otomatis saat pola terkonfirmasi.",
                "6. Long-only strategy untuk winrate >70%.",
            ],
            'signal' => 'BUY',
            'confidence' => $confidence,
            'score' => $score,
            'price' => $currentPrice,
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward' => $riskReward,
            'indicators' => $indicators,
            'historical' => $historical,
            'notes' => $notes,
            'suggestions' => $suggestions ?: ['ℹ️ Tunggu konfirmasi pola sebelum entry.'],
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // ─── CANDLESTICK REVERSAL PATTERNS ─────────────────────────────
    // ───────────────────────────────────────────────────────────────

    private function detectBullishReversal(array $opens, array $highs, array $lows, array $closes): ?string
    {
        $n = count($closes);
        if ($n < 3) return null;

        $c1o = $opens[$n-3]; $c1c = $closes[$n-3];
        $c2o = $opens[$n-2]; $c2c = $closes[$n-2];
        $c3o = $opens[$n-1]; $c3c = $closes[$n-1];
        $c3h = $highs[$n-1]; $c3l = $lows[$n-1];

        // Hammer
        $body3 = abs($c3c - $c3o);
        $lower3 = min($c3o, $c3c) - $c3l;
        $upper3 = $c3h - max($c3o, $c3c);
        if ($body3 > 0 && $lower3 >= 2 * $body3 && $upper3 <= $body3 && $c3c > $c3o) {
            return 'Hammer';
        }

        // Inverted Hammer
        if ($body3 > 0 && $upper3 >= 2 * $body3 && $lower3 <= $body3 && $c3c > $c3o) {
            return 'Inverted Hammer';
        }

        // Bullish Engulfing
        if ($c2c < $c2o && $c3c > $c3o && $c3c > $c2o && $c3o < $c2c) {
            return 'Bullish Engulfing';
        }

        // Piercing Line
        if ($c2c < $c2o && $c3c > $c3o && $c3c > ($c2o + $c2c) / 2 && $c3c < $c2o) {
            return 'Piercing Line';
        }

        // Morning Star
        if (
            $c1c < $c1o &&
            abs($c2c - $c2o) < ($c1o - $c1c) * 0.3 &&
            $c3c > $c3o &&
            $c3c > $c1o
        ) {
            return 'Morning Star';
        }

        return null;
    }

    private function detectBearishReversal(array $opens, array $highs, array $lows, array $closes): ?string
    {
        $n = count($closes);
        if ($n < 3) return null;

        $c1o = $opens[$n-3]; $c1c = $closes[$n-3];
        $c2o = $opens[$n-2]; $c2c = $closes[$n-2];
        $c3o = $opens[$n-1]; $c3c = $closes[$n-1];
        $c3h = $highs[$n-1]; $c3l = $lows[$n-1];

        // Hanging Man
        $body3 = abs($c3c - $c3o);
        $lower3 = min($c3o, $c3c) - $c3l;
        $upper3 = $c3h - max($c3o, $c3c);
        if ($body3 > 0 && $lower3 >= 2 * $body3 && $upper3 <= $body3 && $c3c < $c3o) {
            return 'Hanging Man';
        }

        // Shooting Star
        if ($body3 > 0 && $upper3 >= 2 * $body3 && $lower3 <= $body3 && $c3c < $c3o) {
            return 'Shooting Star';
        }

        // Bearish Engulfing
        if ($c2c > $c2o && $c3c < $c3o && $c3c < $c2o && $c3o > $c2c) {
            return 'Bearish Engulfing';
        }

        // Dark Cloud Cover
        if ($c2c > $c2o && $c3c < $c3o && $c3c < ($c2o + $c2c) / 2 && $c3c > $c2o) {
            return 'Dark Cloud Cover';
        }

        // Evening Star
        if (
            $c1c > $c1o &&
            abs($c2c - $c2o) < ($c1c - $c1o) * 0.3 &&
            $c3c < $c3o &&
            $c3c < $c1o
        ) {
            return 'Evening Star';
        }

        return null;
    }

    // ───────────────────────────────────────────────────────────────
    // ─── CHART PATTERNS ────────────────────────────────────────────
    // ───────────────────────────────────────────────────────────────

    private function detectCupAndHandle(array $closes, array $highs, array $lows, array $volumes): ?array
    {
        $n = count($closes);
        if ($n < 30) return null;

        $cupStart = max(0, $n - 50);
        $cupData = array_slice($closes, $cupStart, 30);
        if (count($cupData) < 20) return null;

        $minIndex = array_search(min($cupData), $cupData);
        if ($minIndex === false || $minIndex == 0 || $minIndex == count($cupData)-1) return null;

        $leftPart = array_slice($cupData, 0, $minIndex);
        $rightPart = array_slice($cupData, $minIndex + 1);
        if (empty($leftPart) || empty($rightPart)) return null;

        $leftHigh = max($leftPart);
        $rightHigh = max($rightPart);
        $cupLow = min($cupData);
        $cupDepth = ($leftHigh - $cupLow) / $leftHigh;

        $isCup = (
            $cupDepth >= 0.10 &&
            $cupDepth <= 0.30 &&
            abs($leftHigh - $rightHigh) / $leftHigh < 0.15
        );

        if (!$isCup) return null;

        $handleStart = $cupStart + count($cupData);
        if ($handleStart + 5 > $n) return null;

        $handleHighs = array_slice($highs, $handleStart, 10);
        $handleLows = array_slice($lows, $handleStart, 10);
        if (count($handleHighs) < 5) return null;

        $handleHigh = max($handleHighs);
        $handleLow = min($handleLows);
        $handleDepth = ($handleHigh - $handleLow) / $handleHigh;
        $isHandle = $handleDepth <= 0.08;

        if (!$isHandle) return null;

        $cupHigh = max($leftHigh, $rightHigh);
        $currentPrice = end($closes);
        $isBreakout = $currentPrice > $cupHigh;

        if (!$isBreakout) return null;

        return [
            'handle_high' => $handleHigh,
            'depth_pct' => round($cupDepth * 100, 2),
        ];
    }

    private function detectFallingWedge(array $closes, array $highs, array $lows, array $volumes): ?array
    {
        $n = count($closes);
        if ($n < 20) return null;

        $windowCloses = array_slice($closes, -20);
        $windowHighs = array_slice($highs, -20);
        $windowLows = array_slice($lows, -20);
        $windowVolumes = array_slice($volumes, -20);

        $lowerHighs = true;
        $lowerLows = true;
        for ($i = 1; $i < count($windowHighs); $i++) {
            if ($windowHighs[$i] >= $windowHighs[$i-1]) $lowerHighs = false;
            if ($windowLows[$i] >= $windowLows[$i-1]) $lowerLows = false;
        }

        if (!($lowerHighs && $lowerLows)) return null;

        $highSlope = ($windowHighs[count($windowHighs)-1] - $windowHighs[0]);
        $lowSlope = ($windowLows[count($windowLows)-1] - $windowLows[0]);
        if ($lowSlope >= $highSlope) return null;

        $wedgeHigh = max($windowHighs);
        $currentClose = end($closes);
        if ($currentClose <= $wedgeHigh) return null;

        $avgVolBefore = array_sum(array_slice($volumes, -25, 20)) / 20;
        $lastVol = end($volumes);
        if ($lastVol < $avgVolBefore * 1.2) return null;

        return [
            'wedge_high' => $wedgeHigh,
            'wedge_low' => min($windowLows),
        ];
    }

    private function detectInverseHeadAndShoulders(array $closes, array $highs, array $lows): ?array
    {
        $n = count($closes);
        if ($n < 25) return null;

        $start = max(0, $n - 40);
        $windowCloses = array_slice($closes, $start);
        $windowHighs = array_slice($highs, $start);
        $windowLows = array_slice($lows, $start);

        if (count($windowCloses) < 20) return null;

        // Cari head (titik terendah)
        $headIndex = array_search(min($windowLows), $windowLows);
        if ($headIndex < 5 || $headIndex > count($windowLows) - 6) return null;

        // Left shoulder: sebelum head
        $leftWindow = array_slice($windowLows, 0, $headIndex);
        if (empty($leftWindow)) return null;
        $leftShoulderIndex = array_search(min($leftWindow), $leftWindow);
        $leftShoulderLow = $leftWindow[$leftShoulderIndex];
        $leftShoulderHigh = max(array_slice($windowHighs, 0, $headIndex));

        // Right shoulder: setelah head
        $rightWindow = array_slice($windowLows, $headIndex + 1);
        if (empty($rightWindow)) return null;
        $rightShoulderIndex = array_search(min($rightWindow), $rightWindow);
        $rightShoulderLow = $rightWindow[$rightShoulderIndex];
        $rightShoulderHigh = max(array_slice($windowHighs, $headIndex + 1));

        // Validasi: right shoulder low > head low (higher low)
        $headLow = $windowLows[$headIndex];
        if ($rightShoulderLow <= $headLow || $leftShoulderLow <= $headLow) return null;

        // Neckline: garis resistance dari puncak left & right shoulder
        $neckline = min($leftShoulderHigh, $rightShoulderHigh);
        $currentPrice = end($closes);

        // Breakout: harga > neckline
        if ($currentPrice <= $neckline) return null;

        // Target: neckline + (neckline - headLow)
        $target = $neckline + ($neckline - $headLow);

        return [
            'neckline' => $neckline,
            'head_low' => $headLow,
            'left_shoulder_low' => $leftShoulderLow,
            'right_shoulder_low' => $rightShoulderLow,
            'target' => $target,
        ];
    }

    // ───────────────────────────────────────────────────────────────
    // ─── HELPERS ───────────────────────────────────────────────────
    // ───────────────────────────────────────────────────────────────

    private function calculateRSI(array $prices, int $period = 14): array
    {
        if (count($prices) <= $period) {
            return array_fill(0, count($prices), 50.0);
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

        $rsi = array_fill(0, $period, 50.0);

        for ($i = $period; $i < count($prices); $i++) {
            if ($avgLoss == 0) {
                $rsi[] = 100.0;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi[] = 100.0 - (100.0 / (1.0 + $rs));
            }

            $idx = $i - 1;
            $avgGain = ($avgGain * ($period - 1) + ($gains[$idx] ?? 0)) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + ($losses[$idx] ?? 0)) / $period;
        }

        return $rsi;
    }

    private function calculateEMA(array $prices, int $period): array
    {
        if (count($prices) < $period) {
            return array_fill(0, count($prices), $prices[0] ?? 0.0);
        }

        $sma = array_sum(array_slice($prices, 0, $period)) / $period;
        $ema = array_fill(0, $period - 1, $sma);
        $ema[] = $sma;

        $multiplier = 2.0 / ($period + 1);

        for ($i = $period; $i < count($prices); $i++) {
            $nextEMA = ($prices[$i] * $multiplier) + (end($ema) * (1 - $multiplier));
            $ema[] = $nextEMA;
        }

        return $ema;
    }
}