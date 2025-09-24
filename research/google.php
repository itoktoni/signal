<?php
// crypto_scanner_v2.php
// Dijalankan dengan PHP 8.1+. Membutuhkan ekstensi cURL.
// Revisi: Menambahkan filter rezim pasar, Pivot Points, dan skor konfluens untuk sinyal yang lebih kuat.

const SCRIPT_VERSION = '2.0.0';

date_default_timezone_set('UTC');
set_time_limit(0); // Biarkan skrip berjalan selama diperlukan

// -------------------- [KONFIGURASI & .ENV LOADER] --------------------

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}
loadEnv(__DIR__ . '/.env');

// Konfigurasi dari environment variables dengan fallback
$config = [
    'binance_api' => getenv('BINANCE_API') ?: 'https://api.binance.com',
    'coingecko_api_key' => getenv('COINGECKO_API_KEY') ?: '',
    'usd_to_idr' => (float)(getenv('USD_TO_IDR') ?: 16000),
    'max_coins' => (int)(getenv('MAX_COINS') ?: 3),
    'fib_threshold_pct' => (float)(getenv('FIB_THRESHOLD_PCT') ?: 1.0),
    'request_delay_ms' => (int)(getenv('REQUEST_DELAY_MS') ?: 250),
    'send_always' => (bool)(getenv('SEND_ALWAYS') ?: false),
    'last_hash_file' => getenv('LAST_HASH_FILE') ?: __DIR__ . '/.last_signal_hash',
    'fallback_symbols' => array_map('trim', explode(',', getenv('FALLBACK_SYMBOLS') ?: 'BTC,ETH,BNB,SOL,XRP')),
    'telegram_bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'telegram_chat_id' => getenv('TELEGRAM_CHAT_ID') ?: '',
];

$STABLES = ['USDT', 'USDC', 'BUSD', 'DAI', 'TUSD', 'USDP', 'FDUSD'];

// -------------------- [UTILITIES] --------------------

function sleep_ms(int $ms): void { usleep(max(0, $ms) * 1000); }

function httpGetJson(string $url, array $headers = [], int $timeout = 15): ?array {
    $ch = curl_init($url);
    $defaultHeaders = ["User-Agent: CryptoScanner/" . SCRIPT_VERSION, "Accept: application/json"];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("cURL error: " . curl_error($ch) . " -> $url");
        curl_close($ch);
        return null;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400) {
        error_log("HTTP $code -> $url : " . substr((string)$resp, 0, 300));
        return null;
    }
    return json_decode((string)$resp, true);
}

function postTelegram(string $botToken, string $chatId, string $text): bool {
    if (!$botToken || !$chatId) return false;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data, CURLOPT_TIMEOUT => 20]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code >= 400) {
        error_log("Telegram send error: $code -> " . $res);
        return false;
    }
    return true;
}

function formatUSDIDR(float $usd, float $usdToIdr, int $dec = 2): string {
    $usdFmt = '$' . number_format($usd, $dec, '.', ',');
    $idr = (int)round($usd * $usdToIdr);
    $idrFmt = 'Rp ' . number_format($idr, 0, ',', '.');
    return "{$usdFmt} ({$idrFmt})";
}

function median(array $arr): float {
    if (empty($arr)) return 0.0;
    sort($arr);
    $count = count($arr);
    $middle = (int)($count / 2);
    if ($count % 2) {
        return (float)$arr[$middle];
    } else {
        return (float)($arr[$middle - 1] + $arr[$middle]) / 2.0;
    }
}

// -------------------- [DATA FETCHERS] --------------------

function getTrendingFromCoingecko(int $limit = 15): array {
    global $config;
    $base = $config['coingecko_api_key'] ? "https://pro-api.coingecko.com" : "https://api.coingecko.com";
    $url = $base . "/api/v3/search/trending";
    $headers = $config['coingecko_api_key'] ? ["x-cg-pro-api-key: {$config['coingecko_api_key']}"] : [];
    $res = httpGetJson($url, $headers);
    sleep_ms($config['request_delay_ms']);
    $out = [];
    if (!$res || !isset($res['coins'])) return $out;

    foreach ($res['coins'] as $c) {
        if (!isset($c['item'])) continue;
        $it = $c['item'];
        $sym = strtoupper($it['symbol'] ?? '');
        $out[$sym] = [
            'symbol' => $sym,
            'change24h' => $it['data']['price_change_percentage_24h']['usd'] ?? null,
            'name' => $it['name'] ?? null
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}

function getBinanceExchangeInfo(): ?array {
    global $config;
    $res = httpGetJson($config['binance_api'] . "/api/v3/exchangeInfo");
    sleep_ms($config['request_delay_ms']);
    return $res;
}

function getBinanceTopSymbolsByVolume(int $limit = 50): array {
    global $config;
    $res = httpGetJson($config['binance_api'] . "/api/v3/ticker/24hr");
    sleep_ms($config['request_delay_ms']);
    if (!$res) return [];
    $usdt = array_filter($res, fn($t) => str_ends_with($t['symbol'], 'USDT'));
    usort($usdt, fn($a, $b) => (float)$b['quoteVolume'] <=> (float)$a['quoteVolume']);
    return array_map(fn($t) => $t['symbol'], array_slice($usdt, 0, $limit));
}

function getBinanceKlines(string $pair, string $interval = '1h', int $limit = 200): ?array {
    global $config;
    if (!str_ends_with($pair, 'USDT')) $pair .= 'USDT';
    $url = "{$config['binance_api']}/api/v3/klines?symbol=" . urlencode($pair) . "&interval={$interval}&limit={$limit}";
    $res = httpGetJson($url);
    sleep_ms($config['request_delay_ms']);
    return $res;
}

// -------------------- [TECHNICAL INDICATORS] --------------------
// Fungsi EMA, RSI, ATR, Fib tidak diubah karena sudah standar.
// ... (salin fungsi emaLast, calcRSI, calcATR, fibonacciLevelsFromHighLow, checkFibProximity dari skrip asli)

function emaLast(array $prices, int $period): ?float {
    if (count($prices) < $period) return null;
    $k = 2 / ($period + 1);
    $ema = array_sum(array_slice($prices, 0, $period)) / $period;
    for ($i = $period; $i < count($prices); $i++) {
        $ema = ($prices[$i] - $ema) * $k + $ema;
    }
    return $ema;
}

function calcRSI(array $prices, int $period = 14): ?float {
    if (count($prices) < $period + 1) return null;
    $gains = []; $losses = [];
    for ($i = 1; $i < count($prices); $i++) {
        $d = $prices[$i] - $prices[$i - 1];
        $gains[] = $d > 0 ? $d : 0;
        $losses[] = $d < 0 ? abs($d) : 0;
    }
    $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
    $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;
    for ($i = $period; $i < count($gains); $i++) {
        $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
        $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;
    }
    if ($avgLoss == 0) return 100.0;
    $rs = $avgGain / $avgLoss;
    return 100.0 - (100.0 / (1.0 + $rs));
}

function calcATR(array $klines, int $period = 14): ?float {
    if (count($klines) < $period + 1) return null;
    $trs = [];
    for ($i = 1; $i < count($klines); $i++) {
        $h = (float)$klines[$i][2];
        $l = (float)$klines[$i][3];
        $pc = (float)$klines[$i - 1][4];
        $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
    }
    if(count($trs) < $period) return null;
    $slice = array_slice($trs, -$period);
    return array_sum($slice) / count($slice);
}

function fibonacciLevelsFromHighLow(float $high, float $low): array {
    $range = $high - $low;
    return [
        "0.236" => $high - ($range * 0.236), "0.382" => $high - ($range * 0.382),
        "0.500" => $high - ($range * 0.5),   "0.618" => $high - ($range * 0.618),
        "0.786" => $high - ($range * 0.786),
    ];
}

function checkFibProximity(float $price, array $levels, float $thresholdPct = 1.0): array {
    foreach ($levels as $ratioStr => $level) {
        if ($price == 0) continue;
        $dist_pct = abs($price - $level) / $price * 100.0;
        if ($dist_pct <= $thresholdPct) {
            $type = (in_array($ratioStr, ['0.382', '0.500', '0.618'])) ? 'support' : 'resistance';
            return ['near' => true, 'ratio' => $ratioStr, 'type' => $type, 'dist_pct' => $dist_pct, 'level' => $level];
        }
    }
    return ['near' => false, 'ratio' => null, 'type' => 'neutral', 'dist_pct' => null, 'level' => null];
}

/**
 * [BARU] Menghitung Pivot Points (Standard)
 * Menggunakan data High, Low, Close dari candle sebelumnya.
 * @param float $h High kemarin
 * @param float $l Low kemarin
 * @param float $c Close kemarin
 * @return array Level-level Pivot, Support (S1, S2), dan Resistance (R1, R2)
 */
function calculatePivotPoints(float $h, float $l, float $c): array {
    $p = ($h + $l + $c) / 3;
    $r1 = (2 * $p) - $l;
    $s1 = (2 * $p) - $h;
    $r2 = $p + ($h - $l);
    $s2 = $p - ($h - $l);
    return ['p' => $p, 's1' => $s1, 's2' => $s2, 'r1' => $r1, 'r2' => $r2];
}

// -------------------- [ANALISIS & SKORING] --------------------

/**
 * [BARU] Menentukan rezim pasar berdasarkan pergerakan BTC.
 * Hanya akan mencari sinyal LONG jika pasar tidak dalam kondisi Bearish kuat.
 */
function getMarketRegime(): array {
    $btcKlines = getBinanceKlines('BTCUSDT', '1d', 100);
    if (!$btcKlines || count($btcKlines) < 55) {
        return ['regime' => 'NETRAL', 'reason' => 'Data BTC tidak cukup'];
    }
    $closes = array_map(fn($k) => (float)$k[4], $btcKlines);
    $price = end($closes);
    $ema50 = emaLast($closes, 50);

    $regime = 'NETRAL';
    $reason = "Harga BTC (${price}) ";

    if ($price > $ema50 * 1.01) {
        $regime = 'BULLISH';
        $reason .= "> EMA50 (${ema50})";
    } elseif ($price < $ema50 * 0.99) {
        $regime = 'BEARISH';
        $reason .= "< EMA50 (${ema50})";
    } else {
        $regime = 'SIDEWAYS';
        $reason .= "dekat EMA50 (${ema50})";
    }

    return ['regime' => $regime, 'reason' => $reason];
}


/**
 * [DIREVISI] Menganalisis satu timeframe dengan skor konfluens
 */
function analyzeTF(string $pair, string $interval = '1h'): ?array {
    global $config;
    $klines = getBinanceKlines($pair, $interval, 200);
    if (!$klines || count($klines) < 50) return null;

    $closes = array_map(fn($k) => (float)$k[4], $klines);
    $vols = array_map(fn($k) => (float)$k[5], $klines);
    $price = end($closes);

    // Indikator dasar
    $emaShort = emaLast($closes, 9);
    $emaLong = emaLast($closes, 21);
    $rsiVal = calcRSI($closes, 14);
    $atrVal = calcATR($klines, 14);

    // --- Kalkulasi Skor Dasar ---
    $emaScore = ($emaShort > $emaLong) ? 1 : -1;
    $rsiScore = 0;
    if ($rsiVal >= 45 && $rsiVal <= 70) $rsiScore = 1;
    elseif ($rsiVal < 45) $rsiScore = -1;
    elseif ($rsiVal > 70) $rsiScore = -1; // Overbought dianggap negatif untuk entry long

    $medianVol = median(array_slice($vols, -50));
    $volScore = (end($vols) > $medianVol * 1.5) ? 1 : 0; // Hanya peduli volume tinggi

    // --- [REVISI] Menggunakan Pivot Points untuk S/R ---
    $prevCandle = $klines[count($klines) - 2];
    $pivots = calculatePivotPoints((float)$prevCandle[2], (float)$prevCandle[3], (float)$prevCandle[4]);
    $srScore = 0; $srLabel = 'Netral';
    $threshold = $price * 0.005; // 0.5% proximity
    if (abs($price - $pivots['s1']) < $threshold || abs($price - $pivots['s2']) < $threshold) {
        $srScore = 2; $srLabel = "Dekat Support Pivot"; // Skor lebih tinggi
    } elseif (abs($price - $pivots['r1']) < $threshold || abs($price - $pivots['r2']) < $threshold) {
        $srScore = -2; $srLabel = "Dekat Resistance Pivot";
    }

    // --- [BARU] Skor Konfluens ---
    $confluenceScore = 0;
    // Konfluens 1: Golden Cross (EMA9 > EMA21) + Volume Tinggi
    if ($emaScore > 0 && $volScore > 0) $confluenceScore += 2;
    // Konfluens 2: Harga memantul dari Support Pivot dengan RSI yang sehat
    if ($srScore > 0 && $rsiScore > 0) $confluenceScore += 2;
    // Konfluens 3: EMA Bullish dan harga di atas Pivot Point utama
    if ($emaScore > 0 && $price > $pivots['p']) $confluenceScore += 1;

    // --- Total Skor ---
    $totalScore = $emaScore + $rsiScore + $volScore + $srScore + $confluenceScore;

    // SL/TP berdasarkan ATR
    $atrUse = $atrVal ?: $price * 0.02; // Fallback 2% jika ATR null
    $sl = $price - 1.5 * $atrUse;
    $tp = $price + 2.5 * $atrUse;

    return [
        'price' => $price,
        'ema' => $emaScore > 0 ? "Bullish" : "Bearish",
        'rsi' => "RSI: " . round($rsiVal, 1),
        'volume' => $volScore > 0 ? "Vol Tinggi" : "Vol Normal",
        'sr' => $srLabel,
        'score' => $totalScore,
        'entry' => $price, 'sl' => $sl, 'tp' => $tp,
        'pivots' => $pivots
    ];
}


/**
 * [DIREVISI] Menganalisis multi-TF dan memberikan kesimpulan
 */
function analyzeMultiTFForPair(string $pair, ?array $trendData, float $usdToIdr): array {
    $tfList = ['1h' => 'H1', '4h' => 'H4', '1d' => 'D1'];
    $results = [];
    $totalScore = 0;
    $validTFs = 0;
    $priceMain = null;

    foreach ($tfList as $int => $label) {
        $r = analyzeTF($pair, $int);
        $results[$label] = $r;
        if ($r) {
            if ($priceMain === null) $priceMain = $r['price'];
            $totalScore += $r['score'];
            $validTFs++;
        }
    }

    // --- Logika Kesimpulan Baru ---
    $h1Score = $results['H1']['score'] ?? 0;
    $h4Score = $results['H4']['score'] ?? 0;
    $d1Score = $results['D1']['score'] ?? 0;

    $overall = "❌ Hindari";
    $confidence = "Rendah";

    // Skenario WIN RATE TINGGI: Ketiga timeframe selaras dan positif
    if ($h1Score >= 3 && $h4Score >= 3 && $d1Score >= 3) {
        $overall = "✅ POTENSI SANGAT BAIK (Semua TF selaras Bullish)";
        $confidence = "Tinggi";
    }
    // Skenario Swing Trading: Timeframe besar (D1/H4) mendukung
    elseif ($d1Score >= 2 && $h4Score >= 2) {
        $overall = "📈 Potensi Swing (D1 & H4 Bullish)";
        $confidence = "Menengah";
    }
    // Skenario Scalping/Intraday: Timeframe kecil kuat, besar netral/sedikit lemah
    elseif ($h1Score >= 4 && $h4Score >= 0) {
        $overall = "⚡️ Potensi Scalping/Intraday (H1 Kuat)";
        $confidence = "Menengah";
    }
     else {
        $overall = "⚠️ Sinyal beragam, tunggu konfirmasi lebih lanjut.";
        $confidence = "Rendah";
    }

    // --- Membangun Pesan Teks ---
    $out = [];
    $out[] = "📊 *{$pair}* | Analisis Multi-TF";
    if ($priceMain) $out[] = "💰 Harga: " . formatUSDIDR($priceMain, $usdToIdr, 4);
    $out[] = "------------------------------------";

    foreach ($tfList as $label) {
        $r = $results[$label];
        if (!$r) {
            $out[] = "• *{$label}*: Data tidak cukup";
            continue;
        }
        $out[] = "• *{$label}* (Skor: {$r['score']}) | {$r['ema']} | {$r['rsi']} | {$r['sr']}";
    }

    $out[] = "------------------------------------";

    // Ambil rekomendasi entry dari H1 jika sinyalnya bagus
    $rec = $results['H1'];
    if ($rec && $rec['score'] >= 3) {
        $out[] = "💡 *Rekomendasi Entry (dari H1):*";
        $out[] = "   ➤ Entry: " . formatUSDIDR($rec['entry'], $usdToIdr, 4);
        $out[] = "   🛑 SL: " . formatUSDIDR($rec['sl'], $usdToIdr, 4);
        $out[] = "   ✅ TP: " . formatUSDIDR($rec['tp'], $usdToIdr, 4);
        $rr_ratio = ($rec['tp'] - $rec['entry']) / ($rec['entry'] - $rec['sl']);
        $out[] = "   📈 R:R Ratio: ~1:" . number_format($rr_ratio, 2);
    }

    $out[] = "";
    $out[] = "⭐ *Kesimpulan*: {$overall}";
    $out[] = "✨ *Keyakinan Sinyal*: {$confidence}";

    return ['text' => implode("\n", $out), 'confidence' => $confidence, 'pair' => $pair];
}

// ======================= [MAIN EXECUTION] =======================
echo date("Y-m-d H:i:s") . " UTC - Crypto Scanner v" . SCRIPT_VERSION . "\n\n";

// 1. [BARU] Cek Rezim Pasar BTC
$market = getMarketRegime();
echo "Rezim Pasar (BTC): {$market['regime']} - {$market['reason']}\n";
if ($market['regime'] === 'BEARISH') {
    echo "Pasar sedang BEARISH. Tidak mencari sinyal LONG untuk altcoin. Skrip dihentikan.\n";
    // Optional: kirim notifikasi bahwa pasar sedang jelek
    // postTelegram($config['telegram_bot_token'], $config['telegram_chat_id'], "⚠️ Pasar sedang dalam mode *BEARISH*. Semua sinyal long ditunda.");
    exit;
}

// 2. Ambil koin trending & data Binance
$trending = getTrendingFromCoingecko(15);
$exinfo = getBinanceExchangeInfo();
$binmap = [];
if ($exinfo && isset($exinfo['symbols'])) {
    foreach ($exinfo['symbols'] as $s) {
        if ($s['status'] === 'TRADING' && $s['quoteAsset'] === 'USDT') {
            $binmap[$s['baseAsset']] = $s['symbol'];
        }
    }
}

// 3. Bangun daftar kandidat
$candidates = [];
foreach ($trending as $sym => $obj) {
    if (in_array($sym, $STABLES) || isset($candidates[$sym])) continue;
    if (isset($binmap[$sym])) $candidates[$sym] = $binmap[$sym];
    if (count($candidates) >= $config['max_coins']) break;
}
// Isi dengan top volume jika trending tidak cukup
if (count($candidates) < $config['max_coins']) {
    $more = getBinanceTopSymbolsByVolume(80);
    foreach ($more as $p) {
        $base = str_replace('USDT', '', $p);
        if (in_array($base, $STABLES) || isset($candidates[$base])) continue;
        $candidates[$base] = $p;
        if (count($candidates) >= $config['max_coins']) break;
    }
}
// Fallback jika API gagal
if (empty($candidates)) {
    foreach ($config['fallback_symbols'] as $b) {
        $candidates[$b] = strtoupper($b) . 'USDT';
    }
}
echo "Kandidat yang akan dianalisis: " . implode(', ', $candidates) . "\n\n";

// 4. Analisis BTC Barometer
$btcAnalysis = analyzeMultiTFForPair('BTCUSDT', $trending['BTC'] ?? null, $config['usd_to_idr']);
$header = "
*BTC Barometer*
Status Pasar: *{$market['regime']}*
{$btcAnalysis['text']}
====================\n\n";

// 5. Analisis setiap kandidat
$results = [];
foreach ($candidates as $base => $pair) {
    if ($pair === 'BTCUSDT') continue;
    $trendData = $trending[$base] ?? null;
    $results[] = analyzeMultiTFForPair($pair, $trendData, $config['usd_to_idr']);
}

// 6. [REVISI] Urutkan sinyal berdasarkan tingkat keyakinan (Confidence)
usort($results, function($a, $b) {
    $order = ['Tinggi' => 3, 'Menengah' => 2, 'Rendah' => 1];
    return ($order[$b['confidence']] ?? 0) <=> ($order[$a['confidence']] ?? 0);
});

// 7. Bangun pesan akhir
$finalMsg = $header;
$blocks = [];
foreach($results as $res) {
    if ($res['confidence'] !== 'Rendah' || $config['send_always']) {
       $blocks[] = $res['text'];
    }
}

if (empty($blocks)) {
    echo "Tidak ada sinyal dengan keyakinan Menengah/Tinggi yang ditemukan.\n";
    exit;
}

$finalMsg .= implode("\n\n", $blocks);
$finalMsg .= "\n\n_Disclaimer: Selalu lakukan riset sendiri (DYOR). Ini bukan nasihat keuangan. Bot v".SCRIPT_VERSION."_";

// 8. Kirim jika ada perubahan
$hash = md5($finalMsg);
$last = @file_get_contents($config['last_hash_file']);
if ($last === $hash && !$config['send_always']) {
    echo "Tidak ada perubahan sinyal. Pengiriman dibatalkan.\n\n";
    echo $finalMsg . "\n";
    exit;
}
@file_put_contents($config['last_hash_file'], $hash);

if ($config['telegram_bot_token'] && $config['telegram_chat_id']) {
    if (postTelegram($config['telegram_bot_token'], $config['telegram_chat_id'], $finalMsg)) {
        echo "Pesan berhasil dikirim ke Telegram.\n";
    } else {
        echo "Gagal mengirim pesan ke Telegram.\n";
    }
} else {
    echo "Telegram tidak dikonfigurasi. Pesan dicetak di sini:\n";
    echo $finalMsg . "\n";
}

?>
