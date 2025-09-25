<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Versi Lengkap Final dengan Fallback & URL Spesifik
class AnalyzeMaster extends Command
{
    protected $signature = 'crypto:analyze1 {symbol=BTCUSDT} {position=100}';
    protected $description = 'Analyze crypto coin with TA and send to Telegram';

    // Properti untuk menyimpan konfigurasi dari .env
    protected $binanceApi;
    protected $telegramToken;
    protected $telegramChatId;
    protected $usdToIdr;
    protected $ema50Period;
    protected $ema200Period;
    protected $rsiPeriod;
    protected $macdFast;
    protected $macdSlow;
    protected $macdSignal;
    protected $bbPeriod;
    protected $bbStdDev;
    protected $rsiEntryThreshold;
    protected $rsiExitThreshold;
    protected $takeProfitPercent;
    protected $timeframes;
    protected $lastHashFile;
    protected $sendAlways;

    public function __construct()
    {
        parent::__construct();
        // Membaca konfigurasi dari file .env
        $this->binanceApi     = env('BINANCE_API', 'https://data-api.binance.vision'); // Sesuai permintaan
        $this->telegramToken  = env('TELEGRAM_BOT_TOKEN');
        $this->telegramChatId = env('TELEGRAM_CHAT_ID');
        $this->usdToIdr       = env('USD_TO_IDR', 16000);
        $this->ema50Period    = env('EMA50_PERIOD', 50);
        $this->ema200Period   = env('EMA200_PERIOD', 200);
        $this->rsiPeriod      = env('RSI_PERIOD', 14);
        $this->macdFast       = env('MACD_FAST', 12);
        $this->macdSlow       = env('MACD_SLOW', 26);
        $this->macdSignal     = env('MACD_SIGNAL', 9);
        $this->bbPeriod       = env('BB_PERIOD', 20);
        $this->bbStdDev       = env('BB_STDDEV', 2);
        $this->rsiEntryThreshold = env('RSI_ENTRY_THRESHOLD', 30);
        $this->rsiExitThreshold  = env('RSI_EXIT_THRESHOLD', 70);
        $this->takeProfitPercent  = env('TAKE_PROFIT_PERCENT', 5);
        $this->timeframes = ['1h' => 'H1', '4h' => 'H4', '1d' => '1D'];
        $this->lastHashFile = env('LAST_HASH_FILE', storage_path('.last_signal_hash'));
        $this->sendAlways = env('SEND_ALWAYS', false);
    }

    public function handle()
    {
        ini_set('memory_limit', '256M'); // Increase memory limit for data processing

        $symbol = strtoupper($this->argument('symbol'));
        if (!str_ends_with($symbol, 'USDT')) $symbol .= 'USDT';
        $positionUSD = floatval($this->argument('position')); // Position size in USD

        $this->info(now()->toDateTimeString() . " | Menganalisis {$symbol} multi-timeframe menggunakan {$this->binanceApi}... Posisi: \${$positionUSD}");

        $tickSize = $this->getTickSize($symbol);
        $trendChange = $this->getCoingeckoTrend($symbol);

        $tfBlocks = [];
        $scores = [];
        $priceMain = null;
        foreach ($this->timeframes as $int => $label) {
            $klines = $this->getBinanceKlines($symbol, $int, 300);
            if (!$klines || count($klines) < 201) {
                $this->warn("Data tidak cukup untuk {$label}");
                $tfBlocks[$label] = null;
                $scores[$label] = null;
                continue;
            }
            $analysis = $this->analyzeTimeframe($klines, $tickSize);
            if (!$analysis) {
                $this->warn("Gagal analisis untuk {$label}");
                $tfBlocks[$label] = null;
                $scores[$label] = null;
                continue;
            }
            if ($priceMain === null) {
                $closes = array_map(fn($k) => floatval($k[4]), $klines);
                $priceMain = end($closes);
            }
            $tfBlocks[$label] = $analysis;
            $scores[$label] = $analysis['score'];
        }

        if (empty(array_filter($tfBlocks))) {
            $this->error("❌ Tidak ada analisis yang berhasil untuk {$symbol}.");
            return self::FAILURE;
        }

        // Overall recommendation
        $validScores = array_filter($scores, fn($s) => $s !== null);
        $pos = count(array_filter($validScores, fn($s) => $s > 0));
        $neg = count(array_filter($validScores, fn($s) => $s < 0));
        if ($pos === count($validScores) && $pos > 0) $overall = "✅ Swing OK (TF semua mendukung)";
        elseif ($scores['H1'] > 0 && ($scores['H4'] <= 0 || $scores['1D'] <= 0)) $overall = "⚠️ H1 oke — hanya scalping (jangan hold lama)";
        elseif ($pos > $neg) $overall = "⚠️ Mixed — tunggu konfirmasi";
        else $overall = "❌ Hindari (TF mayoritas negatif)";

        // Build message
        $message = "📊 *{$symbol}* (Multi-TF)\n\n";
        if ($priceMain) $message .= "💰 Harga sekarang: $" . number_format($priceMain, 2, '.', '') . "\n\n";
        foreach (['H1', 'H4', '1D'] as $lab) {
            $r = $tfBlocks[$lab] ?? null;
            if (!$r) {
                $message .= "**{$lab}**: Data tidak cukup\n\n";
                continue;
            }
            $message .= "**{$lab}** (score: {$r['score']})\n";
            $message .= "- Tren: {$r['summary']['Trend']}\n";
            $message .= "- RSI: {$r['summary']['RSI']}\n";
            $message .= "- EMA{$this->ema50Period}: {$r['summary']['EMA50']}\n";
            $message .= "- EMA{$this->ema200Period}: {$r['summary']['EMA200']}\n";
            $message .= "- MACD: {$r['summary']['MACD']} (Hist: {$r['summary']['Histogram']})\n";
            $message .= "- BB: Upper {$r['summary']['Upper BB']}, Lower {$r['summary']['Lower BB']}\n";
            $message .= "- ATR: {$r['summary']['ATR']}\n";
            $message .= "- Fib: {$r['summary']['Fib']}\n";
            $message .= "- Volume: {$r['summary']['Volume']}\n";
            $message .= "- Support: $" . number_format($r['summary']['Support'], 6) . "\n";
            $message .= "- Resistance: $" . number_format($r['summary']['Resistance'], 6) . "\n";
            if ($r['signal'] !== 'Hold') {
                $message .= "- Sinyal: {$r['signal']}\n";
                if ($r['stopLoss']) $message .= "  🛑 SL: $" . number_format($r['stopLoss'], 6) . "\n";
                if ($r['takeProfit']) $message .= "  ✅ TP: $" . number_format($r['takeProfit'], 6) . "\n";
            }
            $message .= "- Long Entry: $" . number_format($r['longEntry'], 6) . " (pullback to support)\n";
            $message .= "- Short Entry: $" . number_format($r['shortEntry'], 6) . " (break resistance)\n";
            $message .= "\n";
        }

        if ($trendChange !== null) {
            $message .= "🔥 Coingecko trend (24h): {$trendChange}%\n";
            if ($trendChange > 5) $message .= "→ Trending naik (+1 confidence)\n";
            elseif ($trendChange < -3) $message .= "→ Trending turun (-1 caution)\n";
        }

        $message .= "*Kesimpulan multi-timeframe:* {$overall}\n\n";

        // Trading Plan
        $h1 = $tfBlocks['H1'] ?? null;
        if ($h1) {
            $message .= "**📉 SHORT Trading Plan (H1):**\n";
            $message .= "💰 $" . number_format($h1['shortEntry'], 2, '.', '') . " **(Entry - break resistance)**\n";
            $message .= "🛑 $" . number_format($h1['shortSL'], 2, '.', '') . " **(Stop Loss - di atas resistance)**\n";

            // Calculate P/L for position size
            $riskUSDShort = $h1['shortSL'] - $h1['shortEntry'];
            $slPLUSDShort = -$riskUSDShort * ($positionUSD / $h1['shortEntry']);
            $slPLIDRShort = $slPLUSDShort * $this->usdToIdr;
            $message .= "   💸 SL P/L: $" . number_format($slPLUSDShort, 2, '.', '') . " / Rp " . number_format($slPLIDRShort, 0, ',', '.') . "\n";

            $message .= "✅  $" . number_format($h1['shortTP'], 2, '.', '') . " **(TP - RR {$h1['shortRR']}:1)**\n";
            $tpPLUSDShort = ($h1['shortEntry'] - $h1['shortTP']) * ($positionUSD / $h1['shortEntry']) - ($positionUSD * 0.00122);
            $tpPLIDRShort = $tpPLUSDShort * $this->usdToIdr;
            $message .= "   💸 TP P/L: $" . number_format($tpPLUSDShort, 2, '.', '') . " / Rp " . number_format($tpPLIDRShort, 0, ',', '.') . "\n";

            $message .= "📊 **Success Rate:** {$h1['probability']}%\n";

            // Net RR for Short
            if ($riskUSDShort > 0) {
                $feeImpact = 0.00122; // 0.122% total fees
                $netRRShort = round((($h1['shortEntry'] - $h1['shortTP']) / $riskUSDShort) * (1 - $feeImpact), 1);
                $message .= "💰 **Net RR (setelah biaya):** TP: {$netRRShort}:1\n";
            }
            $message .= "\n";

            $message .= "**📉 SHORT Trading Plan (H1):**\n";
            $message .= "💰 $" . number_format($h1['shortEntry'], 2, '.', '') . " **(Entry - break resistance)**\n";
            $message .= "🛑 $" . number_format($h1['shortSL'], 2, '.', '') . " **(Stop Loss - di atas resistance)**\n";

            // Calculate P/L for position size
            $riskUSDShort = $h1['shortSL'] - $h1['shortEntry'];
            $slPLUSDShort = -$riskUSDShort * ($positionUSD / $h1['shortEntry']);
            $slPLIDRShort = $slPLUSDShort * $this->usdToIdr;
            $message .= "   💸 SL P/L: $" . number_format($slPLUSDShort, 2, '.', '') . " / Rp " . number_format($slPLIDRShort, 0, ',', '.') . "\n";

            $message .= "✅  $" . number_format($h1['shortTP'], 2, '.', '') . " **(TP - RR {$h1['shortRR']}:1)**\n";
            $tpPLUSDShort = ($h1['shortEntry'] - $h1['shortTP']) * ($positionUSD / $h1['shortEntry']) - ($positionUSD * 0.00122);
            $tpPLIDRShort = $tpPLUSDShort * $this->usdToIdr;
            $message .= "   💸 TP P/L: $" . number_format($tpPLUSDShort, 2, '.', '') . " / Rp " . number_format($tpPLIDRShort, 0, ',', '.') . "\n";

            $message .= "� **Success Rate:** {$h1['probability']}%\n";

            // Net RR for Short
            if ($riskUSDShort > 0) {
                $feeImpact = 0.00122; // 0.122% total fees
                $netRRShort = round((($h1['shortEntry'] - $h1['shortTP']) / $riskUSDShort) * (1 - $feeImpact), 1);
                $message .= "💰 **Net RR (setelah biaya):** TP: {$netRRShort}:1\n";
            }

            // Fee and Tax Calculation (Indonesia - Maker 0.10%, CFX 0.0222%)
            $samplePosition = 1000; // $1000 position
            $makerFee = $samplePosition * 0.0010; // 0.10%
            $cfxFee = $samplePosition * 0.000222; // 0.0222%
            $totalCost = $makerFee + $cfxFee;
            $message .= "💸 **Biaya (posisi $1000, Maker Order):** Fee 0.10% = $" . number_format($makerFee, 2, '.', '') . ", CFX 0.0222% = $" . number_format($cfxFee, 2, '.', '') . ", Total = $" . number_format($totalCost, 2, '.', '') . "\n";
            $message .= "\n";

            if (strpos($overall, 'Hindari') !== false) {
                $message .= "**⚠️ Note:** Weak signals. Wait for break above resistance (short) or below support (long).\n";
            }
        }

        // Use H1 for additional strategy suggestions
        if ($h1) {
            $message .= "**Catatan Strategi:**\n";
            $message .= "- Jika harga tembus support $" . number_format($h1['summary']['Support'], 6) . ", cut loss untuk long.\n";
            $message .= "- Jika harga tembus resistance $" . number_format($h1['summary']['Resistance'], 6) . ", cut loss untuk short.\n";
            $message .= "- Jika harga > EMA200 dan RSI < 30, pertimbangkan long. Jika RSI > 70, pertimbangkan short.\n";
        }

        // Deduplication
        $hash = md5($message);
        $last = @file_get_contents($this->lastHashFile);
        if ($last === $hash && !$this->sendAlways) {
            $this->info("No change in signal. Not sending Telegram.");
            $this->line($message);
            return self::SUCCESS;
        }
        @file_put_contents($this->lastHashFile, $hash);

        $this->sendTelegram($message);
        $this->info("✅ Analisis terkirim.");
        $this->line($message);

        return self::SUCCESS;
    }

    protected function analyzeTimeframe(array $klines, float $tickSize = 0.01): ?array
    {
        $closes  = array_map(fn($k) => floatval($k[4]), $klines);
        $highs   = array_map(fn($k) => floatval($k[2]), $klines);
        $lows    = array_map(fn($k) => floatval($k[3]), $klines);
        $volumes = array_map(fn($k) => floatval($k[5]), $klines);
        $price   = end($closes);

        if (extension_loaded('trader')) {
            $this->info("-> Menggunakan ekstensi 'trader' untuk kalkulasi.");
            $ema50 = last(trader_ema($closes, $this->ema50Period));
            $ema200 = last(trader_ema($closes, $this->ema200Period));
            $rsi = last(trader_rsi($closes, $this->rsiPeriod));
            $macd = trader_macd($closes, $this->macdFast, $this->macdSlow, $this->macdSignal);
            $macdLine = last($macd[0]);
            $signalLine = last($macd[1]);
            $histogram = last($macd[2]);
            $bbands = trader_bbands($closes, $this->bbPeriod, $this->bbStdDev, $this->bbStdDev, 0);
            $upperBB = last($bbands[0]);
            $middleBB = last($bbands[1]);
            $lowerBB = last($bbands[2]);
        } else {
            $this->warn("-> Ekstensi 'trader' tidak ada. Menggunakan kalkulasi manual (fallback).");
            $ema50 = $this->emaLast($closes, $this->ema50Period);
            $ema200 = $this->emaLast($closes, $this->ema200Period);
            $rsi = $this->calcRSI($closes, $this->rsiPeriod);
            $macdData = $this->calcMACD($closes, $this->macdFast, $this->macdSlow, $this->macdSignal);
            $macdLine = end($macdData['macd']);
            $signalLine = end($macdData['signal']);
            $histogram = end($macdData['histogram']);
            $bbands = $this->calcBollingerBands($closes, $this->bbPeriod, $this->bbStdDev);
            $upperBB = end($bbands['upper']);
            $middleBB = end($bbands['middle']);
            $lowerBB = end($bbands['lower']);
        }

        if ($ema50 === null || $ema200 === null || $rsi === null || $macdLine === null) return null;

        // ATR for SL/TP
        $atr = $this->calcATR($klines, 14);
        $atrUse = $atr ?: max(0.0001, $price * 0.01);

        // Support/Resistance
        $recentSlice = array_slice($closes, -20);
        $support = min($recentSlice);
        $resistance = max($recentSlice);

        // Fibonacci
        $swingHigh = $resistance;
        $swingLow = $support;
        $fibLevels = $this->fibonacciLevelsFromHighLow($swingHigh, $swingLow);
        $fibProx = $this->checkFibProximity($price, $fibLevels, 1.0);

        // Volume analysis
        $medianVol = $this->median($volumes);
        $volNow = end($volumes);
        $volScore = 0;
        if ($medianVol > 0 && $volNow >= $medianVol * 1.2) $volScore = 1;
        elseif ($medianVol > 0 && $volNow <= $medianVol * 0.8) $volScore = -1;

        // Improved scoring
        $emaScore = ($ema50 > $ema200) ? 1 : -1;
        $rsiScore = ($rsi < 30) ? 1 : (($rsi > 70) ? -1 : 0);
        $macdScore = ($histogram > 0) ? 1 : -1;
        $fibScore = ($fibProx['near'] && $fibProx['type'] === 'support') ? 1 : (($fibProx['near'] && $fibProx['type'] === 'resistance') ? -1 : 0);

        $totalScore = $emaScore + $rsiScore + $macdScore + $volScore + $fibScore;

        // Signal logic
        $entrySignal = false;
        $exitSignal = false;
        $stopLoss = null;
        $takeProfit = null;

        // Entry conditions: RSI < threshold, price > EMA200, MACD histogram > 0, price > lower BB
        if ($rsi < $this->rsiEntryThreshold && $price > $ema200 && $histogram > 0 && $price > $lowerBB) {
            $entrySignal = true;
            $stopLoss = $price - 1.5 * $atrUse; // Stop loss using ATR
            $takeProfit = $price + 2.5 * $atrUse; // Take profit using ATR
        }

        // Exit conditions: RSI > threshold or price < EMA200
        if ($rsi > $this->rsiExitThreshold || $price < $ema200) {
            $exitSignal = true;
        }

        // Suggested entry prices and realistic SL/TP with multiple targets
        $longEntry = $this->roundToTickSize($support, $tickSize ?? 0.01);
        $shortEntry = $this->roundToTickSize($resistance, $tickSize ?? 0.01);
        $longSL = $this->roundToTickSize($support - $atrUse * 1.5, $tickSize ?? 0.01);
        $shortSL = $this->roundToTickSize($resistance + $atrUse * 1.5, $tickSize ?? 0.01);

        // Single Take Profit for Long with RR 2:1
        $longTP = $this->roundToTickSize($longEntry + ($longEntry - $longSL) * 2, $tickSize ?? 0.01); // RR 2:1

        // Single Take Profit for Short with RR 2:1
        $shortTP = $this->roundToTickSize($shortEntry - ($shortSL - $shortEntry) * 2, $tickSize ?? 0.01); // RR 2:1

        // Calculate RR ratios
        $longRR = $longEntry > $longSL ? round(($longTP - $longEntry) / ($longEntry - $longSL), 1) : 0;
        $shortRR = $shortEntry < $shortSL ? round(($shortEntry - $shortTP) / ($shortSL - $shortEntry), 1) : 0;

        // Success probability based on score
        $probability = 50; // Base
        if ($totalScore >= 4) $probability = 75;
        elseif ($totalScore >= 2) $probability = 65;
        elseif ($totalScore <= -2) $probability = 35;

        $signal = 'Hold';
        if ($entrySignal) $signal = 'Entry';
        elseif ($exitSignal) $signal = 'Exit';

        return [
            'score'   => $totalScore,
            'summary' => [
                'Trend' => $price > $ema200 ? 'Uptrend' : 'Downtrend',
                'RSI' => round($rsi, 1),
                'EMA50' => round($ema50, 4),
                'EMA200' => round($ema200, 4),
                'MACD' => round($macdLine, 4),
                'Signal Line' => round($signalLine, 4),
                'Histogram' => round($histogram, 4),
                'Upper BB' => round($upperBB, 4),
                'Lower BB' => round($lowerBB, 4),
                'ATR' => round($atr, 4),
                'Fib' => $fibProx['near'] ? "{$fibProx['type']} {$fibProx['ratio']}" : 'Neutral',
                'Volume' => $volScore > 0 ? 'High' : ($volScore < 0 ? 'Low' : 'Normal'),
                'Support' => round($support, 4),
                'Resistance' => round($resistance, 4),
            ],
            'signal' => $signal,
            'stopLoss' => $stopLoss ? round($stopLoss, 4) : null,
            'takeProfit' => $takeProfit ? round($takeProfit, 4) : null,
            'longEntry' => round($longEntry, 4),
            'shortEntry' => round($shortEntry, 4),
            'longSL' => round($longSL, 4),
            'longTP' => round($longTP, 4),
            'longRR' => $longRR,
            'shortSL' => round($shortSL, 4),
            'shortTP' => round($shortTP, 4),
            'shortRR' => $shortRR,
            'probability' => $probability,
        ];
    }

    protected function median($arr) {
        if (!is_array($arr) || count($arr) == 0) return 0;
        sort($arr);
        $c = count($arr);
        $m = intval($c / 2);
        return ($c % 2) ? $arr[$m] : ($arr[$m-1] + $arr[$m]) / 2;
    }

    // --- UTILITAS ---
    protected function getTickSize(string $symbol): float {
        $exinfo = Http::timeout(10)->get($this->binanceApi . "/api/v3/exchangeInfo");
        if ($exinfo->successful()) {
            $data = $exinfo->json();
            foreach ($data['symbols'] as $s) {
                if ($s['symbol'] === $symbol) {
                    foreach ($s['filters'] as $f) {
                        if ($f['filterType'] === 'PRICE_FILTER') {
                            return floatval($f['tickSize']);
                        }
                    }
                }
            }
        }
        return 0.01; // default
    }

    protected function roundToTickSize(float $price, float $tickSize): float {
        return round($price / $tickSize) * $tickSize;
    }

    protected function getBinanceKlines(string $symbol, string $interval, int $limit): ?array
    {
        try {
            $url = "{$this->binanceApi}/api/v3/klines?symbol={$symbol}&interval={$interval}&limit={$limit}";

            // Menggunakan withoutVerifying() untuk mengatasi masalah SSL di Windows Anda
            $response = Http::withoutVerifying()->timeout(15)->get($url);

            if ($response->successful()) {
                return $response->json();
            }
            $this->error("Gagal mengambil data dari {$this->binanceApi}. Status: " . $response->status());
            Log::error("Binance API Error", ['url' => $this->binanceApi, 'status' => $response->status()]);
            return null;
        } catch (\Exception $e) {
            $this->error("Koneksi ke {$this->binanceApi} gagal: " . $e->getMessage());
            return null;
        }
    }

    protected function sendTelegram($text) { /* ... fungsi sendTelegram ... */ }

    protected function getCoingeckoTrend($symbol) {
        $base = str_replace('USDT', '', $symbol);
        // Simple fetch, assume API key if needed
        $url = "https://api.coingecko.com/api/v3/simple/price?ids={$base}&vs_currencies=usd&include_24hr_change=true";
        $res = Http::timeout(10)->get($url);
        if ($res->successful()) {
            $data = $res->json();
            if (isset($data[$base]['usd_24h_change'])) {
                return round($data[$base]['usd_24h_change'], 2);
            }
        }
        return null;
    }

    // --- FUNGSI KALKULASI MANUAL (FALLBACK) ---
    protected function emaLast($prices, $period) { $emas = $this->ema($prices, $period); return end($emas) ?: null; }

    protected function ema($prices, $period) {
        if (count($prices) < $period) return [];
        $multiplier = 2 / ($period + 1);
        $ema = [];
        $ema[0] = $prices[0];
        for ($i = 1; $i < count($prices); $i++) {
            $ema[$i] = ($prices[$i] - $ema[$i-1]) * $multiplier + $ema[$i-1];
        }
        return $ema;
    }

    protected function calcRSI($prices, $period = 14) {
        if (count($prices) < $period + 1) return null;
        $gains = [];
        $losses = [];
        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i-1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }
        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;
        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;
        }
        if ($avgLoss == 0) return 100;
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    protected function calcMACD($prices, $fast = 12, $slow = 26, $signal = 9) {
        $emaFast = $this->ema($prices, $fast);
        $emaSlow = $this->ema($prices, $slow);
        $macd = [];
        $minLen = min(count($emaFast), count($emaSlow));
        for ($i = 0; $i < $minLen; $i++) {
            $macd[] = $emaFast[$i] - $emaSlow[$i];
        }
        $signalLine = $this->ema($macd, $signal);
        $histogram = [];
        $minLen2 = min(count($macd), count($signalLine));
        for ($i = 0; $i < $minLen2; $i++) {
            $histogram[] = $macd[$i] - $signalLine[$i];
        }
        return ['macd' => $macd, 'signal' => $signalLine, 'histogram' => $histogram];
    }

    protected function calcBollingerBands($prices, $period = 20, $stdDev = 2) {
        if (count($prices) < $period) return ['upper' => [], 'middle' => [], 'lower' => []];
        $sma = [];
        for ($i = $period - 1; $i < count($prices); $i++) {
            $slice = array_slice($prices, $i - $period + 1, $period);
            $sma[] = array_sum($slice) / $period;
        }
        $upper = [];
        $lower = [];
        for ($i = 0; $i < count($sma); $i++) {
            $slice = array_slice($prices, $i, $period);
            $variance = 0;
            foreach ($slice as $price) {
                $variance += pow($price - $sma[$i], 2);
            }
            $variance /= $period;
            $std = sqrt($variance);
            $upper[] = $sma[$i] + $stdDev * $std;
            $lower[] = $sma[$i] - $stdDev * $std;
        }
        return ['upper' => $upper, 'middle' => $sma, 'lower' => $lower];
    }

    protected function calcATR($klines, $period = 14) {
        if (count($klines) < $period + 1) return null;
        $trs = [];
        for ($i = 1; $i < count($klines); $i++) {
            $h = floatval($klines[$i][2]);
            $l = floatval($klines[$i][3]);
            $pc = floatval($klines[$i-1][4]);
            $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
        }
        $slice = array_slice($trs, -$period);
        return array_sum($slice) / count($slice);
    }

    protected function fibonacciLevelsFromHighLow($high, $low) {
        $range = $high - $low;
        return [
            "0.236" => $high - ($range * 0.236),
            "0.382" => $high - ($range * 0.382),
            "0.500" => $high - ($range * 0.5),
            "0.618" => $high - ($range * 0.618),
            "0.786" => $high - ($range * 0.786),
        ];
    }

    protected function checkFibProximity($price, $levels, $thresholdPct = 1.0) {
        foreach ($levels as $ratioStr => $level) {
            $dist_pct = abs($price - $level) / max($price, 1) * 100.0;
            if ($dist_pct <= $thresholdPct) {
                $type = (in_array($ratioStr, ['0.382', '0.500', '0.618'])) ? 'support' : 'resistance';
                return ['near' => true, 'ratio' => $ratioStr, 'type' => $type, 'dist_pct' => $dist_pct, 'level' => $level];
            }
        }
        return ['near' => false, 'ratio' => null, 'type' => 'neutral', 'dist_pct' => null, 'level' => null];
    }
}