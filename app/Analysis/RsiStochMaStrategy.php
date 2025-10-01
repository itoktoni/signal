<?php

namespace App\Analysis;

use App\Analysis\Contract\AnalysisAbstract;
use App\Analysis\Contract\MarketDataInterface;
use stdClass;

/**
 * SupportResistanceBreakoutStrategy Class
 *
 * Implements a "Support and Resistance Breakout" trading strategy.
 * A BUY signal is generated when the price breaks above a key resistance level,
 * with confirmation from other indicators.
 *
 * 1. Find Key Levels: Automatically identifies significant support and resistance levels from recent price action.
 * 2. Detect Breakout: The primary signal is a candle closing above a resistance level.
 * 3. Confirmation: The breakout is validated by:
 * - A significant spike in trading volume.
 * - Bullish momentum indicated by the RSI (> 50).
 * - An overall uptrend confirmed by price being above a long-term SMA.
 */
class RsiStochMaStrategy extends AnalysisAbstract
{
    // --- Strategy Configuration ---
    private int $lookbackPeriod = 60;   // How many recent candles to analyze for S/R levels
    private int $pivotLookaround = 5;     // How many candles left/right to confirm a pivot point
    private int $volumeAvgPeriod = 20;
    private float $volumeSpikeFactor = 1.7; // Volume must be 1.7x the average for valid breakout
    private int $smaPeriod = 100;
    private int $rsiPeriod = 14;
    private float $riskRewardRatio = 2.5; // Target a 1:2.5 risk/reward

    public function getCode(): string
    {
        return 'sr_breakout';
    }

    public function getName(): string
    {
        return 'Support & Resistance Breakout Strategy';
    }

    public function analyze(
        string $symbol,
        float $amount = 100,
        string $timeframe = '4h',
        ?string $forcedApi = null
    ): object {
        $analysisResult = new stdClass();
        $analysisResult->title = $this->getName() . " ({$symbol} - {$timeframe})";
        $analysisResult->description = [];
        $analysisResult->indicators = [
            'SMA' => $this->smaPeriod,
            'RSI' => $this->rsiPeriod,
            'Volume MA' => $this->volumeAvgPeriod,
        ];

        // Step 1: Get Data
        $analysisResult->description[] = "Mengambil data historis OHLCV sebanyak 200 candle terakhir.";
        $historicalData = $this->getHistoricalData($symbol, $timeframe, 200);
        $analysisResult->historical = $historicalData;

        if (count($historicalData) < $this->lookbackPeriod) {
            throw new \Exception("Data historis tidak cukup untuk analisis (butuh setidaknya {$this->lookbackPeriod} candle).");
        }
        $currentPrice = $this->getPrice($symbol);
        $analysisResult->price = $currentPrice;

        // Step 2: Calculate Indicators
        $analysisResult->description[] = "Menghitung indikator teknikal: SMA, RSI, dan Rata-rata Volume.";
        $closePrices = array_column($historicalData, 'close');
        $volumes = array_column($historicalData, 'volume');
        $sma = $this->calculateSMA($closePrices, $this->smaPeriod);
        $rsi = $this->calculateRSI($closePrices, $this->rsiPeriod);
        $avgVolume = $this->calculateSMA($volumes, $this->volumeAvgPeriod);
        $lastVolume = end($volumes);

        $analysisResult->indicators['SMA_value'] = round($sma, 4);
        $analysisResult->indicators['RSI_value'] = round($rsi, 2);
        $analysisResult->indicators['Avg_Volume'] = round($avgVolume, 2);

        // Step 3: Find Support & Resistance Levels
        $analysisResult->description[] = "Mengidentifikasi level support & resistance kunci dari {$this->lookbackPeriod} candle terakhir.";
        $keyLevels = $this->findKeyLevels(array_slice($historicalData, -$this->lookbackPeriod), $this->pivotLookaround);
        $analysisResult->indicators['support_levels'] = $keyLevels['supports'];
        $analysisResult->indicators['resistance_levels'] = $keyLevels['resistances'];

        // Step 4: Detect Breakout and Score
        $analysisResult->description[] = "Mendeteksi sinyal breakout dan melakukan skoring.";
        $score = 0;
        $notes = [];
        $isBreakout = false;
        $brokenResistance = null;

        $lastCandle = end($historicalData);
        if (!empty($keyLevels['resistances'])) {
            // Find the highest resistance level that was broken by the last candle's close
            rsort($keyLevels['resistances']); // Sort from high to low
            foreach ($keyLevels['resistances'] as $res) {
                if ($lastCandle['close'] > $res && $lastCandle['open'] < $res) {
                    $brokenResistance = $res;
                    break;
                }
            }
        }

        if ($brokenResistance !== null) {
            $isBreakout = true;
            $score += 50;
            $notes[] = "✅ Breakout Terdeteksi: Harga berhasil ditutup di atas resistance kunci di level {$brokenResistance}.";

            // Confirmation 1: Volume Spike
            if ($lastVolume > ($avgVolume * $this->volumeSpikeFactor)) {
                $score += 25;
                $notes[] = "✅ Konfirmasi Volume: Volume ({$lastVolume}) melonjak di atas rata-rata ({$avgVolume}), memvalidasi breakout.";
            } else {
                $notes[] = "❌ Volume Lemah: Breakout terjadi dengan volume rendah, waspada breakout palsu.";
            }

            // Confirmation 2: RSI Momentum
            if ($rsi > 50) {
                $score += 15;
                $notes[] = "✅ Konfirmasi Momentum: RSI ({$rsi}) > 50, menunjukkan momentum bullish yang kuat.";
            } else {
                $notes[] = "❌ Momentum Lemah: RSI di bawah 50, pergerakan kurang meyakinkan.";
            }

            // Confirmation 3: General Trend
            if ($currentPrice > $sma) {
                $score += 10;
                $notes[] = "✅ Konfirmasi Tren: Harga berada di atas SMA {$this->smaPeriod}, searah dengan tren utama.";
            } else {
                $notes[] = "⚠️ Melawan Tren: Breakout terjadi di bawah SMA {$this->smaPeriod}, risiko lebih tinggi.";
            }
        } else {
            $notes[] = "Tidak ada sinyal breakout yang terdeteksi. Menunggu harga menembus level resistance.";
        }

        $analysisResult->score = $score;
        $analysisResult->confidence = $score;

        // Step 5: Calculate Trading Parameters (Mandatory)
        $analysisResult->description[] = "Menghitung parameter trading: entry, stop loss, dan take profit.";
        if ($isBreakout) {
            // Entry: Target a retest of the broken resistance, which now acts as support.
            $analysisResult->entry = round($brokenResistance, 4);

            // Stop Loss: Place it below the nearest significant support level.
            $slSupport = 0;
            if (!empty($keyLevels['supports'])) {
                sort($keyLevels['supports']);
                foreach (array_reverse($keyLevels['supports']) as $sup) {
                    if ($sup < $analysisResult->entry) {
                        $slSupport = $sup;
                        break;
                    }
                }
            }

            if ($slSupport) {
                 $analysisResult->stop_loss = round($slSupport * 0.995, 4); // 0.5% below the support
            } else {
                // Fallback: 3% below entry if no support found
                $analysisResult->stop_loss = round($analysisResult->entry * 0.97, 4);
            }

            // Take Profit
            $riskAmount = $analysisResult->entry - $analysisResult->stop_loss;
            if ($riskAmount > 0) {
                $rewardAmount = $riskAmount * $this->riskRewardRatio;
                $analysisResult->take_profit = round($analysisResult->entry + $rewardAmount, 4);
                $analysisResult->risk_reward = "1:" . number_format($this->riskRewardRatio, 1);
            } else {
                 $analysisResult->take_profit = 0;
                 $analysisResult->risk_reward = 'N/A';
                 $notes[] = "⚠️ Peringatan: Kalkulasi risiko tidak valid.";
            }
        } else {
            $analysisResult->entry = 0;
            $analysisResult->stop_loss = 0;
            $analysisResult->take_profit = 0;
            $analysisResult->risk_reward = 'N/A';
        }

        // Final Signal Determination
        if ($score >= 75) { // Breakout + Volume confirmation is our minimum for a strong signal
            $analysisResult->signal = 'BUY';
            $notes[] = "💡 Sinyal Beli Kuat: Skor {$score} memenuhi syarat minimal (75). Entry direkomendasikan pada retest level {$analysisResult->entry}.";
        } else {
            $analysisResult->signal = 'NEUTRAL';
        }

        $analysisResult->notes = $notes;
        $analysisResult->description[] = "Analisis selesai. Sinyal: {$analysisResult->signal} dengan skor {$analysisResult->score}.";

        return $analysisResult;
    }

    /**
     * Finds key support and resistance levels using pivot points.
     */
    private function findKeyLevels(array $data, int $lookaround): array
    {
        $supports = [];
        $resistances = [];
        $dataCount = count($data);

        if ($dataCount <= $lookaround * 2) {
            return ['supports' => [], 'resistances' => []];
        }

        for ($i = $lookaround; $i < $dataCount - $lookaround; $i++) {
            $isPivotHigh = true;
            $isPivotLow = true;

            for ($j = 1; $j <= $lookaround; $j++) {
                // Check for pivot high
                if ($data[$i]['high'] < $data[$i - $j]['high'] || $data[$i]['high'] < $data[$i + $j]['high']) {
                    $isPivotHigh = false;
                }
                // Check for pivot low
                if ($data[$i]['low'] > $data[$i - $j]['low'] || $data[$i]['low'] > $data[$i + $j]['low']) {
                    $isPivotLow = false;
                }
            }

            if ($isPivotHigh) {
                $resistances[] = $data[$i]['high'];
            }
            if ($isPivotLow) {
                $supports[] = $data[$i]['low'];
            }
        }
        return [
            'supports' => array_values(array_unique($supports)),
            'resistances' => array_values(array_unique($resistances))
        ];
    }

    private function calculateSMA(array $data, int $period): float
    {
        if (count($data) < $period) return 0;
        $slice = array_slice($data, -$period);
        return array_sum($slice) / count($slice);
    }

    private function calculateRSI(array $data, int $period): float
    {
        if (count($data) <= $period) return 50; // Neutral

        $changes = [];
        for ($i = 1; $i < count($data); $i++) {
            $changes[] = $data[$i] - $data[$i - 1];
        }

        $gains = $losses = 0;

        // First calculation
        for ($i = 0; $i < $period; $i++) {
            if ($changes[$i] > 0) {
                $gains += $changes[$i];
            } else {
                $losses -= $changes[$i];
            }
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        // Subsequent smoothing
        for ($i = $period; $i < count($changes); $i++) {
            $change = $changes[$i];
            $avgGain = ($avgGain * ($period - 1) + ($change > 0 ? $change : 0)) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + ($change < 0 ? -$change : 0)) / $period;
        }

        if ($avgLoss == 0) return 100;

        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }
}

