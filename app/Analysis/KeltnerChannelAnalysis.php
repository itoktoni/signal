<?php

namespace App\Analysis;

use App\Analysis\Contract\AnalysisAbstract;
use Illuminate\Support\Arr;

class KeltnerChannelAnalysis extends AnalysisAbstract
{
    public function getCode(): string
    {
        return 'keltner_channel';
    }

    public function getName(): string
    {
        return 'Keltner Channel Pro Analysis';
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        // === 1. Get market data ===
        $historical = $this->getHistoricalData($symbol, $timeframe, 200);
        $price      = $this->getPrice($symbol);

        // Prepare arrays
        $closes  = array_column($historical, 'close');
        $highs   = array_column($historical, 'high');
        $lows    = array_column($historical, 'low');
        $volumes = array_column($historical, 'volume');
        $count   = count($closes);

        if ($count < 50) {
            throw new \Exception("Not enough data for analysis");
        }

        // === 2. Helper functions ===
        $ema = function(array $data, int $period): float {
            $k = 2 / ($period + 1);
            $ema = $data[0];
            for ($i = 1; $i < count($data); $i++) {
                $ema = $data[$i] * $k + $ema * (1 - $k);
            }
            return $ema;
        };

        $atr = function(array $highs, array $lows, array $closes, int $period = 14): float {
            $trs = [];
            for ($i = 1; $i < count($highs); $i++) {
                $tr = max(
                    $highs[$i] - $lows[$i],
                    abs($highs[$i] - $closes[$i - 1]),
                    abs($lows[$i] - $closes[$i - 1])
                );
                $trs[] = $tr;
            }
            return array_sum(array_slice($trs, -$period)) / $period;
        };

        $rsi = function(array $data, int $period = 14): float {
            $gains = $losses = [];
            for ($i = 1; $i < count($data); $i++) {
                $change = $data[$i] - $data[$i - 1];
                $gains[] = max(0, $change);
                $losses[] = max(0, -$change);
            }
            $avgGain = array_sum(array_slice($gains, -$period)) / $period;
            $avgLoss = array_sum(array_slice($losses, -$period)) / $period;
            if ($avgLoss == 0) return 100;
            $rs = $avgGain / $avgLoss;
            return 100 - (100 / (1 + $rs));
        };

        // === 3. Calculate indicators ===
        $ema20  = $ema(array_slice($closes, -20), 20);
        $ema50  = $ema(array_slice($closes, -50), 50);
        $ema200 = $ema(array_slice($closes, -200), min(200, $count));
        $atrVal = $atr($highs, $lows, $closes, 10);
        $rsiVal = $rsi($closes, 14);

        $upperKC = $ema20 + $atrVal * 1.5;
        $lowerKC = $ema20 - $atrVal * 1.5;
        $volAvg  = array_sum(array_slice($volumes, -20)) / 20;
        $volumeOK = end($volumes) > $volAvg;
        $trendUp  = $ema50 > $ema200;

        // === 4. Determine signal ===
        $signal = 'NEUTRAL';
        $confidence = 50;
        $description = [];
        $trendStrength = 0;

        if ($trendUp && end($closes) > $upperKC && $rsiVal > 55 && $volumeOK) {
            $signal = 'BUY';
            $confidence = 75;
            $trendStrength = 80;
            $description[] = "Harga menembus upper Keltner Channel dengan RSI > 55 dan volume tinggi, menandakan momentum bullish yang kuat.";
        } elseif (!$trendUp && end($closes) < $lowerKC && $rsiVal < 45) {
            $signal = 'SELL';
            $confidence = 65;
            $trendStrength = 70;
            $description[] = "Harga menembus bawah Keltner Channel dengan RSI < 45, sinyal bearish.";
        } else {
            $signal = 'WAIT';
            $confidence = 50;
            $trendStrength = 40;
            $description[] = "Belum ada konfirmasi breakout atau trend kuat. Tunggu sinyal berikutnya.";
        }

        // === 5. Entry, SL, TP calculation ===
        $entry = $signal === 'BUY' ? $price * 0.99 : ($signal === 'SELL' ? $price * 1.01 : $price);
        $stop_loss = $signal === 'BUY' ? $lowerKC * 0.98 : ($signal === 'SELL' ? $upperKC * 1.02 : $price * 0.97);
        $take_profit = $signal === 'BUY' ? $price * 1.04 : ($signal === 'SELL' ? $price * 0.96 : $price * 1.02);
        $risk_reward = "1:2";

        // === 6. Return structured result ===
        return (object) [
            'title'              => 'Keltner Channel Analysis',
            'description'        => $description,
            'signal'             => $signal,
            'confidence'         => round($confidence, 2),
            'score'              => round(($confidence + $trendStrength) / 2, 2),
            'price'              => $price,
            'entry'              => round($entry, 4),
            'stop_loss'          => round($stop_loss, 4),
            'take_profit'        => round($take_profit, 4),
            'risk_reward'        => $risk_reward,
            'indicators' => [
                'EMA20' => round($ema20, 4),
                'EMA50' => round($ema50, 4),
                'EMA200' => round($ema200, 4),
                'ATR' => round($atrVal, 4),
                'RSI' => round($rsiVal, 2),
                'Volume Avg' => round($volAvg, 2)
            ],
            'historical'         => $historical,
            'notes'              => [
                $signal === 'BUY'
                    ? "Entry lebih aman setelah pullback kecil di dekat $entry"
                    : ($signal === 'SELL'
                        ? "Waspada potensi short covering di atas $upperKC"
                        : "Tunggu konfirmasi breakout sebelum entry")
            ],
            'patterns'           => [],
            'market_phase'       => $trendUp ? 'Uptrend' : 'Downtrend',
            'volatility_factor'  => round($atrVal / end($closes) * 100, 2),
            'support_levels'     => [$lowerKC, $ema50],
            'resistance_levels'  => [$upperKC, $ema50 * 1.02],
            'trend_direction'    => $trendUp ? 'Bullish' : 'Bearish',
            'trend_strength'     => $trendStrength,
        ];
    }
}
