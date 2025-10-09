<?php

namespace App\Analysis;

use App\Analysis\Contract\AnalysisAbstract;

class DaytradeBuyAnalysis extends AnalysisAbstract
{
    public function getCode(): string
    {
        return 'daytrade_buy';
    }

    public function getName(): string
    {
        return 'Daytrade Buy Strategy (1H/15m)';
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '15m', ?string $forcedApi = null): object
    {
        // --- Timeframe: 1H untuk Tren, 15m untuk Entry ---
        $historical15m = $this->getHistoricalData($symbol, '15m', 200);
        $historical1h = $this->getHistoricalData($symbol, '1h', 200);

        if (count($historical15m) < 100 || count($historical1h) < 50) {
            throw new \Exception("Not enough historical data for analysis.");
        }

        // --- Data & Harga Terkini ---
        $current = end($historical15m);
        $price = $current['close'];

        // --- 1. Analisa Tren Timeframe Besar (1H) menggunakan EMA 50 ---
        $ema_1h_50 = $this->calculateEMA(array_column($historical1h, 'close'), 50);
        $last_ema_1h = end($ema_1h_50);
        $last_price_1h = end($historical1h)['close'];
        $is_uptrend_1h = $last_price_1h > $last_ema_1h;

        // --- 2. Analisa Struktur & Sinyal di Timeframe Entry (15m) ---
        $bullish_reversal = $this->detectBullishReversalWithVolume($historical15m);
        $support = $this->findSupport($historical15m);
        $resistance = $this->findResistance($historical15m);
        $previous_high = $this->findPreviousHigh($historical15m);
        $is_break_of_structure = $price > $previous_high;

        // --- 3. Analisa Volatilitas & Risiko (15m) ---
        $atr_values = $this->calculateATR($historical15m, 14);
        $last_atr = end($atr_values);
        $volatility_percentage = ($last_atr / $price) * 100;

        // --- Kalkulasi Skor Kumulatif ---
        $confidence = 0;
        $notes = [];
        $setup_type = 'None';

        // Poin dari Tren Utama (1H)
        if ($is_uptrend_1h) {
            $confidence += 30;
            $notes[] = '[+30] TREN UTAMA: Tren 1 Jam Bullish (Harga di atas EMA50).';
            $setup_type = 'Trend Continuation';
        } else {
            $notes[] = '[+0] TREN UTAMA: Tren 1 Jam belum Bullish.';
            $setup_type = 'Potential Reversal';
        }

        // Poin dari Sinyal Entry (15m)
        if ($bullish_reversal['is_reversal']) {
            $confidence += 25;
            $notes[] = '[+25] SINYAL ENTRY: Ditemukan pola reversal bullish di 15m.';
        } else {
            $notes[] = '[+0] SINYAL ENTRY: Tidak ada pola reversal bullish yang jelas di 15m.';
        }

        // Poin bonus untuk Break of Structure, terutama jika melawan tren
        if ($is_break_of_structure) {
            $bonus_points = $is_uptrend_1h ? 10 : 20; // Bonus lebih besar jika ini sinyal pembalikan arah
            $confidence += $bonus_points;
            $notes[] = "[+{$bonus_points}] STRUKTUR: Harga menembus resistance sebelumnya di 15m, menandakan kekuatan buyer.";
        }

        // Poin dari Konfirmasi Volume
        if ($bullish_reversal['volume_confirmed']) {
            $confidence += 15;
            $notes[] = '[+15] VOLUME: Pola reversal didukung oleh volume yang kuat.';
        }

        // Poin dari Volatilitas
        if ($volatility_percentage < 1.0) { // Toleransi volatilitas lebih kecil untuk timeframe kecil
             $confidence += 10;
             $notes[] = '[+10] VOLATILITAS: Pasar tenang, risiko lebih terkendali.';
        }


        // --- Penentuan Entry, SL, TP, dan RRR ---
        $entry = $price;
        $stop_loss = $support - ($last_atr * 0.5);
        $take_profit = $resistance;
        $risk_reward_ratio = $this->calculateRRR($entry, $stop_loss, $take_profit);

        // --- Keputusan Sinyal Final ---
        $signal = 'WAIT';
        if ($confidence >= 75 && $risk_reward_ratio >= 1.5) {
            $signal = 'BUY';
        } elseif ($confidence >= 75) {
             $notes[] = "SINYAL DITAHAN: Skor bagus, tetapi Risk/Reward Ratio kurang dari 1.5 (Saat ini 1:{$risk_reward_ratio}).";
        }

        return (object)[
            'title' => $this->getName(),
            'description' => [
                'Strategi menggunakan EMA 1 Jam untuk tren dan sinyal 15 menit untuk entry.',
                'Skor dihitung kumulatif, menunjukkan kualitas setup secara keseluruhan.',
                'Sinyal BUY hanya aktif jika skor >= 75 dan RRR >= 1.5.'
            ],
            'signal' => $signal,
            'confidence' => $confidence,
            'score' => $confidence,
            'price' => $price,
            'entry' => $entry,
            'stop_loss' => round($stop_loss, 5),
            'take_profit' => round($take_profit, 5),
            'risk_reward' => '1:' . $risk_reward_ratio,
            'indicators' => [
                ['name' => 'EMA(50) on 1H', 'value' => round($last_ema_1h, 5)],
                ['name' => 'ATR(14) on 15m', 'value' => round($last_atr, 5)],
                ['name' => 'Previous High (Structure)', 'value' => round($previous_high, 5)]
            ],
            'historical' => $historical15m,
            'notes' => $notes,
            'patterns' => [
                ['name' => 'Setup Type', 'detected' => $setup_type],
                ['name' => 'Bullish Reversal 15m', 'detected' => $bullish_reversal['is_reversal']],
                ['name' => 'Break of Structure 15m', 'detected' => $is_break_of_structure],
            ],
            'market_phase' => $is_uptrend_1h ? 'Uptrend' : ($is_break_of_structure ? 'Early Reversal' : 'Downtrend/Sideways'),
            'volatility_factor' => round($volatility_percentage, 2) . '%',
            'support_levels' => [$support],
            'resistance_levels' => [$resistance],
            'trend_direction' => $is_uptrend_1h ? 'Bullish' : 'Neutral/Bearish',
            'trend_strength' => $is_uptrend_1h ? ($last_price_1h / $last_ema_1h - 1) * 100 : 0,
        ];
    }

    // --------------------------
    // Helper & Indicator Methods
    // --------------------------

    private function calculateEMA(array $closes, int $period): array
    {
        $ema = [];
        if(count($closes) < $period) return [end($closes) ?: 0];
        $multiplier = 2 / ($period + 1);
        $sma = array_sum(array_slice($closes, 0, $period)) / $period;
        $ema[] = $sma;
        for ($i = $period; $i < count($closes); $i++) {
            $ema_value = ($closes[$i] - end($ema)) * $multiplier + end($ema);
            $ema[] = $ema_value;
        }
        return $ema;
    }

    private function calculateATR(array $candles, int $period): array
    {
        if(count($candles) < $period + 1) return [0];
        $atr = [];
        $true_ranges = [];
        for ($i = 1; $i < count($candles); $i++) {
            $tr1 = $candles[$i]['high'] - $candles[$i]['low'];
            $tr2 = abs($candles[$i]['high'] - $candles[$i-1]['close']);
            $tr3 = abs($candles[$i]['low'] - $candles[$i-1]['close']);
            $true_ranges[] = max($tr1, $tr2, $tr3);
        }
        if(count($true_ranges) < $period) return [0];
        $atr[] = array_sum(array_slice($true_ranges, 0, $period)) / $period;
        for ($i = $period; $i < count($true_ranges); $i++) {
            $atr_value = ((end($atr) * ($period - 1)) + $true_ranges[$i]) / $period;
            $atr[] = $atr_value;
        }
        return $atr;
    }

    private function detectBullishReversalWithVolume(array $data): array
    {
        if (count($data) < 20) return ['is_reversal' => false, 'volume_confirmed' => false];
        $last_candle = end($data);
        $prev_candle = $data[count($data) - 2];
        $is_engulfing = $prev_candle['close'] < $prev_candle['open'] &&
                        $last_candle['close'] > $last_candle['open'] &&
                        $last_candle['close'] > $prev_candle['open'] &&
                        $last_candle['open'] < $prev_candle['close'];
        $volumes = array_column(array_slice($data, -20, 19), 'volume');
        $avg_volume = count($volumes) > 0 ? array_sum($volumes) / count($volumes) : 0;
        $volume_confirmed = $avg_volume > 0 && $last_candle['volume'] > ($avg_volume * 1.5);
        return ['is_reversal' => $is_engulfing, 'volume_confirmed' => $is_engulfing && $volume_confirmed];
    }

    private function findPreviousHigh(array $data): float
    {
        $slice = array_slice($data, -100, 99); // Look before the last candle
        if (empty($slice)) return PHP_FLOAT_MAX;

        $lows = array_column($slice, 'low');
        if (empty($lows)) return PHP_FLOAT_MAX;

        $min_low_val = min($lows);
        $min_low_key = array_search($min_low_val, $lows);

        $data_before_low = array_slice($slice, 0, $min_low_key + 1);
        if (empty($data_before_low)) {
           return max(array_column($slice, 'high'));
        }
        $highs_before_low = array_column($data_before_low, 'high');
        return max($highs_before_low);
    }

    private function findSupport(array $data): float
    {
        $lows = array_column(array_slice($data, -100), 'low');
        return min($lows);
    }

    private function findResistance(array $data): float
    {
        $highs = array_column(array_slice($data, -100), 'high');
        return max($highs);
    }

    private function calculateRRR(float $entry, float $sl, float $tp): float
    {
        if ($entry <= $sl) return 0;
        $risk = $entry - $sl;
        $reward = $tp - $entry;
        if ($risk <= 0 || $reward <= 0) return 0;
        return round($reward / $risk, 2);
    }
}
