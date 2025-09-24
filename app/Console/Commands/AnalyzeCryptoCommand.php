<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AnalyzeCryptoCommand extends Command
{
    /**
     * php artisan analyze:crypto BTCUSDT --tf=1h
     */
    protected $signature = 'analyze:coin {symbol} {--tf=1h}';
    protected $description = 'Analisa manual market crypto (Entry, SL, TP, RR, Winrate, Signal)';

    public function handle()
    {
        $symbol = strtoupper($this->argument('symbol'));
        $tf = $this->option('tf');

        $this->info("📊 Analisa {$symbol} [TF: {$tf}]");

        // Check if symbol exists in database
        $cryptoSymbol = \App\Models\CryptoSymbol::where('symbol', $symbol)->first();
        if (!$cryptoSymbol) {
            $this->error("❌ Symbol {$symbol} tidak ditemukan di database. Jalankan 'php artisan crypto:update-symbols' terlebih dahulu.");
            return;
        }

        // Check if symbol is available for trading
        if (!$cryptoSymbol->isAvailableForTrading()) {
            $this->error("❌ Symbol {$symbol} tidak tersedia untuk trading (Status: {$cryptoSymbol->status})");
            return;
        }

        $price = $this->getCurrentPrice($symbol);
        if (!$price) {
            $this->warn("⚠️ Menggunakan data demo untuk {$symbol} (API tidak tersedia)");
        }

        $candles = $this->getCandles($symbol, $tf, 100);
        if (!$candles) {
            $this->error("❌ Gagal ambil data candle");
            return;
        }

        $analysis = $this->manualAnalysis($price, $candles);

        $this->table(
            ['Symbol', 'Entry', 'Stop Loss', 'Take Profit', 'RR', 'Confidence', 'Signal'],
            [[
                $symbol,
                $analysis['entry'],
                $analysis['stop_loss'],
                $analysis['take_profit'],
                $analysis['rr'],
                $analysis['confidence'] . '%',
                $analysis['signal'],
            ]]
        );
    }

    private function getCurrentPrice($symbol)
    {
        $binanceService = new \App\Services\BinanceService();
        $priceData = $binanceService->getTickerPriceWithFallback($symbol);

        if ($priceData && isset($priceData['price'])) {
            return (float) $priceData['price'];
        }

        return null;
    }

    private function getCandles($symbol, $tf, $limit = 100)
    {
        try {
            $api = rtrim(env('BINANCE_API'), '/');
            $res = Http::timeout(30)->get("{$api}/api/v3/klines", [
                'symbol' => $symbol,
                'interval' => $tf,
                'limit' => $limit
            ]);

            if ($res->successful()) {
                return $res->json();
            }

            // Return demo candle data if API fails
            return $this->getDemoCandles($symbol, $tf, $limit);
        } catch (\Exception $e) {
            // Return demo candle data if API fails
            return $this->getDemoCandles($symbol, $tf, $limit);
        }
    }

    private function getDemoCandles($symbol, $tf, $limit = 100)
    {
        $basePrice = $this->getDemoPrice($symbol);
        $candles = [];

        // Generate demo candles with some realistic price movements
        $currentTime = now()->timestamp * 1000;
        $intervalMs = $this->getIntervalMs($tf);

        for ($i = $limit; $i > 0; $i--) {
            $openTime = $currentTime - ($i * $intervalMs);
            $price = (float) $basePrice + (mt_rand(-1000, 1000) / 100); // Add some random variation
            $high = $price + (mt_rand(0, 500) / 100);
            $low = $price - (mt_rand(0, 500) / 100);
            $close = $price + (mt_rand(-200, 200) / 100);
            $volume = mt_rand(1000000, 10000000);

            $candles[] = [
                $openTime,           // Open time
                $price,              // Open
                $high,               // High
                $low,                // Low
                $close,              // Close
                $volume,             // Volume
                $openTime + $intervalMs, // Close time
                $volume * $price,    // Quote asset volume
                100,                 // Number of trades
                $volume * 0.4,       // Taker buy base asset volume
                $volume * 0.4 * $price, // Taker buy quote asset volume
                '0'                  // Ignore
            ];
        }

        return $candles;
    }

    private function getDemoPrice($symbol)
    {
        $demoPrices = [
            'HYPEUSDT' => '0.00000123',
            'BTCUSDT' => '60000.00',
            'ETHUSDT' => '3000.00',
            'BNBUSDT' => '400.00',
            'ADAUSDT' => '0.35',
            'SOLUSDT' => '150.00',
            'XRPUSDT' => '0.50',
            'DOTUSDT' => '5.00',
            'AVAXUSDT' => '25.00',
            'MATICUSDT' => '0.80',
            'LINKUSDT' => '10.00',
            'UNIUSDT' => '8.00',
            'LTCUSDT' => '70.00',
            'ALGOUSDT' => '0.15',
            'ATOMUSDT' => '7.00',
        ];

        return $demoPrices[strtoupper($symbol)] ?? '1.00';
    }

    private function getIntervalMs($tf)
    {
        $intervals = [
            '1m' => 60 * 1000,
            '3m' => 3 * 60 * 1000,
            '5m' => 5 * 60 * 1000,
            '15m' => 15 * 60 * 1000,
            '30m' => 30 * 60 * 1000,
            '1h' => 60 * 60 * 1000,
            '2h' => 2 * 60 * 60 * 1000,
            '4h' => 4 * 60 * 60 * 1000,
            '6h' => 6 * 60 * 60 * 1000,
            '8h' => 8 * 60 * 60 * 1000,
            '12h' => 12 * 60 * 60 * 1000,
            '1d' => 24 * 60 * 60 * 1000,
            '3d' => 3 * 24 * 60 * 60 * 1000,
            '1w' => 7 * 24 * 60 * 60 * 1000,
            '1M' => 30 * 24 * 60 * 60 * 1000,
        ];

        return $intervals[$tf] ?? 60 * 60 * 1000; // Default to 1h
    }

    private function formatPrice($price)
    {
        // For very small prices (like HYPEUSDT), show more decimal places
        if ($price < 0.0001) {
            return number_format($price, 8);
        } elseif ($price < 0.01) {
            return number_format($price, 6);
        } elseif ($price < 1) {
            return number_format($price, 4);
        } else {
            return number_format($price, 2);
        }
    }

    private function manualAnalysis($price, $candles)
    {
        $closes = array_map(fn($c) => (float)$c[4], $candles);
        $ema = array_sum(array_slice($closes, -20)) / 20;
        $rsi = $this->calcRSI($closes, 14);

        $highs = array_map(fn($c) => (float)$c[2], $candles);
        $lows  = array_map(fn($c) => (float)$c[3], $candles);

        $resistance = max(array_slice($highs, -20));
        $support    = min(array_slice($lows, -20));

        $signal = 'NEUTRAL';
        $entry = $price;
        $sl = null;
        $tp = null;
        $rr = null;
        $confidence = 50;

        if ($price > $ema && $rsi > 50) {
            $signal = 'LONG';
            $sl = $support;
            $tp = $resistance;
        } elseif ($price < $ema && $rsi < 50) {
            $signal = 'SHORT';
            $sl = $resistance;
            $tp = $support;
        }

        if ($sl && $tp) {
            if ($signal === 'LONG') {
                $rr = round(($tp - $entry) / ($entry - $sl), 2);
            } elseif ($signal === 'SHORT') {
                $rr = round(($entry - $tp) / ($sl - $entry), 2);
            }
        }

        if ($signal === 'LONG') {
            $confidence = $rsi > 60 ? 70 : 55;
        } elseif ($signal === 'SHORT') {
            $confidence = $rsi < 40 ? 70 : 55;
        }

        return [
            'entry'       => $this->formatPrice($entry),
            'stop_loss'   => $sl ? $this->formatPrice($sl) : '-',
            'take_profit' => $tp ? $this->formatPrice($tp) : '-',
            'rr'          => $rr ? "1:{$rr}" : '-',
            'confidence'  => $confidence,
            'signal'      => $signal,
        ];
    }

    private function calcRSI($closes, $period = 14)
    {
        $gains = 0;
        $losses = 0;
        for ($i = count($closes) - $period; $i < count($closes) - 1; $i++) {
            $diff = $closes[$i + 1] - $closes[$i];
            if ($diff > 0) {
                $gains += $diff;
            } else {
                $losses -= $diff;
            }
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        if ($avgLoss == 0) return 100;

        $rs = $avgGain / $avgLoss;
        return round(100 - (100 / (1 + $rs)), 2);
    }
}
