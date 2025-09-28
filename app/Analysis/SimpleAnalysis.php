<?php
namespace App\Analysis;

use App\Analysis\AnalysisInterface;
use App\Analysis\ApiProviderManager;
use App\Settings\Settings;

class SimpleAnalysis implements AnalysisInterface
{
     private float $usdIdr     = 16000; // asumsi kurs
     private float $amount     = 100;   // default amount USD
     private string $timeframe = '1h';  // default timeframe
     private array $last       = [];
     private ApiProviderManager $apiManager;

     public function __construct(ApiProviderManager $apiManager)
     {
         $this->apiManager = $apiManager;
     }

    // === Implementasi Interface ===
    public function getCode(): string
    {
        return 'default_simple_analysis';
    }

    public function getName(): string
    {
        return 'MA + RSI + Volume + ATR + MACD Analysis';
    }

    public function getDescription(): string
    {
        return 'Analisis teknikal dengan MA20, MA50, RSI, Volume Ratio, ATR, MACD, Divergence untuk entry/SL/TP.';
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        $this->amount    = $amount;
        $this->timeframe = $timeframe;

        // === Ambil data harga ===
        $data = $this->apiManager->getHistoricalData(strtoupper($symbol), $timeframe, 200, $forcedApi);

        $closes  = array_map(fn($c) => (float) $c[4], $data);
        $highs   = array_map(fn($c) => (float) $c[2], $data);
        $lows    = array_map(fn($c) => (float) $c[3], $data);
        $volumes = array_map(fn($c) => (float) $c[5], $data);

        $currentPrice = end($closes);

        // === Hitung indikator ===
        $ma20        = array_sum(array_slice($closes, -20)) / 20;
        $ma50        = array_sum(array_slice($closes, -50)) / 50;
        $rsi         = $this->calculateRSI($closes, 14);
        $atr         = $this->calculateATR($highs, $lows, $closes, 14);
        $volumeRatio = $volumes[count($volumes) - 1] / (array_sum(array_slice($volumes, -20)) / 20);

        [$macd, $signalLine, $histogram] = $this->calculateMACD($closes);
        $volumeDivergence                = $this->detectVolumeDivergence($closes, $volumes);

        // === Sinyal awal ===
        $signal     = 'NEUTRAL';
        $confidence = 50;
        $notes      = [];

        if ($ma20 < $ma50 && $rsi < 40 && $currentPrice < $ma20 && $macd < $signalLine) {
            $signal     = 'SELL';
            $confidence = 80;
            $notes[]    = 'Harga di bawah MA20 & MA50, RSI rendah, MACD bearish.';
        } elseif ($ma20 > $ma50 && $rsi > 60 && $currentPrice > $ma20 && $macd > $signalLine) {
            $signal     = 'BUY';
            $confidence = 80;
            $notes[]    = 'Harga di atas MA20 & MA50, RSI kuat, MACD bullish.';
        }

        // === Volume divergence konfirmasi ===
        if ($volumeDivergence === 'bullish') {
            $notes[] = 'Volume divergence bullish → potensi pembalikan naik.';
            if ($signal === 'SELL') {
                $signal = 'NEUTRAL';
                $confidence -= 20;
            }
        } elseif ($volumeDivergence === 'bearish') {
            $notes[] = 'Volume divergence bearish → potensi pembalikan turun.';
            if ($signal === 'BUY') {
                $signal = 'NEUTRAL';
                $confidence -= 20;
            }
        }

        // === Stop Loss & TP ===
        $recentHigh = max(array_slice($highs, -10));
        $recentLow  = min(array_slice($lows, -10));

        if ($signal === 'BUY') {
            $entry_usd       = $currentPrice;
            $stop_loss_usd   = $recentLow;
            $take_profit_usd = $entry_usd + $atr * 2;
        } elseif ($signal === 'SELL') {
            $entry_usd       = $currentPrice;
            $stop_loss_usd   = $recentHigh;
            $take_profit_usd = $entry_usd - $atr * 2;
        } else {
            $entry_usd       = $currentPrice;
            $stop_loss_usd   = $entry_usd;
            $take_profit_usd = $entry_usd;
        }

        // === Konversi IDR ===
        $entry_idr       = $entry_usd * $this->usdIdr;
        $stop_loss_idr   = $stop_loss_usd * $this->usdIdr;
        $take_profit_idr = $take_profit_usd * $this->usdIdr;

        // === Hitung Qty ===
        $qty = $amount / $entry_usd;

                                            // === Fee Pluang (Maker default) ===
        $maker_fee      = $amount * 0.001;  // 0.10%
        $cfx_fee        = $amount * 0.0002; // 0.02%
        $fee_before_ppn = $maker_fee + $cfx_fee;
        $ppn            = $fee_before_ppn * 0.11;
        $fee_usd        = $fee_before_ppn + $ppn;
        $fee_idr        = $fee_usd * $this->usdIdr;

        // === Risk & Reward ===
        if ($signal === 'BUY') {
            $risk   = ($entry_usd - $stop_loss_usd) * $qty;
            $reward = ($take_profit_usd - $entry_usd) * $qty;
        } elseif ($signal === 'SELL') {
            $risk   = ($stop_loss_usd - $entry_usd) * $qty;
            $reward = ($entry_usd - $take_profit_usd) * $qty;
        } else {
            $risk   = 0;
            $reward = 0;
        }

        $risk_reward_ratio = $risk > 0 ? $reward / $risk : 0;
        $risk_reward       = $risk > 0 ? '1:' . round($risk_reward_ratio, 2) : '0:0';

        // === Jika RR < 1, balikkan signal ===
        if ($risk_reward_ratio < 1 && $signal !== 'NEUTRAL') {
            if ($signal === 'SELL') {
                $signal  = 'BUY';
                $notes[] = 'Reversal: RR < 1, SELL tidak menguntungkan → dibalik jadi BUY.';
            } elseif ($signal === 'BUY') {
                $signal  = 'SELL';
                $notes[] = 'Reversal: RR < 1, BUY tidak menguntungkan → dibalik jadi SELL.';
            }
            $confidence = max(60, $confidence - 10);
        }

        // === Potential P/L (net fee) ===
        $potential_profit_usd = max(0, $reward - $fee_usd);
        $potential_loss_usd   = max(0, $risk + $fee_usd);

        $potential_profit_idr = $potential_profit_usd * $this->usdIdr;
        $potential_loss_idr   = $potential_loss_usd * $this->usdIdr;

        // === Save indikator terakhir ===
        $this->last = [
            'ma20'          => $ma20,
            'ma50'          => $ma50,
            'rsi'           => $rsi,
            'atr'           => $atr,
            'volume_ratio'  => $volumeRatio,
            'macd'          => $macd,
            'signal_line'   => $signalLine,
            'histogram'     => $histogram,
            'current_price' => $currentPrice,
        ];

        // Add API provider information to notes
        $apiProvider = $forcedApi ? strtoupper($forcedApi) : 'BinanceApi';
        $notes[] = "⚡️ data From {$apiProvider}";

        return (object) [
            'title'            => "MA20/50 + RSI + Volume + ATR + MACD Analysis for $symbol ($timeframe)",
            'description'      => "Analisis teknikal dengan MA20, MA50, RSI, Volume Ratio, ATR, MACD, Divergence pada timeframe $timeframe.",
            'signal'           => $signal,
            'confidence'       => $confidence,
            'entry'            => $entry_usd,
            'stop_loss'        => $stop_loss_usd,
            'take_profit'      => $take_profit_usd,
            'qty'              => $qty,
            'fee'              => $fee_usd,
            'risk_usd'         => $risk,
            'reward_usd'       => $reward,
            'risk_reward'      => $risk_reward,
            'potential_profit' => $potential_profit_usd,
            'potential_loss'   => $potential_loss_usd,
            'notes'            => implode(' ', $notes) ?: "Gunakan timeframe $timeframe. SL diambil dari swing high/low, TP dihitung ATR × 2. ⚡️ data From {$apiProvider}",
        ];
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getIndicators(): array
    {
        return [
            'MA20'         => round($this->last['ma20'] ?? 0, 2),
            'MA50'         => round($this->last['ma50'] ?? 0, 2),
            'RSI'          => round($this->last['rsi'] ?? 0, 2),
            'ATR'          => round($this->last['atr'] ?? 0, 2),
            'VolumeRatio'  => round($this->last['volume_ratio'] ?? 0, 2),
            'MACD'         => round($this->last['macd'] ?? 0, 2),
            'SignalLine'   => round($this->last['signal_line'] ?? 0, 2),
            'Histogram'    => round($this->last['histogram'] ?? 0, 2),
            'CurrentPrice' => round($this->last['current_price'] ?? 0, 2),
        ];
    }

    public function getNotes(): string
    {
        return "Gunakan timeframe {$this->timeframe}. Konfirmasi dengan MACD & Volume Divergence.";
    }

    // === Helper ===
    private function calculateRSI(array $closes, int $period = 14): float
    {
        $gains  = 0;
        $losses = 0;
        for ($i = count($closes) - $period; $i < count($closes) - 1; $i++) {
            $diff = $closes[$i + 1] - $closes[$i];
            if ($diff > 0) {
                $gains += $diff;
            } else {
                $losses -= $diff;
            }
        }
        if ($losses == 0) {
            return 100;
        }
        $rs = $gains / $losses;
        return 100 - 100 / (1 + $rs);
    }

    private function calculateATR(array $highs, array $lows, array $closes, int $period = 14): float
    {
        $trs = [];
        for ($i = 1; $i < count($closes); $i++) {
            $tr    = max($highs[$i] - $lows[$i], abs($highs[$i] - $closes[$i - 1]), abs($lows[$i] - $closes[$i - 1]));
            $trs[] = $tr;
        }
        return array_sum(array_slice($trs, -$period)) / $period;
    }

    private function calculateEMA(array $data, int $period): array
    {
        $k      = 2 / ($period + 1);
        $ema    = [];
        $ema[0] = array_sum(array_slice($data, 0, $period)) / $period;
        for ($i = $period; $i < count($data); $i++) {
            $ema[] = ($data[$i] - end($ema)) * $k + end($ema);
        }
        return $ema;
    }

    private function calculateMACD(array $closes): array
    {
        $ema12    = $this->calculateEMA($closes, 12);
        $ema26    = $this->calculateEMA($closes, 26);
        $macdLine = [];
        $len      = min(count($ema12), count($ema26));
        for ($i = 0; $i < $len; $i++) {
            $macdLine[] = $ema12[$i] - $ema26[$i];
        }
        $signalLine = $this->calculateEMA($macdLine, 9);
        $lastMacd   = end($macdLine);
        $lastSignal = end($signalLine);
        $histogram  = $lastMacd - $lastSignal;
        return [$lastMacd, $lastSignal, $histogram];
    }

    private function detectVolumeDivergence(array $closes, array $volumes): string
    {
        $recentCloseChange  = end($closes) - $closes[count($closes) - 5];
        $recentVolumeChange = end($volumes) - $volumes[count($volumes) - 5];

        if ($recentCloseChange > 0 && $recentVolumeChange < 0) {
            return 'bearish';
        } elseif ($recentCloseChange < 0 && $recentVolumeChange > 0) {
            return 'bullish';
        }
        return 'none';
    }
}
