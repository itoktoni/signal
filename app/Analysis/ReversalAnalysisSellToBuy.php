<?php

namespace App\Analysis;

use GuzzleHttp\Client;

class ReversalAnalysisSellToBuy implements AnalysisInterface
{
    private string $apiUrl = 'https://api.binance.com/api/v3/klines';
    private float $usdIdr = 16000;
    private float $amount = 100;
    private array $last = [];

    public function analyze(string $symbol, float $amount = 100): object
    {
        $this->amount = $amount;

        // === Ambil data harga ===
        $client = new Client();
        $response = $client->get($this->apiUrl, [
            'query' => [
                'symbol'   => strtoupper($symbol),
                'interval' => '15m',
                'limit'    => 200
            ]
        ]);
        $data = json_decode($response->getBody()->getContents(), true);

        $closes = array_map(fn($c) => (float) $c[4], $data);
        $highs  = array_map(fn($c) => (float) $c[2], $data);
        $lows   = array_map(fn($c) => (float) $c[3], $data);

        $currentPrice = end($closes);

        // === Indikator ===
        $rsi = $this->calculateRSI($closes, 14);
        [$support, $resistance] = $this->calculateSR($closes);
        $bullishEngulfing = $this->detectBullishEngulfing($data);

        // === Kondisi Reversal ===
        $confirmations = 0;
        $notes = [];

        if ($rsi < 30) {
            $confirmations++;
            $notes[] = "RSI < 30 → kondisi oversold.";
        }

        if ($currentPrice <= $support * 1.01) {
            $confirmations++;
            $notes[] = "Harga dekat support ($support).";
        }

        if ($bullishEngulfing) {
            $confirmations++;
            $notes[] = "Terbentuk pola bullish engulfing.";
        }

        // === Sinyal & Confidence ===
        $signal = 'NEUTRAL';
        $confidence = 50;

        if ($confirmations >= 2) {
            $signal = 'BUY';
            $confidence = 70 + ($confirmations * 10);
        }

        // === Notes dinamis ===
        if ($confirmations === 1) {
            $notes[] = "⚠️ Sinyal masih lemah, tunggu konfirmasi tambahan.";
        } elseif ($confirmations === 2) {
            $notes[] = "✅ Sinyal kuat, bisa mulai akumulasi.";
        } elseif ($confirmations >= 3) {
            $notes[] = "🔥 Triple konfirmasi reversal, high conviction!";
        }

        // === SL & TP ===
        $stop_loss_usd = min(array_slice($lows, -10));
        $take_profit_usd = $currentPrice + (($resistance - $currentPrice) * 0.8);

        $entry_usd = $currentPrice;
        $entry_idr = $entry_usd * $this->usdIdr;
        $stop_loss_idr = $stop_loss_usd * $this->usdIdr;
        $take_profit_idr = $take_profit_usd * $this->usdIdr;

        // === Hitung Qty ===
        $qty = $amount / $entry_usd;

        // === Fee Pluang ===
        $maker_fee = $amount * 0.0010;
        $cfx_fee   = $amount * 0.0002;
        $fee_before_ppn = $maker_fee + $cfx_fee;
        $ppn = $fee_before_ppn * 0.11;
        $fee_usd = $fee_before_ppn + $ppn;
        $fee_idr = $fee_usd * $this->usdIdr;

        // === Risk Reward ===
        $risk   = ($entry_usd - $stop_loss_usd) * $qty;
        $reward = ($take_profit_usd - $entry_usd) * $qty;

        $risk_reward_ratio = $risk > 0 ? $reward / $risk : 0;
        $risk_reward = $risk > 0 ? "1:" . round($risk_reward_ratio, 2) : "0:0";

        // === Potential P/L (net fee) ===
        $potential_profit = max(0, $reward - $fee_usd);
        $potential_loss   = max(0, $risk + $fee_usd);

        // === Save indikator terakhir ===
        $this->last = [
            'RSI'           => $rsi,
            'Support'       => $support,
            'Resistance'    => $resistance,
            'BullishEngulf' => $bullishEngulfing ? 1 : 0,
            'CurrentPrice'  => $currentPrice
        ];

        return (object) [
            'title'                => "Reversal Detection for $symbol (15m)",
            'description'          => "Deteksi reversal menggunakan RSI oversold, support-resistance, dan pola candlestick bullish engulfing.",
            'signal'               => $signal,
            'confidence'           => $confidence,
            'entry'                => $entry_usd,
            'stop_loss'            => $stop_loss_usd,
            'take_profit'          => $take_profit_usd,
            'risk_reward'          => $risk_reward,
            'fee'                  => $fee_usd,
            'potential_profit'     => $potential_profit,
            'potential_loss'       => $potential_loss,
            'notes'                => implode(" ", $notes)
        ];
    }

    public function getCode(): string
    {
        return 'reversal_detection';
    }

    public function getName(): string
    {
        return 'Reversal Detection (RSI + SR + Candlestick)';
    }

    public function getDescription(): string
    {
        return 'Analisis reversal dengan RSI, support-resistance, dan pola candlestick bullish engulfing.';
    }

    public function getIndicators(): array
    {
        return $this->last;
    }

    public function getNotes(): string
    {
        return "Gunakan timeframe 15m. Cocok untuk mendeteksi potensi pembalikan trend.";
    }

    // === Helper ===
    private function calculateRSI(array $closes, int $period = 14): float
    {
        $gains = 0; $losses = 0;
        for ($i = count($closes) - $period; $i < count($closes) - 1; $i++) {
            $diff = $closes[$i + 1] - $closes[$i];
            if ($diff > 0) $gains += $diff;
            else $losses -= $diff;
        }
        if ($losses == 0) return 100;
        $rs = $gains / $losses;
        return 100 - (100 / (1 + $rs));
    }

    private function calculateSR(array $closes): array
    {
        $recent = array_slice($closes, -50);
        $support = min($recent);
        $resistance = max($recent);
        return [$support, $resistance];
    }

    private function detectBullishEngulfing(array $data): bool
    {
        $c1 = $data[count($data) - 2];
        $c2 = $data[count($data) - 1];

        $open1 = (float) $c1[1]; $close1 = (float) $c1[4];
        $open2 = (float) $c2[1]; $close2 = (float) $c2[4];

        return ($close1 < $open1) && // candle 1 merah
               ($close2 > $open2) && // candle 2 hijau
               ($open2 < $close1) &&
               ($close2 > $open1);
    }
}
