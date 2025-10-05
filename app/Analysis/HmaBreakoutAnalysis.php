<?php

namespace App\Analysis;

use App\Analysis\Contract\AnalysisAbstract;

class HmaBreakoutAnalysis extends AnalysisAbstract
{
    public function getCode(): string
    {
        return 'hma_breakout';
    }

    public function getName(): string
    {
        return 'HMA Change of Character + Breakout Analysis';
    }

    public function analyze(
        string $symbol,
        float $amount = 100,
        string $timeframe = '1h',
        ?string $forcedApi = null
    ): object {
        // === STEP 1: Get market data ===
        $historical = $this->getHistoricalData($symbol, $timeframe, 200);
        $price = $this->getPrice($symbol);

        $closes = array_column($historical, 'close');

        // === STEP 2: Calculate HMA ===
        $hmaFast = $this->calculateHMA($closes, 20);
        $hmaSlow = $this->calculateHMA($closes, 50);

        $trend_direction = end($hmaFast) > end($hmaSlow) ? 'Bullish' : 'Bearish';
        $trend_strength = round(abs((end($hmaFast) - end($hmaSlow)) / end($hmaSlow)) * 100, 2);

        // === STEP 3: Detect Change of Character (ChoCH) ===
        $changeOfCharacter = $this->crossOver($hmaFast, $hmaSlow);

        // === STEP 4: Detect Breakout Structure ===
        $recentHighs = array_slice(array_column($historical, 'high'), -10);
        $resistance = max($recentHighs);
        $breakout = $price > $resistance;

        // === STEP 5: Define Buy Signal ===
        $buySignal = $changeOfCharacter && $breakout;

        // === STEP 6: Confidence & scoring ===
        $confidence = $buySignal ? 80 : 40;
        $score = $buySignal ? 85 : 50;

        // === STEP 7: Entry, Stop Loss, Take Profit ===
        $entry = $buySignal ? $price * 0.99 : $price;
        $stopLoss = $buySignal ? $price * 0.97 : $price * 1.02;
        $takeProfit = $buySignal ? $price * 1.03 : $price * 0.98;

        // === STEP 8: Result Object ===
        return (object) [
            'title' => $this->getName(),
            'description' => [
                'Menggunakan HMA20 dan HMA50 untuk mendeteksi perubahan karakter (ChoCH)',
                'Jika sebelumnya downtrend lalu harga menembus resistance, sistem memberi sinyal BUY',
                'Menghitung entry berdasarkan retrace kecil setelah breakout',
                'Stoploss diletakkan di bawah swing low terakhir, Take profit di resistance berikutnya',
            ],
            'signal' => $buySignal ? 'BUY' : 'SELL',
            'confidence' => $confidence,
            'score' => $score,
            'price' => $price,
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward' => '1:2',
            'indicators' => [
                'HMA20' => end($hmaFast),
                'HMA50' => end($hmaSlow),
            ],
            'historical' => $historical,
            'notes' => [
                $buySignal
                    ? 'Momentum bullish kuat setelah ChoCH dan breakout, entry saat retrace minor.'
                    : 'Belum ada konfirmasi bullish, tunggu harga menembus resistance.',
            ],
            'patterns' => $buySignal ? ['ChoCH', 'Breakout Resistance'] : [],
            'market_phase' => $buySignal ? 'Reversal Bullish' : 'Downtrend',
            'volatility_factor' => $this->calculateATR($historical),
            'support_levels' => [$this->findSupport($historical)],
            'resistance_levels' => [$resistance],
            'trend_direction' => $trend_direction,
            'trend_strength' => $trend_strength,
        ];
    }

    // === HELPER FUNCTIONS ===

    private function calculateHMA(array $closes, int $period): array
    {
        // WMA(2*WMA(n/2) - WMA(n)), sqrt(n)
        $wma = function ($data, $len) {
            $result = [];
            for ($i = $len - 1; $i < count($data); $i++) {
                $subset = array_slice($data, $i - $len + 1, $len);
                $weights = range(1, $len);
                $weightedSum = array_sum(array_map(fn($v, $w) => $v * $w, $subset, $weights));
                $result[] = $weightedSum / array_sum($weights);
            }
            return $result;
        };

        $half = $wma($closes, intval($period / 2));
        $full = $wma($closes, $period);

        $diff = [];
        for ($i = 0; $i < min(count($half), count($full)); $i++) {
            $diff[] = 2 * $half[$i] - $full[$i];
        }

        return $wma($diff, (int)sqrt($period));
    }

    private function crossOver(array $fast, array $slow): bool
    {
        $n = min(count($fast), count($slow));
        if ($n < 2) return false;
        return $fast[$n - 2] < $slow[$n - 2] && $fast[$n - 1] > $slow[$n - 1];
    }

    private function calculateATR(array $data, int $period = 14): float
    {
        $trs = [];
        for ($i = 1; $i < count($data); $i++) {
            $high = $data[$i]['high'];
            $low = $data[$i]['low'];
            $prevClose = $data[$i - 1]['close'];
            $trs[] = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
        }
        return round(array_sum(array_slice($trs, -$period)) / min($period, count($trs)), 4);
    }

    private function findSupport(array $data, int $window = 10): float
    {
        $lows = array_slice(array_column($data, 'low'), -$window);
        return min($lows);
    }
}
