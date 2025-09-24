<?php
// continue.php - Advanced Crypto Signal & Scanner
// Peningkatan dari coin.php dengan indikator, filter, dan logika pemindaian yang lebih canggih.

date_default_timezone_set('UTC');
set_time_limit(0);

// -------------------- load .env (sama seperti sebelumnya) --------------------
function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $p = explode('=', $line, 2);
        if (count($p) !== 2) continue;
        putenv(trim($p[0]) . '=' . trim($p[1]));
    }
}
loadEnv();

// -------------------- Konfigurasi (dengan tambahan baru) --------------------
$BINANCE_API        = getenv('BINANCE_API') ?: 'https://api.binance.com';
$COINGECKO_API_KEY  = getenv('COINGECKO_API_KEY') ?: '';
$USD_TO_IDR         = floatval(getenv('USD_TO_IDR') ?: 16000);
$MAX_COINS_TO_ANALYZE = intval(getenv('MAX_COINS_TO_ANALYZE') ?: 5); // Berapa banyak koin teratas yang akan dianalisis secara mendalam
$SCANNER_CANDIDATE_LIMIT = intval(getenv('SCANNER_CANDIDATE_LIMIT') ?: 150); // Berapa banyak koin dari Binance yang akan dipindai
$REQUEST_DELAY_MS   = intval(getenv('REQUEST_DELAY_MS') ?: 100);
$SEND_ALWAYS        = intval(getenv('SEND_ALWAYS') ?: 0);
$LAST_HASH_FILE     = getenv('LAST_HASH_FILE') ?: __DIR__ . '/.last_signal_hash_advanced';
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$TELEGRAM_CHAT_ID   = getenv('TELEGRAM_CHAT_ID') ?: '';

$STABLES = ['USDT','USDC','BUSD','DAI','TUSD','USDP','FDUSD'];

// -------------------- Utilitas (sama seperti sebelumnya) --------------------
function sleep_ms($ms) { usleep(max(0,intval($ms)) * 1000); }

function httpGetJson($url, $headers = [], $timeout = 15) {
    $ch = curl_init($url);
    $hdr = array_merge(["User-Agent: crypto-signal/2.0"], $headers);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $hdr, CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_FOLLOWLOCATION => true]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) { error_log("cURL error: $err -> $url"); return null; }
    if ($code >= 400) { error_log("HTTP $code -> $url : " . substr($resp,0,300)); return null; }
    return json_decode($resp, true) ?: null;
}

function postTelegram($botToken, $chatId, $text) {
    // ... (fungsi sama seperti di coin.php)
    if (!$botToken || !$chatId) return false;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = ['chat_id'=>$chatId,'text'=>$text,'parse_mode'=>'Markdown'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$data, CURLOPT_TIMEOUT=>15]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code >= 400) error_log("Telegram send error: $code -> $res");
    return $res;
}

function formatUSDIDR($usd, $usdToIdr, $dec=2) {
    // ... (fungsi sama seperti di coin.php)
    $usdFmt = '$' . number_format($usd, $dec, '.', ',');
    $idr = intval(round($usd * $usdToIdr));
    $idrFmt = 'Rp ' . number_format($idr, 0, ',', '.');
    return "{$usdFmt} ({$idrFmt})";
}


// -------------------- Pengambilan Data (sama seperti sebelumnya) --------------------
function getBinanceTopSymbolsByVolume($limit=100) {
    global $BINANCE_API, $REQUEST_DELAY_MS;
    $res = httpGetJson($BINANCE_API . "/api/v3/ticker/24hr");
    sleep_ms($REQUEST_DELAY_MS);
    if (!$res) return [];
    $usdt = array_filter($res, fn($t)=>substr($t['symbol'],-4)==='USDT');
    usort($usdt, fn($a,$b)=>floatval($b['quoteVolume']) <=> floatval($a['quoteVolume']));
    return array_map(fn($t)=>$t['symbol'], array_slice($usdt,0,$limit));
}

function getBinanceKlines($pair, $interval='1h', $limit=200) {
    global $BINANCE_API, $REQUEST_DELAY_MS;
    $url = $BINANCE_API . "/api/v3/klines?symbol=" . urlencode($pair) . "&interval={$interval}&limit={$limit}";
    $res = httpGetJson($url);
    sleep_ms($REQUEST_DELAY_MS);
    return is_array($res) ? $res : null;
}

// -------------------- Indikator Teknis (dengan tambahan baru) --------------------

function calculateEMA(array $prices, int $period) {
    if (count($prices) < $period) return [];
    $k = 2 / ($period + 1);
    $emas = [];
    // Seed dengan SMA
    $sma = array_sum(array_slice($prices, 0, $period)) / $period;
    $emas[($period - 1)] = $sma;
    for ($i = $period; $i < count($prices); $i++) {
        $emas[$i] = ($prices[$i] - $emas[$i - 1]) * $k + $emas[$i - 1];
    }
    return array_values($emas);
}

function emaLast(array $prices, int $period) {
    $emas = calculateEMA($prices, $period);
    return end($emas) ?: null;
}

function calcRSI(array $prices, int $period=14) { /* ... (fungsi sama) ... */ return 100 - (100/(1+$rs)); }

function calcATR(array $klines, int $period=14) { /* ... (fungsi sama) ... */ return array_sum($slice)/count($slice); }

function supportResistanceSimple(array $closes, int $period=30) {
    $recent = array_slice($closes, -$period);
    return ['s' => min($recent), 'r' => max($recent)];
}

/**
 * Menghitung MACD (Moving Average Convergence Divergence)
 */
function calcMACD(array $prices, int $fast=12, int $slow=26, int $signal=9) {
    if (count($prices) < $slow) return null;
    $emaFast = array_values(calculateEMA($prices, $fast));
    $emaSlow = array_values(calculateEMA($prices, $slow));
    
    // Sesuaikan panjang array
    $emaFast = array_slice($emaFast, count($emaFast) - count($emaSlow));

    $macdLine = [];
    for ($i=0; $i < count($emaSlow); $i++) {
        $macdLine[] = $emaFast[$i] - $emaSlow[$i];
    }

    $signalLine = calculateEMA($macdLine, $signal);
    $macdLine = array_slice($macdLine, count($macdLine) - count($signalLine));

    $histogram = [];
    for ($i=0; $i<count($signalLine); $i++) {
        $histogram[] = $macdLine[$i] - $signalLine[$i];
    }

    return [
        'macd' => end($macdLine),
        'signal' => end($signalLine),
        'histogram' => end($histogram)
    ];
}

/**
 * Menghitung Bollinger Bands
 */
function calcBollingerBands(array $prices, int $period=20, float $dev=2.0) {
    if (count($prices) < $period) return null;
    $slice = array_slice($prices, -$period);
    $sma = array_sum($slice) / $period;
    $std_dev = 0.0;
    foreach($slice as $p) { $std_dev += pow($p - $sma, 2); }
    $std_dev = sqrt($std_dev / $period);
    
    return [
        'upper' => $sma + ($std_dev * $dev),
        'middle' => $sma,
        'lower' => $sma - ($std_dev * $dev),
    ];
}


// -------------------- Analisis TF Tingkat Lanjut --------------------
function advancedAnalysisTF($pair, $interval='1h') {
    $klines = getBinanceKlines($pair, $interval, 300); // Butuh lebih banyak data untuk EMA 200
    if (!$klines || count($klines) < 201) return null;

    $closes = array_map(fn($k)=>floatval($k[4]), $klines);
    $price = end($closes);

    // --- Bobot Penilaian ---
    $weights = ['trend' => 3, 'ema_cross' => 1.5, 'macd' => 2, 'rsi' => 1, 'bb' => 1, 'sr' => 1];

    // 1. Filter Tren Utama (EMA 200)
    $ema200 = emaLast($closes, 200);
    $trendScore = 0; $trendLabel = 'Netral';
    if ($ema200) {
        if ($price > $ema200 * 1.005) { $trendScore = 1; $trendLabel = "Uptrend (di atas EMA200)"; }
        elseif ($price < $ema200 * 0.995) { $trendScore = -1; $trendLabel = "Downtrend (di bawah EMA200)"; }
    }
    
    // Hanya cari sinyal long jika dalam uptrend
    if ($trendScore <= 0) {
        // Bisa return null di sini untuk pemindaian yang lebih ketat, atau tetap analisis untuk info
    }

    // 2. EMA Cross (9 vs 21)
    $emaShort = emaLast($closes, 9);
    $emaLong  = emaLast($closes, 21);
    $emaScore = ($emaShort > $emaLong) ? 1 : -1;

    // 3. MACD
    $macd = calcMACD($closes);
    $macDScore = 0; $macdLabel = 'N/A';
    if ($macd) {
        if ($macd['histogram'] > 0 && $macd['macd'] > $macd['signal']) { $macDScore = 1; $macdLabel = "Bullish Cross"; }
        elseif ($macd['histogram'] < 0 && $macd['macd'] < $macd['signal']) { $macDScore = -1; $macdLabel = "Bearish Cross"; }
        else { $macdLabel = "Netral"; }
    }

    // 4. RSI
    $rsiVal = calcRSI($closes, 14);
    $rsiScore = ($rsiVal >= 50 && $rsiVal <= 70) ? 1 : (($rsiVal < 50) ? -1 : 0);

    // 5. Bollinger Bands
    $bb = calcBollingerBands($closes, 20);
    $bbScore = 0; $bbLabel = 'Di dalam band';
    if ($bb) {
        if ($price < $bb['lower']) { $bbScore = 1; $bbLabel = "Oversold (di bawah BB)"; }
        if ($price > $bb['upper']) { $bbScore = -1; $bbLabel = "Overbought (di atas BB)"; }
        if ($price > $bb['lower'] && $price < $bb['middle']) { $bbLabel = "Bounce dari bawah?"; }
    }

    // 6. Support / Resistance
    $sr = supportResistanceSimple($closes, 30);
    $srScore = 0;
    if ($sr['s'] && abs($price - $sr['s']) <= ($price * 0.015)) { $srScore=1; }
    elseif ($sr['r'] && abs($price - $sr['r']) <= ($price * 0.015)) { $srScore=-1; }

    // --- Skor Total Berbobot ---
    $totalScore = ($trendScore * $weights['trend']) + 
                  ($emaScore * $weights['ema_cross']) + 
                  ($macDScore * $weights['macd']) + 
                  ($rsiScore * $weights['rsi']) + 
                  ($bbScore * $weights['bb']) +
                  ($srScore * $weights['sr']);

    // --- Manajemen Risiko Dinamis ---
    $atrVal = calcATR($klines, 14) ?: ($price * 0.02); // Fallback ATR 2%
    $sl = round($price - 2 * $atrVal, 8);
    // TP dinamis: targetkan resistance terdekat, jika terlalu dekat, gunakan 3x ATR
    $tp = $sr['r'] && ($sr['r'] > $price * 1.01) ? $sr['r'] : round($price + 3 * $atrVal, 8);
    $rr_ratio = ($tp - $price) / ($price - $sl);


    return [
        'price' => $price,
        'score' => round($totalScore, 2),
        'rr_ratio' => round($rr_ratio, 2),
        'sl' => $sl,
        'tp' => $tp,
        'summary' => [
            "Trend" => "{$trendLabel} ({$trendScore * $weights['trend']})",
            "MACD" => "{$macdLabel} ({$macDScore * $weights['macd']})",
            "EMA" => ($emaScore > 0 ? "Bullish" : "Bearish") . " ({$emaScore * $weights['ema_cross']})",
            "BB" => "{$bbLabel} ({$bbScore * $weights['bb']})",
            "RSI" => "{$rsiVal} ({$rsiScore * $weights['rsi']})",
        ]
    ];
}


// -------------------- Formatter Multi-TF --------------------
function formatAnalysisForTelegram($pair, $tfAnalyses) {
    global $USD_TO_IDR;
    
    $price = $tfAnalyses['H1']['price'] ?? $tfAnalyses['H4']['price'] ?? 0;
    if ($price == 0) return "Data tidak cukup untuk {$pair}";

    // Logika Kesimpulan Canggih
    $h1_score = $tfAnalyses['H1']['score'] ?? 0;
    $h4_score = $tfAnalyses['H4']['score'] ?? 0;
    $d1_score = $tfAnalyses['1D']['score'] ?? 0;
    
    $overall = "❌ Hindari";
    if ($h4_score > 3 && $d1_score > 3) { // Konfirmasi tren menengah-panjang
        if ($h1_score > 3 && ($tfAnalyses['H1']['rr_ratio'] ?? 0) >= 1.5) {
            $overall = "✅ POTENSI SWING BAGUS (semua TF mendukung, R:R baik)";
        } else {
            $overall = "⚠️ TUNGGU KONFIRMASI H1 (tren utama OK, entri belum ideal)";
        }
    } elseif ($h1_score > 4 && ($tfAnalyses['H1']['rr_ratio'] ?? 0) >= 1.8) {
        $overall = "⚡️ POTENSI SCALPING CEPAT (H1 kuat, waspada TF besar)";
    }
    
    $out = [];
    $out[] = "📊 *{$pair}* (Analisis Lanjutan)";
    $out[] = "💰 Harga: " . formatUSDIDR($price, $USD_TO_IDR);
    $out[] = "";

    foreach (['H1','H4','1D'] as $lab) {
        $r = $tfAnalyses[$lab] ?? null;
        if (!$r) {
            $out[] = "**{$lab}**: Data tidak cukup"; continue;
        }
        $out[] = "**{$lab}** (Skor: {$r['score']}) | R:R Ratio: {$r['rr_ratio']}";
        foreach($r['summary'] as $key => $val) {
            $out[] = "- {$key}: {$val}";
        }
        $out[] = "  SL: " . formatUSDIDR($r['sl'], $USD_TO_IDR) . " | TP: " . formatUSDIDR($r['tp'], $USD_TO_IDR);
        $out[] = "";
    }

    $out[] = "*Kesimpulan:* {$overall}";
    return implode("\n", $out);
}

// -------------------- MAIN --------------------
echo date("Y-m-d H:i:s") . " UTC | Advanced Scanner Starting\n\n";

// 1. Pemindai Proaktif: Dapatkan daftar koin dari Binance dan saring yang paling menjanjikan
$all_symbols = getBinanceTopSymbolsByVolume($SCANNER_CANDIDATE_LIMIT);
$promising_candidates = [];
echo "Memindai " . count($all_symbols) . " simbol untuk menemukan kandidat...\n";

foreach ($all_symbols as $pair) {
    if (in_array(str_replace('USDT', '', $pair), $STABLES)) continue;

    // Filter cepat: Hanya periksa koin di atas EMA 200 pada timeframe 4H
    $klines4h = getBinanceKlines($pair, '4h', 201);
    if (!$klines4h || count($klines4h) < 201) continue;
    
    $closes4h = array_map(fn($k)=>floatval($k[4]), $klines4h);
    $price4h = end($closes4h);
    $ema200_4h = emaLast($closes4h, 200);

    if ($ema200_4h && $price4h > $ema200_4h) {
        // Kandidat ini dalam tren naik 4jam, layak dianalisis lebih dalam
        $promising_candidates[] = $pair;
        echo "- {$pair} menjanjikan (di atas EMA200 4H). Ditambahkan ke daftar analisis.\n";
    }
}

if (count($promising_candidates) === 0) {
    echo "\nTidak ada koin yang lolos filter pemindai utama. Menggunakan fallback BTC & ETH.\n";
    $promising_candidates = ['BTCUSDT', 'ETHUSDT'];
}

echo "\nAnalisis mendalam untuk " . count($promising_candidates) . " kandidat teratas...\n\n";

// 2. Analisis Mendalam Multi-TF untuk kandidat terpilih
$final_blocks = [];
$candidate_list = array_slice($promising_candidates, 0, $MAX_COINS_TO_ANALYZE);

foreach ($candidate_list as $pair) {
    $tfAnalyses = [];
    foreach (['1h'=>'H1', '4h'=>'H4', '1d'=>'1D'] as $int => $label) {
        $analysis = advancedAnalysisTF($pair, $int);
        if ($analysis) $tfAnalyses[$label] = $analysis;
    }
    
    if (!empty($tfAnalyses)) {
        $final_blocks[] = formatAnalysisForTelegram($pair, $tfAnalyses);
    }
    sleep_ms(200); // Jeda antar koin
}

// 3. Bangun pesan akhir dan kirim
if (empty($final_blocks)) {
    echo "Tidak ada sinyal yang dihasilkan setelah analisis mendalam.\n";
    exit;
}

$header = "📈 *Sinyal Kripto Lanjutan " . date("d M Y H:i") . "*\n\n";
$finalMsg = $header . implode("\n\n---\n\n", $final_blocks);

// Cek duplikasi sebelum mengirim
$hash = md5($finalMsg);
$last = @file_get_contents($LAST_HASH_FILE);
if ($last === $hash && !$SEND_ALWAYS) {
    echo "Tidak ada perubahan sinyal. Pengiriman dibatalkan.\n\n";
    echo $finalMsg . "\n";
    exit;
}
@file_put_contents($LAST_HASH_FILE, $hash);

// Kirim ke Telegram
if ($TELEGRAM_BOT_TOKEN && $TELEGRAM_CHAT_ID) {
    postTelegram($TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID, $finalMsg);
    echo "Pesan terkirim ke Telegram.\n";
} else {
    echo "Telegram tidak dikonfigurasi. Mencetak pesan:\n";
}

echo $finalMsg . "\n";

?>