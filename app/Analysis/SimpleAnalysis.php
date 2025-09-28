<?php

namespace App\Analysis;

use App\Analysis\AnalysisInterface;

class SimpleAnalysis implements AnalysisInterface
{
    protected float $usdIdr = 16000;
    protected float $amount;
    protected string $timeframe;
    protected array $indicators = [];
    protected string $notes = '';
    protected $apiManager;

    public function __construct($apiManager)
    {
        $this->apiManager = $apiManager;
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        $this->amount    = $amount;
        $this->timeframe = $timeframe;

        // === Ambil data timeframe utama ===
        $data = $this->apiManager->getHistoricalData(strtoupper($symbol), $timeframe, 200, $forcedApi);
        $closes  = array_map(fn($c) => (float) $c[4], $data);
        $highs   = array_map(fn($c) => (float) $c[2], $data);
        $lows    = array_map(fn($c) => (float) $c[3], $data);
        $volumes = array_map(fn($c) => (float) $c[5], $data);
        $lastClose = end($closes);

        // === Ambil data multi timeframe (konfirmasi trend) ===
        $h4 = $this->apiManager->getHistoricalData(strtoupper($symbol), "4h", 200, $forcedApi);
        $d1 = $this->apiManager->getHistoricalData(strtoupper($symbol), "1d", 200, $forcedApi);

        $ema200_1h = $this->ema($closes, 200);
        $ema200_4h = $this->ema(array_map(fn($c) => (float) $c[4], $h4), 200);
        $ema200_1d = $this->ema(array_map(fn($c) => (float) $c[4], $d1), 200);

        // === Indikator utama ===
        $ema20  = $this->ema($closes, 20);
        $ema50  = $this->ema($closes, 50);
        $rsi    = $this->rsi($closes, 14);
        $atr    = $this->atr($highs, $lows, $closes, 14);
        $macd   = $this->macd($closes, 12, 26, 9);
        $stoch  = $this->stochasticRsi($closes, 14);
        $bbands = $this->bollingerBands($closes, 20, 2);

        $volSma = $this->sma($volumes, 20);
        $volumeStrength = end($volumes) / ($volSma ?: 1);

        // Support / Resistance
        $recentHigh = max(array_slice($highs, -50));
        $recentLow  = min(array_slice($lows, -50));
        $breakout = null;
        if ($lastClose > $recentHigh * 1.004) {
            $breakout = 'resistance';
            $this->notes .= "Breakout resistance di $recentHigh. ";
        } elseif ($lastClose < $recentLow * 0.996) {
            $breakout = 'support';
            $this->notes .= "Breakout support di $recentLow. ";
        } else {
            $this->notes .= "Belum ada breakout, perhatikan area $recentHigh / $recentLow. ";
        }

        // === Risk Reward ===
        $slLong = min(array_slice($lows, -5)) - ($atr * 0.5);
        $tpLong = $lastClose + ($atr * 2);
        $rrLong = ($tpLong - $lastClose) / max(0.00001, ($lastClose - $slLong));

        $slShort = max(array_slice($highs, -5)) + ($atr * 0.5);
        $tpShort = $lastClose - ($atr * 2);
        $rrShort = ($lastClose - $tpShort) / max(0.00001, ($slShort - $lastClose));

        // === Pilih skenario ===
        $signal = "NO TRADE";
        $entry = $stopLoss = $takeProfit = null;
        $rr = "N/A";

        if ($rrLong >= $rrShort) {
            $signal = 'BUY';
            $entry = $lastClose;
            $stopLoss = $slLong;
            $takeProfit = $tpLong;
            $rr = "1:" . round($rrLong, 2);
        } else {
            $signal = 'SELL';
            $entry = $lastClose;
            $stopLoss = $slShort;
            $takeProfit = $tpShort;
            $rr = "1:" . round($rrShort, 2);
        }

        // === Confidence Score ===
        $confidence = $this->calculateConfidence(
            $ema20, $ema50, $ema200_1h, $ema200_4h, $ema200_1d,
            $rsi, $macd, $volumeStrength, $atr, $breakout, $stoch, $bbands, $signal, $lastClose
        );

        // Apply threshold
        if ($confidence < 40) {
            $signal = "NO TRADE";
            $this->notes .= "Confidence rendah ($confidence%), tidak direkomendasikan entry. ";
        }

        // === Simpan indikator ===
        $this->indicators = [
            'ema20'  => $ema20,
            'ema50'  => $ema50,
            'ema200_1h' => $ema200_1h,
            'ema200_4h' => $ema200_4h,
            'ema200_1d' => $ema200_1d,
            'rsi'    => $rsi,
            'atr'    => $atr,
            'macd'   => $macd['macd'],
            'macd_signal' => $macd['signal'],
            'stoch_rsi' => $stoch,
            'bollinger' => $bbands,
            'volume_strength' => $volumeStrength,
        ];

        return (object) [
            'title'       => 'Multi-Timeframe Advanced Analysis',
            'description' => $this->getDescription(),
            'signal'      => $signal,
            'confidence'  => $confidence,
            'entry'       => $entry,
            'stop_loss'   => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward' => $rr,
            'indicators'  => $this->getIndicators(),
            'notes'       => trim($this->getNotes()),
        ];
    }

    public function getCode(): string { return 'multi_tf_analysis'; }
    public function getName(): string { return 'Multi-Timeframe Advanced Analysis'; }
    public function getDescription(): string
    {
        return 'Analisis multi-timeframe (1h,4h,1d) dengan filter EMA200, indikator RSI, MACD, Stochastic RSI, Bollinger Bands, ATR, Volume. Menggunakan risk/reward nyata dan confidence threshold.';
    }
    public function getIndicators(): array { return $this->indicators; }
    public function getNotes(): string { return $this->notes; }

    // ===== Helper indikator =====
    private function sma(array $values, int $period): float {
        if (count($values) < $period) return 0;
        return array_sum(array_slice($values, -$period)) / $period;
    }

    private function ema(array $values, int $period): float {
        if (count($values) < $period) return 0;
        $k = 2 / ($period + 1);
        $ema = $values[0];
        for ($i = 1; $i < count($values); $i++) {
            $ema = ($values[$i] * $k) + ($ema * (1 - $k));
        }
        return $ema;
    }

    private function rsi(array $closes, int $period = 14): float {
        $gains = $losses = 0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff >= 0) $gains += $diff; else $losses -= $diff;
        }
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;
        if ($avgLoss == 0) return 100;
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    private function macd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): array {
        $emaFast = $this->ema($closes, $fast);
        $emaSlow = $this->ema($closes, $slow);
        $macd = $emaFast - $emaSlow;
        $signalLine = $this->ema([$macd], $signal);
        return ['macd' => $macd, 'signal' => $signalLine];
    }

    private function atr(array $highs, array $lows, array $closes, int $period = 14): float {
        $trs = [];
        for ($i = 1; $i < count($closes); $i++) {
            $tr = max([
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1]),
            ]);
            $trs[] = $tr;
        }
        return array_sum(array_slice($trs, -$period)) / max(1, $period);
    }

    private function stochasticRsi(array $closes, int $period = 14): float {
        $rsi = $this->rsi($closes, $period);
        $min = min(array_slice($closes, -$period));
        $max = max(array_slice($closes, -$period));
        return ($max - $min == 0) ? 50 : (($rsi - $min) / ($max - $min)) * 100;
    }

    private function bollingerBands(array $closes, int $period = 20, float $multiplier = 2): array {
        $sma = $this->sma($closes, $period);
        $slice = array_slice($closes, -$period);
        $variance = array_sum(array_map(fn($c) => pow($c - $sma, 2), $slice)) / $period;
        $stdDev = sqrt($variance);
        return [
            'upper' => $sma + ($multiplier * $stdDev),
            'middle' => $sma,
            'lower' => $sma - ($multiplier * $stdDev),
        ];
    }

    private function calculateConfidence(
        float $ema20, float $ema50, float $ema200_1h, float $ema200_4h, float $ema200_1d,
        float $rsi, array $macd, float $volumeStrength, float $atr,
        ?string $breakout, float $stoch, array $bbands, string $signal, float $lastClose
    ): float {
        $score = 50;

        // EMA trend
        if ($ema20 > $ema50 && $ema50 > $ema200_1h) $score += 10;
        elseif ($ema20 < $ema50 && $ema50 < $ema200_1h) $score -= 10;

        // Multi TF EMA200 filter
        if ($signal === "BUY" && ($lastClose < $ema200_1h || $lastClose < $ema200_4h || $lastClose < $ema200_1d)) {
            $score -= 20; $this->notes .= "Trend filter menolak BUY. ";
        }
        if ($signal === "SELL" && ($lastClose > $ema200_1h || $lastClose > $ema200_4h || $lastClose > $ema200_1d)) {
            $score -= 20; $this->notes .= "Trend filter menolak SELL. ";
        }

        // RSI
        if ($rsi > 70) $score -= 8;
        elseif ($rsi < 30) $score += 8;

        // MACD
        if ($macd['macd'] > $macd['signal']) $score += 6;
        else $score -= 6;

        // Volume
        if ($volumeStrength > 1.5) $score += 5;
        elseif ($volumeStrength < 0.7) $score -= 5;

        // Breakout
        if ($breakout === 'resistance') $score += 5;
        elseif ($breakout === 'support') $score -= 5;

        // Stochastic RSI
        if ($stoch > 80) $score -= 5;
        elseif ($stoch < 20) $score += 5;

        // Bollinger Band squeeze breakout
        if ($lastClose > $bbands['upper']) $score += 4;
        elseif ($lastClose < $bbands['lower']) $score -= 4;

        // ATR
        if ($atr > 0) $score += 2;

        return max(20, min(98, $score));
    }
}
