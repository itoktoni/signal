<?php

namespace App\Analysis;

use App\Analysis\AnalysisInterface;
use GuzzleHttp\Client;

class ReversalDetectionV2Analysis implements AnalysisInterface
{
    private string $apiUrl = 'https://api.binance.com/api/v3/klines';
    private float $usdIdr = 16000; // kurs USD → IDR
    private float $amount = 100;   // default USD
    private array $last = [];
    private string $timeframe = '15m'; // default timeframe

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '15m'): object
    {
        $this->amount = $amount;
        $this->timeframe = $timeframe;

        // === Ambil data Binance ===
        $client = new Client();
        $response = $client->get($this->apiUrl, [
            'query' => [
                'symbol'   => strtoupper($symbol),
                'interval' => $timeframe,
                'limit'    => 120
            ]
        ]);
        $data = json_decode($response->getBody()->getContents(), true);

        $closes  = array_map(fn($c) => (float) $c[4], $data);
        $opens   = array_map(fn($c) => (float) $c[1], $data);
        $highs   = array_map(fn($c) => (float) $c[2], $data);
        $lows    = array_map(fn($c) => (float) $c[3], $data);
        $volumes = array_map(fn($c) => (float) $c[5], $data);

        $currentPrice = end($closes);

        // === Hitung indikator ===
        $rsi   = $this->calculateRSI($closes, 14);
        $ema50 = $this->calculateEMA($closes, 50);
        $atr   = $this->calculateATR($highs, $lows, $closes, 14);
        $bullishEngulf = $this->detectBullishEngulfing($opens, $closes);
        $volumeSpike   = $this->detectVolumeSpike($volumes, 20);

        // === Support / Resistance sederhana ===
        $support    = min(array_slice($closes, -20));
        $resistance = max(array_slice($closes, -20));

        // === Tentukan signal ===
        $signal = 'NEUTRAL';
        $confidence = 50;
        $notes = [];

        if ($rsi < 30) {
            $notes[] = "RSI oversold ($rsi).";
            $signal = 'BUY';
            $confidence += 15;
        }
        if ($bullishEngulf) {
            $notes[] = "Bullish engulfing terdeteksi.";
            $confidence += 20;
            $signal = 'BUY';
        }
        if ($volumeSpike) {
            $notes[] = "Volume spike terdeteksi.";
            $confidence += 10;
        }
        if ($currentPrice > $ema50) {
            $notes[] = "Harga di atas EMA50 → trend filter positif.";
            $confidence += 10;
        } else {
            $notes[] = "Harga di bawah EMA50 → trend masih bearish.";
            $confidence -= 10;
        }

        // Clamp confidence ke range 0–100
        $confidence = max(0, min(100, $confidence));

        // === Entry, SL, TP ===
        $entry_usd       = $currentPrice;
        $stop_loss_usd   = $entry_usd - $atr;
        $take_profit_usd = $entry_usd + (2 * $atr);

        // === Konversi ke IDR ===
        $entry_idr       = $entry_usd * $this->usdIdr;
        $stop_loss_idr   = $stop_loss_usd * $this->usdIdr;
        $take_profit_idr = $take_profit_usd * $this->usdIdr;

        // === Hitung qty ===
        $qty = $amount / $entry_usd;

        // === Fee Pluang ===
        $fee_usd = $this->calculateFee($amount);
        $fee_idr = $fee_usd * $this->usdIdr;

        // === Risk Reward ===
        $risk   = ($entry_usd - $stop_loss_usd) * $qty;
        $reward = ($take_profit_usd - $entry_usd) * $qty;

        $risk_reward_ratio = $risk > 0 ? $reward / $risk : 0;
        $risk_reward = $risk > 0 ? "1:" . round($risk_reward_ratio, 2) : "0:0";

        // === Jika RR < 1 → reversal signal dibalik ===
        if ($risk_reward_ratio < 1 && $signal !== 'NEUTRAL') {
            $notes[] = "RR < 1 → sinyal dibalik.";
            $signal = $signal === 'BUY' ? 'SELL' : 'BUY';
            $confidence = max(60, $confidence - 10);
        }

        // === Potential P/L ===
        $potential_profit_usd = max(0, $reward - $fee_usd);
        $potential_loss_usd   = max(0, $risk + $fee_usd);

        // Save indikator terakhir
        $this->last = [
            'RSI'           => $rsi,
            'EMA50'         => $ema50,
            'ATR'           => $atr,
            'BullishEngulf' => $bullishEngulf,
            'VolumeSpike'   => $volumeSpike,
            'Support'       => $support,
            'Resistance'    => $resistance,
            'CurrentPrice'  => $currentPrice
        ];

        return (object) [
            'title'                => "Reversal Detection v2 for $symbol ($timeframe)",
            'description'          => $this->getDescription(),
            'signal'               => $signal,
            'confidence'           => $confidence,
            'entry_usd'            => $entry_usd,
            'entry_idr'            => $entry_idr,
            'stop_loss_usd'        => $stop_loss_usd,
            'stop_loss_idr'        => $stop_loss_idr,
            'take_profit_usd'      => $take_profit_usd,
            'take_profit_idr'      => $take_profit_idr,
            'qty'                  => $qty,
            'fee_usd'              => $fee_usd,
            'fee_idr'              => $fee_idr,
            'risk_reward'          => $risk_reward,
            'potential_profit_usd' => $potential_profit_usd,
            'potential_profit_idr' => $potential_profit_usd * $this->usdIdr,
            'potential_loss_usd'   => $potential_loss_usd,
            'potential_loss_idr'   => $potential_loss_usd * $this->usdIdr,
            'notes'                => implode(" ", $notes)
        ];
    }

    // === Implementasi Interface ===
    public function getCode(): string { return 'reversal_v2'; }
    public function getName(): string { return 'Reversal Detection v2'; }
    public function getDescription(): string
    {
        return 'Analisis reversal menggunakan RSI oversold, bullish engulfing, volume spike, ATR untuk SL/TP, dan EMA50 sebagai trend filter.';
    }
    public function getIndicators(): array { return $this->last; }
    public function getNotes(): string { return "Gunakan timeframe {$this->timeframe} untuk mendeteksi reversal."; }

    // === Helper ===
    private function calculateRSI(array $closes, int $period = 14): float
    {
        $gains = $losses = 0.0;
        for ($i = count($closes) - $period; $i < count($closes) - 1; $i++) {
            $diff = $closes[$i + 1] - $closes[$i];
            if ($diff > 0) $gains += $diff;
            else $losses -= $diff;
        }
        if ($losses == 0) return 100;
        $rs = $gains / $losses;
        return 100 - (100 / (1 + $rs));
    }

    private function calculateEMA(array $data, int $period): float
    {
        $k = 2 / ($period + 1);
        $ema = $data[0];
        foreach ($data as $price) {
            $ema = $price * $k + $ema * (1 - $k);
        }
        return $ema;
    }

    private function calculateATR(array $highs, array $lows, array $closes, int $period = 14): float
    {
        $trs = [];
        for ($i = 1; $i < count($closes); $i++) {
            $tr = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
            $trs[] = $tr;
        }
        return array_sum(array_slice($trs, -$period)) / $period;
    }

    private function detectBullishEngulfing(array $opens, array $closes): bool
    {
        $n = count($opens);
        $prevOpen = $opens[$n - 2];
        $prevClose = $closes[$n - 2];
        $lastOpen = $opens[$n - 1];
        $lastClose = $closes[$n - 1];
        return ($prevClose < $prevOpen) && ($lastClose > $lastOpen) &&
               ($lastClose > $prevOpen) && ($lastOpen < $prevClose);
    }

    private function detectVolumeSpike(array $volumes, int $period = 20): bool
    {
        $avg = array_sum(array_slice($volumes, -$period)) / $period;
        $last = end($volumes);
        return $last > 1.5 * $avg; // spike jika > 150% rata-rata
    }

    private function calculateFee(float $amount, string $type = 'maker'): float
    {
        $makerFee = 0.001;  // 0.10%
        $takerFee = 0.0015; // 0.15%
        $cfxFee   = 0.0002; // 0.02%
        $feeRate = $type === 'maker' ? $makerFee : $takerFee;
        $baseFee = $amount * ($feeRate + $cfxFee);
        return $baseFee + ($baseFee * 0.11); // + PPN 11%
    }
}
