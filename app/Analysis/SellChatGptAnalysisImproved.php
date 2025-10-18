<?php

namespace App\Analysis;

use App\Analysis\Contract\AnalysisAbstract;

class SellChatGptAnalysisImproved extends AnalysisAbstract
{
    private array $timeframes = [
        '15m' => ['weight' => 1, 'period' => 15],
        '1h'  => ['weight' => 2, 'period' => 60],
        '4h'  => ['weight' => 3, 'period' => 240],
    ];

    private const SCORE_THRESHOLD = 70;
    private const RISK_REWARD_RATIO = 2;

    public function getCode(): string
    {
        return 'dynamic_multi_tf_sell_v2_manual_fixed';
    }

    public function getName(): string
    {
        return 'Dynamic Multi-TF Trend SELL (Fully Fixed)';
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        $results = [];
        $totalWeightedScore = 0;
        $totalWeight = 0;

        foreach ($this->timeframes as $tf => $config) {
            $res = $this->analyzeTimeframe($symbol, $tf);
            if ($res['valid']) {
                $results[$tf] = $res;
                $totalWeightedScore += $res['score'] * $config['weight'];
                $totalWeight += $config['weight'];
            }
        }

        if ($totalWeight === 0) {
            return $this->emptyObject('No valid data or all timeframes failed analysis.');
        }

        $finalScore = $totalWeightedScore / $totalWeight;
        $confidence = round($finalScore, 2);
        $signal = $finalScore >= self::SCORE_THRESHOLD ? 'SELL' : 'NEUTRAL';

        $mainTf = $results[$timeframe] ?? ($results['1h'] ?? end($results));

        return (object)[
            'title' => 'Dynamic Multi-Timeframe SELL Signal',
            'description' => [
                'Analisis dengan scoring dinamis dan perhitungan indikator yang lebih robust.',
                'Timeframe diberi bobot (4h > 1h > 15m) untuk konfirmasi tren utama.',
                'Indikator: EMA20, Supertrend, PSAR, MACD (dihitung manual, anti-crash).',
                'Score ≥ ' . self::SCORE_THRESHOLD . ' menghasilkan sinyal SELL.',
            ],
            'signal' => $signal,
            'confidence' => $confidence,
            'score' => round($finalScore),
            'price' => $mainTf['price'],
            'entry' => $mainTf['entry'],
            'stop_loss' => $mainTf['stop_loss'],
            'take_profit' => $mainTf['take_profit'],
            'risk_reward' => '1:' . self::RISK_REWARD_RATIO,
            'indicators' => $mainTf['indicators'],
            'historical' => $mainTf['historical'],
            'notes' => [
                "Signal: {$signal} (Weighted Score {$finalScore})",
                "Script telah diperbaiki secara keseluruhan untuk mencegah error.",
            ],
            'patterns' => [],
            'market_phase' => $signal === 'SELL' ? 'Downtrend' : 'Neutral',
            'volatility_factor' => $mainTf['atr_value'] ?? 0,
            'support_levels' => [],
            'resistance_levels' => [],
            'trend_direction' => $signal === 'SELL' ? 'Bearish' : 'Sideways',
            'trend_strength' => $confidence,
        ];
    }

    private function analyzeTimeframe(string $symbol, string $timeframe): array
    {
        $data = $this->getHistoricalData($symbol, $timeframe, 200);

        $minDataPoints = 50;
        if (empty($data) || count($data) < $minDataPoints) {
            return ['valid' => false, 'reason' => "Insufficient data (need >= {$minDataPoints}, got " . count($data) . ")"];
        }

        $closes = array_column($data, 'close');
        $highs  = array_column($data, 'high');
        $lows   = array_column($data, 'low');
        $price  = end($closes);

        // Hitung semua indikator
        $ema20 = $this->calculateEMA($closes, 20);
        $supertrend = $this->calculateSupertrend($highs, $lows, $closes, 10, 3);
        $psar = $this->calculatePSAR($highs, $lows);
        $macd = $this->calculateMACD($closes);
        $atr = $this->calculateATR($highs, $lows, $closes, 14);
        $currentAtr = end($atr);

        if ($currentAtr <= 0) {
             return ['valid' => false, 'reason' => 'ATR is zero or less, cannot calculate dynamic score/SL.'];
        }

        $scores = [];
        $scores['ema'] = $this->getEmaScore($price, $ema20, $currentAtr);
        $scores['supertrend'] = $this->getSupertrendScore($price, $supertrend, $currentAtr);
        $scores['psar'] = $this->getPsarScore($price, $psar, $currentAtr);
        $scores['macd'] = $this->getMacdScore($macd);

        $weights = ['ema' => 1, 'supertrend' => 1.5, 'psar' => 1.5, 'macd' => 1];

        $totalWeightedScore = 0;
        $totalWeight = 0;
        foreach ($scores as $key => $score) {
            $totalWeightedScore += $score * $weights[$key];
            $totalWeight += $weights[$key];
        }

        $finalScore = ($totalWeight > 0) ? $totalWeightedScore / $totalWeight : 0;

        // Dynamic SL/TP Calculation yang Lebih Aman
        $lookback = 5;
        $recentHigh = $price;
        $numCandles = count($highs);
        for ($i = $numCandles - $lookback; $i < $numCandles; $i++) {
            if ($i >= 0) {
                $recentHigh = max($recentHigh, $highs[$i]);
            }
        }

        $dynamicSL = max($recentHigh, end($psar), $price + (1.5 * $currentAtr));
        $risk = $dynamicSL - $price;
        $entryPrice = $price * 0.998;
        $dynamicTP = $entryPrice - ($risk * self::RISK_REWARD_RATIO);

        return [
            'valid' => true,
            'score' => $finalScore,
            'price' => $price,
            'entry' => $entryPrice,
            'stop_loss' => $dynamicSL,
            'take_profit' => $dynamicTP,
            'atr_value' => $currentAtr,
            'indicators' => [
                'EMA20' => ['value' => round($ema20, 4), 'score' => round($scores['ema'], 2)],
                'Supertrend' => ['value' => round(end($supertrend), 4), 'score' => round($scores['supertrend'], 2)],
                'PSAR' => ['value' => round(end($psar), 4), 'score' => round($scores['psar'], 2)],
                'MACD' => [
                    'macd' => round(end($macd['macd']), 4),
                    'signal' => round(end($macd['signal']), 4),
                    'histogram' => round(end($macd['hist']), 4),
                    'score' => round($scores['macd'], 2)
                ],
            ],
            'historical' => $data,
        ];
    }

    // --- Fungsi Scoring Dinamis ---
    private function getEmaScore(float $price, float $ema, float $atr): float { return ($price < $ema) ? min(100, max(0, (($ema - $price) / $atr) * 50)) : 0; }
    private function getSupertrendScore(float $price, array $supertrend, float $atr): float { $st = end($supertrend); return ($price < $st) ? min(100, max(0, (($st - $price) / $atr) * 50)) : 0; }
    private function getPsarScore(float $price, array $psar, float $atr): float { $ps = end($psar); return ($price < $ps) ? min(100, max(0, (($ps - $price) / $atr) * 50)) : 0; }
    private function getMacdScore(array $macd): float { $hist = end($macd['hist']); return ($hist < 0) ? min(100, max(0, abs($hist) * 100000)) : 0; }

    // --- SEMUA FUNGSI INDIKATOR MANUAL YANG LENGKAP DAN BENAR ---

    private function calculateEMA(array $closes, int $period): float
    {
        $count = count($closes);
        if ($count < $period) return end($closes);

        $multiplier = 2 / ($period + 1);
        $sma = array_sum(array_slice($closes, 0, $period)) / $period;
        $ema = $sma;

        for ($i = $period; $i < $count; $i++) {
            $ema = ($closes[$i] - $ema) * $multiplier + $ema;
        }
        return $ema;
    }

    private function calculateMACD(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): array
    {
        $count = count($closes);
        $macdLine = array_fill(0, $count, 0);
        $signalLine = array_fill(0, $count, 0);
        $histogram = array_fill(0, $count, 0);

        if ($count < $slow) {
            return ['macd' => $macdLine, 'signal' => $signalLine, 'hist' => $histogram];
        }

        $multiplierFast = 2 / ($fast + 1);
        $multiplierSlow = 2 / ($slow + 1);

        $smaFast = array_sum(array_slice($closes, 0, $fast)) / $fast;
        $smaSlow = array_sum(array_slice($closes, 0, $slow)) / $slow;

        $emaFast = array_fill(0, $count, $smaFast);
        $emaSlow = array_fill(0, $count, $smaSlow);

        for ($i = $slow; $i < $count; $i++) {
            $emaFast[$i] = ($closes[$i] - $emaFast[$i-1]) * $multiplierFast + $emaFast[$i-1];
            $emaSlow[$i] = ($closes[$i] - $emaSlow[$i-1]) * $multiplierSlow + $emaSlow[$i-1];
            $macdLine[$i] = $emaFast[$i] - $emaSlow[$i];
        }

        $firstSignalIndex = $slow + $signal - 1;
        $smaSignal = array_sum(array_slice($macdLine, $slow - 1, $signal)) / $signal;
        $emaSignal = $smaSignal;
        $signalLine[$firstSignalIndex] = $emaSignal;

        for ($i = $firstSignalIndex + 1; $i < $count; $i++) {
            $multiplierSignal = 2 / ($signal + 1);
            $emaSignal = ($macdLine[$i] - $emaSignal) * $multiplierSignal + $emaSignal;
            $signalLine[$i] = $emaSignal;
        }

        for ($i = 0; $i < $count; $i++) {
            $histogram[$i] = $macdLine[$i] - $signalLine[$i];
        }

        return ['macd' => $macdLine, 'signal' => $signalLine, 'hist' => $histogram];
    }

    /**
     * PENJELASAN: Fungsi Supertrend yang LENGKAP dan selalu mengembalikan array.
     */
    private function calculateSupertrend(array $high, array $low, array $close, int $period = 10, float $multiplier = 3): array
    {
        $atr = $this->calculateATR($high, $low, $close, $period);
        $count = count($close);
        if ($count === 0) return [];

        $supertrend = array_fill(0, $count, 0);
        $trend = array_fill(0, $count, 0);

        for ($i = 0; $i < $count; $i++) {
            $hl2 = ($high[$i] + $low[$i]) / 2;
            $ub = $hl2 + ($multiplier * ($atr[$i] ?? 0));
            $lb = $hl2 - ($multiplier * ($atr[$i] ?? 0));

            if ($i == 0) {
                $supertrend[$i] = $lb;
                $trend[$i] = -1;
            } else {
                if ($trend[$i-1] == 1 && $close[$i] <= $lb) {
                    $trend[$i] = -1;
                    $supertrend[$i] = $ub;
                } elseif ($trend[$i-1] == -1 && $close[$i] >= $ub) {
                    $trend[$i] = 1;
                    $supertrend[$i] = $lb;
                } else {
                    $trend[$i] = $trend[$i-1];
                    if ($trend[$i] == 1) {
                        $supertrend[$i] = min($lb, $supertrend[$i-1]);
                    } else {
                        $supertrend[$i] = max($ub, $supertrend[$i-1]);
                    }
                }
            }
        }
        return $supertrend;
    }

    /**
     * PENJELASAN: Fungsi PSAR yang LENGKAP dan selalu mengembalikan array.
     */
    private function calculatePSAR(array $high, array $low, float $step = 0.02, float $max = 0.2): array
    {
        $count = count($high);
        if($count === 0) return [];

        $psar = array_fill(0, $count, 0);
        $psar[0] = $low[0];
        $ep = $high[0];
        $af = $step;
        $upTrend = true;

        for ($i = 1; $i < $count; $i++) {
            $psar[$i] = $psar[$i - 1] + $af * ($ep - $psar[$i - 1]);

            if ($upTrend) {
                if ($low[$i] < $psar[$i]) {
                    $upTrend = false;
                    $psar[$i] = $ep;
                    $ep = $low[$i];
                    $af = $step;
                } elseif ($high[$i] > $ep) {
                    $ep = $high[$i];
                    $af = min($af + $step, $max);
                }
            } else {
                if ($high[$i] > $psar[$i]) {
                    $upTrend = true;
                    $psar[$i] = $ep;
                    $ep = $high[$i];
                    $af = $step;
                } elseif ($low[$i] < $ep) {
                    $ep = $low[$i];
                    $af = min($af + $step, $max);
                }
            }
        }
        return $psar;
    }

    /**
     * PENJELASAN: Fungsi ATR yang LENGKAP dan selalu mengembalikan array.
     */
    private function calculateATR(array $high, array $low, array $close, int $period): array
    {
        $count = count($high);
        if($count <= $period) {
            return array_fill(0, $count, 0);
        }

        $trs = [];
        for ($i = 1; $i < $count; $i++) {
            $trs[] = max(
                $high[$i] - $low[$i],
                abs($high[$i] - $close[$i - 1]),
                abs($low[$i] - $close[$i - 1])
            );
        }

        $atr = array_fill(0, $count, 0);
        $atr[$period] = array_sum(array_slice($trs, 0, $period)) / $period;

        for ($i = $period + 1; $i < $count; $i++) {
            $atr[$i] = (($atr[$i-1] * ($period - 1)) + $trs[$i-1]) / $period;
        }

        return $atr;
    }

    private function emptyObject(string $msg): object
    {
        return (object)[
            'title' => 'Analysis Error', 'description' => [$msg], 'signal' => 'NEUTRAL', 'confidence' => 0,
            'score' => 0, 'price' => 0, 'entry' => 0, 'stop_loss' => 0, 'take_profit' => 0, 'risk_reward' => '1:1',
            'indicators' => [], 'historical' => [], 'notes' => [$msg], 'patterns' => [], 'market_phase' => 'Error',
            'volatility_factor' => 0, 'support_levels' => [], 'resistance_levels' => [], 'trend_direction' => 'Error', 'trend_strength' => 0,
        ];
    }
}
