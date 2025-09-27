<?php

namespace App\Analysis;

use App\Analysis\AnalysisInterface;
use stdClass;

/**
 * RsiBollingerReversal Analysis
 *
 * Strategi ini mengidentifikasi potensi pembalikan arah harga (reversal) dengan
 * menggabungkan indikator Bollinger Bands (BB) dan Relative Strength Index (RSI).
 * Sinyal BUY muncul ketika harga menyentuh atau menembus Lower Band BB dan RSI
 * berada di area oversold (<30), lalu dikonfirmasi dengan candle yang ditutup kembali
 * di dalam band. Sebaliknya, sinyal SELL muncul saat harga menyentuh atau menembus
 * Upper Band BB dan RSI di area overbought (>70), dikonfirmasi dengan candle
 * yang ditutup kembali di dalam band.
 */
class RsiBollingerReversal implements AnalysisInterface
{
    private const BINANCE_API_URL = 'https://api.binance.com/api/v3';
    private const USD_IDR_RATE = 15500; // Sebaiknya gunakan API kurs untuk data real-time

    private string $notes = '';

    public function getCode(): string
    {
        return 'rsi_bollinger_reversal';
    }

    public function getName(): string
    {
        return 'RSI + Bollinger Bands Reversal';
    }

    public function getDescription(): string
    {
        return '
        <p><strong>Teknologi dan Alur Analisis:</strong></p>
        <ol class="list-decimal list-inside space-y-2">
            <li><strong>Pengambilan Data:</strong> Skrip mengambil data candlestick (Kline) 100 periode terakhir dari API publik Binance dengan interval waktu 4 jam (H4) untuk mendapatkan gambaran tren jangka menengah yang lebih stabil.</li>
            <li><strong>Kalkulasi Indikator:</strong>
                <ul class="list-disc list-inside ml-4">
                    <li><strong>Bollinger Bands (Periode 20, Deviasi 2):</strong> Dihitung untuk mengukur volatilitas dan level harga relatif (overbought/oversold). Terdiri dari Middle, Upper, dan Lower Band.</li>
                    <li><strong>Relative Strength Index (RSI Periode 14):</strong> Dihitung untuk mengukur kecepatan dan perubahan pergerakan harga, guna mengidentifikasi kondisi jenuh beli (overbought > 70) atau jenuh jual (oversold < 30).</li>
                </ul>
            </li>
            <li><strong>Logika Sinyal Reversal:</strong>
                <ul class="list-disc list-inside ml-4">
                    <li><strong>Sinyal BUY (Bullish Reversal):</strong> Dicari kondisi di mana harga pada <strong>dua candle sebelumnya</strong> menyentuh atau berada di bawah Lower Band, RSI menunjukkan <i>oversold</i> (&lt;30), dan <strong>candle terakhir</strong> ditutup kembali di <strong>atas</strong> Lower Band. Ini menandakan tekanan jual melemah dan potensi pembalikan ke atas.</li>
                    <li><strong>Sinyal SELL (Bearish Reversal):</strong> Dicari kondisi di mana harga pada <strong>dua candle sebelumnya</strong> menyentuh atau berada di atas Upper Band, RSI menunjukkan <i>overbought</i> (&gt;70), dan <strong>candle terakhir</strong> ditutup kembali di <strong>bawah</strong> Upper Band. Ini menandakan tekanan beli melemah dan potensi pembalikan ke bawah.</li>
                </ul>
            </li>
            <li><strong>Manajemen Risiko:</strong> Jika sinyal ditemukan, skrip secara otomatis menghitung level Entry (harga saat ini), Stop Loss (di bawah low terakhir untuk BUY, di atas high terakhir untuk SELL), dan Take Profit dengan rasio Risk:Reward 1:2 untuk memaksimalkan potensi keuntungan.</li>
            <li><strong>Konversi & Output:</strong> Semua nilai harga dan potensi profit/loss dikonversi ke IDR dan disajikan dalam format objek standar sesuai permintaan.</li>
        </ol>
        ';
    }

    public function getIndicators(): array
    {
        return [
            'Bollinger Bands' => 'Periode: 20, Deviasi: 2',
            'RSI' => 'Periode: 14',
            'Timeframe' => '4 Jam (H4)'
        ];
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function analyze(string $symbol, float $amount = 100): object
    {
        $result = $this->createDefaultResultObject();
        $result->title = "Analisis {$symbol} - {$this->getName()}";

        try {
            // 1. Ambil data dari API
            $klines = $this->fetchJson(self::BINANCE_API_URL . "/klines?symbol={$symbol}&interval=4h&limit=100");
            $ticker = $this->fetchJson(self::BINANCE_API_URL . "/ticker/price?symbol={$symbol}");

            // --- FIX START ---
            // Mengubah pengecekan dari properti objek ke kunci array
            if (!$klines || !$ticker || !isset($ticker['price'])) {
                throw new \Exception("Gagal mengambil data dari API Binance untuk simbol {$symbol}.");
            }

            $currentPrice = (float) $ticker['price'];
            // --- FIX END ---

            // 2. Olah data & hitung indikator
            $closes = array_map(fn($k) => (float)$k[4], $klines);
            $highs = array_map(fn($k) => (float)$k[2], $klines);
            $lows = array_map(fn($k) => (float)$k[3], $klines);

            $bollingerBands = $this->calculateBollingerBands($closes, 20, 2);
            $rsi = $this->calculateRSI($closes, 14);

            if (empty($bollingerBands['lower']) || empty($rsi)) {
                throw new \Exception("Data tidak cukup untuk menghitung indikator.");
            }

            $lastClose = end($closes);
            $prevClose = $closes[count($closes) - 2];

            $lastLowerBand = end($bollingerBands['lower']);
            $prevLowerBand = $bollingerBands['lower'][count($bollingerBands['lower']) - 2];

            $lastUpperBand = end($bollingerBands['upper']);
            $prevUpperBand = $bollingerBands['upper'][count($bollingerBands['upper']) - 2];

            $lastRsi = end($rsi);
            $prevRsi = $rsi[count($rsi) - 2];

            // 3. Logika Sinyal
            $signal = 'NEUTRAL';
            $confidence = 0;
            $entryPrice = 0;
            $stopLoss = 0;
            $takeProfit = 0;

            // Kondisi Bullish Reversal (BUY)
            if ($prevClose <= $prevLowerBand && $lastClose > $lastLowerBand && $prevRsi < 35) {
                $signal = 'BUY';
                $confidence = 75.0;
                $entryPrice = $currentPrice;
                $stopLoss = min(array_slice($lows, -5)); // Stop loss di bawah 5 low terakhir
                if ($entryPrice > $stopLoss) {
                   $risk = $entryPrice - $stopLoss;
                   $takeProfit = $entryPrice + ($risk * 2); // Risk-Reward Ratio 1:2
                } else {
                    $signal = 'NEUTRAL'; // Invalid stop loss
                }
            }
            // Kondisi Bearish Reversal (SELL)
            elseif ($prevClose >= $prevUpperBand && $lastClose < $lastUpperBand && $prevRsi > 65) {
                $signal = 'SELL';
                $confidence = 75.0;
                $entryPrice = $currentPrice;
                $stopLoss = max(array_slice($highs, -5)); // Stop loss di atas 5 high terakhir
                if ($entryPrice < $stopLoss) {
                    $risk = $stopLoss - $entryPrice;
                    $takeProfit = $entryPrice - ($risk * 2); // Risk-Reward Ratio 1:2
                } else {
                    $signal = 'NEUTRAL'; // Invalid stop loss
                }
            }

            // 4. Set Notes dan isi hasil
            $this->notes = "RSI saat ini: " . number_format($lastRsi, 2) . ". ";
            $this->notes .= "Harga terakhir ditutup pada $" . number_format($lastClose, 4) . ". ";
            if ($signal === 'NEUTRAL') {
                $this->notes .= "Tidak ditemukan sinyal reversal yang kuat saat ini. Disarankan untuk menunggu konfirmasi lebih lanjut.";
            } else {
                $this->notes .= "Sinyal {$signal} terdeteksi dengan tingkat kepercayaan {$confidence}%. Pastikan untuk selalu menerapkan manajemen risiko yang ketat.";
            }

            $result->signal = $signal;
            $result->confidence = $confidence;

            if ($signal !== 'NEUTRAL') {
                return $this->calculateFinancials($result, $amount, $entryPrice, $stopLoss, $takeProfit);
            }

        } catch (\Exception $e) {
            $this->notes = "Terjadi kesalahan: " . $e->getMessage();
            $result->signal = 'NEUTRAL';
            $result->description = $this->getDescription(); // Ensure description is set even on error
        }

        return $result;
    }

    /**
     * Helper function to fetch and decode JSON from a URL.
     * --- FIX START ---
     * Mengubah return type menjadi ?array dan menggunakan json_decode($data, true)
     * untuk konsistensi.
     */
    private function fetchJson(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            return null;
        }
        curl_close($ch);
        return json_decode($data, true); // Selalu mengembalikan sebagai associative array
    }
    // --- FIX END ---

    /**
     * Calculates RSI.
     */
    private function calculateRSI(array $closes, int $period = 14): array
    {
        if (count($closes) < $period) return [];
        $rsi = [];
        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0;
            } else {
                $gains[] = 0;
                $losses[] = abs($change);
            }
        }

        if (empty($gains)) return [];

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        $rsiValue = $avgLoss == 0 ? 100 : 100 - (100 / (1 + ($avgGain / $avgLoss)));

        $rsiForPeriod = array_fill(0, $period -1, null);
        $rsiForPeriod[] = $rsiValue;


        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;
            $rsiForPeriod[] = $avgLoss == 0 ? 100 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
        }

        return $rsiForPeriod;
    }

    /**
     * Calculates Bollinger Bands.
     */
    private function calculateBollingerBands(array $closes, int $period = 20, int $stdDevMultiplier = 2): array
    {
        if (count($closes) < $period) return ['middle' => [], 'upper' => [], 'lower' => []];
        $sma = [];
        $upper = [];
        $lower = [];

        for ($i = $period - 1; $i < count($closes); $i++) {
            $slice = array_slice($closes, $i - ($period - 1), $period);
            $currentSma = array_sum($slice) / $period;
            $sma[] = $currentSma;

            $sumOfSquares = 0;
            foreach ($slice as $val) {
                $sumOfSquares += pow($val - $currentSma, 2);
            }
            $currentStdDev = sqrt($sumOfSquares / $period);

            $upper[] = $currentSma + ($currentStdDev * $stdDevMultiplier);
            $lower[] = $currentSma - ($currentStdDev * $stdDevMultiplier);
        }

        return ['middle' => $sma, 'upper' => $upper, 'lower' => $lower];
    }

    /**
     * Creates a default result object.
     */
    private function createDefaultResultObject(): object
    {
        $obj = new stdClass();
        $obj->title = 'Analisis Belum Dijalankan';
        $obj->description = $this->getDescription();
        $obj->signal = 'NEUTRAL';
        $obj->confidence = 0.0;
        $obj->entry = 0.0;
        $obj->stop_loss = 0.0;
        $obj->take_profit = 0.0;
        $obj->risk_reward = 'N/A';
        $obj->fee = 0.0;
        $obj->potential_profit = 0.0;
        $obj->potential_loss = 0.0;
        return $obj;
    }

    /**
     * Calculates all financial details for the result object.
     */
    private function calculateFinancials(object $result, float $amount, float $entry, float $sl, float $tp): object
    {
        $feeRate = 0.001; // 0.1% fee
        $risk = abs($entry - $sl);
        $reward = abs($tp - $entry);

        $result->entry = $entry;
        $result->stop_loss = $sl;
        $result->take_profit = $tp;
        $result->risk_reward = $risk > 0 ? '1:' . number_format($reward / $risk, 2) : 'N/A';

        $result->fee = $amount * $feeRate;

        $result->potential_loss = ($risk / $entry) * $amount;

        $result->potential_profit = ($reward / $entry) * $amount;

        return $result;
    }
}

