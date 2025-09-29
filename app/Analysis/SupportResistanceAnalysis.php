<?php

namespace App\Analysis;

class SupportResistanceAnalysis implements AnalysisInterface
{
    protected array $indicators = [];
    protected string $notes = '';

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        // --- Dummy data (seharusnya ambil dari API market) ---
        $currentPrice = 113500.0;
        $support = 111000.0;
        $resistance = 118000.0;

        // Hitung indikator sederhana
        $sma = ($support + $resistance) / 2;
        $ema = ($currentPrice * 0.6) + ($sma * 0.4);

        $this->indicators = [
            'SMA' => round($sma, 2),
            'EMA' => round($ema, 2),
            'Support' => $support,
            'Resistance' => $resistance
        ];

        // Tentukan sinyal
        $signal = 'NEUTRAL';
        $confidence = 50;
        $entry = $currentPrice;
        $stopLoss = $support * 0.98;
        $takeProfit = $resistance * 1.02;

        if ($currentPrice <= $sma && $currentPrice > $support) {
            $signal = 'BUY';
            $confidence = 70;
            $this->notes = "Harga dekat support, potensi rebound.";
        } elseif ($currentPrice >= $sma && $currentPrice < $resistance) {
            $signal = 'SELL';
            $confidence = 65;
            $this->notes = "Harga dekat resistance, potensi rejection.";
        } else {
            $this->notes = "Harga berada di tengah range, sinyal lemah.";
        }

        $risk = $entry - $stopLoss;
        $reward = $takeProfit - $entry;
        $riskReward = $reward > 0 ? round($reward / $risk, 2) . ":1" : "N/A";

        return (object)[
            'title' => "Support & Resistance Analysis for {$symbol} ({$timeframe})",
            'description' => $this->getDescription(),
            'signal' => $signal,
            'confidence' => $confidence,
            'entry' => round($entry, 2),
            'stop_loss' => round($stopLoss, 2),
            'take_profit' => round($takeProfit, 2),
            'risk_reward' => $riskReward,
        ];
    }

    public function getCode(): string
    {
        return 'support_resistance';
    }

    public function getName(): string
    {
        return 'Support & Resistance Strategy';
    }

    public function getDescription(): string
    {
        return 'Menggunakan kombinasi support/resistance dan moving average (SMA, EMA) untuk mendeteksi sinyal trading. '
             . 'Alur: Ambil harga → Hitung support/resistance → Hitung SMA & EMA → Bandingkan harga saat ini dengan level tersebut → Tentukan sinyal BUY/SELL.';
    }

    public function getIndicators(): array
    {
        return $this->indicators;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }
}
