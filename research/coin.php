<?php
// single_coin.php - Advanced Single Coin Analysis with AI
// Analyzes one coin (e.g., BTCUSDT) with technical indicators and AI-powered conclusion

date_default_timezone_set('UTC');
set_time_limit(0);

// -------------------- Load .env --------------------
function loadEnv($path = __DIR__ . '/.env') {
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
loadEnv();

// -------------------- Config --------------------
$BINANCE_API = getenv('BINANCE_API') ?: 'https://api.binance.com';
$GOOGLE_API_KEY = getenv('GOOGLE_API_KEY') ?: '';
$USD_TO_IDR = floatval(getenv('USD_TO_IDR') ?: 16000);
$REQUEST_DELAY_MS = intval(getenv('REQUEST_DELAY_MS') ?: 100);
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$TELEGRAM_CHAT_ID = getenv('TELEGRAM_CHAT_ID') ?: '';

$STABLES = ['USDT','USDC','BUSD','DAI','TUSD','USDP','FDUSD'];

// Get coin symbol from command line argument, default BTCUSDT
$coin = $argv[1] ?? 'BTCUSDT';
if (!preg_match('/USDT$/', $coin)) $coin = strtoupper($coin) . 'USDT';

// -------------------- Utilities --------------------
function sleep_ms($ms) { usleep(max(0,intval($ms)) * 1000); }

function httpGetJson($url, $headers = [], $timeout = 15) {
    $ch = curl_init($url);
    $hdr = array_merge(["User-Agent: single-coin-analysis/1.0"], $headers);
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
    $usdFmt = '$' . number_format($usd, $dec, '.', ',');
    $idr = intval(round($usd * $usdToIdr));
    $idrFmt = 'Rp ' . number_format($idr, 0, ',', '.');
    return "{$usdFmt} ({$idrFmt})";
}

// -------------------- Data Fetchers --------------------
function getBinanceKlines($pair, $interval='1h', $limit=200) {
    global $BINANCE_API, $REQUEST_DELAY_MS;
    $url = $BINANCE_API . "/api/v3/klines?symbol=" . urlencode($pair) . "&interval={$interval}&limit={$limit}";
    $res = httpGetJson($url);
    sleep_ms($REQUEST_DELAY_MS);
    return is_array($res) ? $res : null;
}

// -------------------- Technical Indicators --------------------
function calculateEMA(array $prices, int $period) {
    if (count($prices) < $period) return [];
    $k = 2 / ($period + 1);
    $emas = [];
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

function calcRSI(array $prices, int $period=14) {
    if (count($prices) < $period+1) return null;
    $gains=[]; $losses=[];
    for ($i=1;$i<count($prices);$i++) {
        $d = $prices[$i]-$prices[$i-1];
        $gains[] = $d>0?$d:0;
        $losses[] = $d<0?abs($d):0;
    }
    $avgGain = array_sum(array_slice($gains,0,$period)) / $period;
    $avgLoss = array_sum(array_slice($losses,0,$period)) / $period;
    for ($i=$period;$i<count($gains);$i++) {
        $avgGain = (($avgGain*($period-1)) + $gains[$i])/$period;
        $avgLoss = (($avgLoss*($period-1)) + $losses[$i])/$period;
    }
    if ($avgLoss == 0) return 100;
    $rs = $avgGain / $avgLoss;
    return 100 - (100/(1+$rs));
}

function calcATR(array $klines, int $period=14) {
    if (count($klines) < $period+1) return null;
    $trs=[];
    for ($i=1;$i<count($klines);$i++) {
        $h = floatval($klines[$i][2]);
        $l = floatval($klines[$i][3]);
        $pc = floatval($klines[$i-1][4]);
        $trs[] = max($h-$l, abs($h-$pc), abs($l-$pc));
    }
    $slice = array_slice($trs,-$period);
    return array_sum($slice)/count($slice);
}

function calcMACD(array $prices, int $fast=12, int $slow=26, int $signal=9) {
    if (count($prices) < $slow) return null;
    $emaFast = array_values(calculateEMA($prices, $fast));
    $emaSlow = array_values(calculateEMA($prices, $slow));

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

function supportResistanceSimple(array $closes, int $period=30) {
    $recent = array_slice($closes, -$period);
    return ['s' => min($recent), 'r' => max($recent)];
}

// -------------------- AI Integration --------------------
function getAIConclusion($analysisData) {
    global $GOOGLE_API_KEY;
    if (!$GOOGLE_API_KEY) return "AI tidak dikonfigurasi. Kesimpulan manual: " . ($analysisData['score'] > 3 ? "Masuk pasar" : "Hindari");

    $prompt = "Analisis teknikal kripto berikut dan berikan kesimpulan apakah harus masuk pasar atau tidak, dengan entry, exit, SL, TP:\n\n" .
              "Coin: {$analysisData['pair']}\n" .
              "Harga: {$analysisData['price']}\n" .
              "Skor: {$analysisData['score']}\n" .
              "EMA: {$analysisData['ema']}\n" .
              "MACD: {$analysisData['macd']}\n" .
              "RSI: {$analysisData['rsi']}\n" .
              "Entry: {$analysisData['entry']}, SL: {$analysisData['sl']}, TP: {$analysisData['tp']}\n" .
              "Berikan kesimpulan singkat dalam bahasa Indonesia.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$GOOGLE_API_KEY}";
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 400 || !$resp) return "AI gagal. Kesimpulan manual: " . ($analysisData['score'] > 3 ? "Masuk pasar" : "Hindari");

    $result = json_decode($resp, true);
    return $result['candidates'][0]['content']['parts'][0]['text'] ?? "AI tidak memberikan respons.";
}

// -------------------- Analysis Function --------------------
function advancedAnalysisTF($pair, $interval='1h') {
    $klines = getBinanceKlines($pair, $interval, 300);
    if (!$klines || count($klines) < 201) return null;

    $closes = array_map(fn($k)=>floatval($k[4]), $klines);
    $price = end($closes);

    $weights = ['trend' => 3, 'ema_cross' => 1.5, 'macd' => 2, 'rsi' => 1];

    // EMA 200 for trend
    $ema200 = emaLast($closes, 200);
    $trendScore = 0; $trendLabel = 'Netral';
    if ($ema200) {
        if ($price > $ema200 * 1.005) { $trendScore = 1; $trendLabel = "Uptrend"; }
        elseif ($price < $ema200 * 0.995) { $trendScore = -1; $trendLabel = "Downtrend"; }
    }

    // EMA Cross
    $emaShort = emaLast($closes, 9);
    $emaLong = emaLast($closes, 21);
    $emaScore = ($emaShort > $emaLong) ? 1 : -1;

    // MACD
    $macd = calcMACD($closes);
    $macDScore = 0; $macdLabel = 'Netral';
    if ($macd) {
        if ($macd['histogram'] > 0 && $macd['macd'] > $macd['signal']) { $macDScore = 1; $macdLabel = "Bullish"; }
        elseif ($macd['histogram'] < 0 && $macd['macd'] < $macd['signal']) { $macDScore = -1; $macdLabel = "Bearish"; }
    }

    // RSI
    $rsiVal = calcRSI($closes, 14);
    $rsiScore = ($rsiVal >= 50 && $rsiVal <= 70) ? 1 : (($rsiVal < 50) ? -1 : 0);

    // Total Score
    $totalScore = ($trendScore * $weights['trend']) +
                  ($emaScore * $weights['ema_cross']) +
                  ($macDScore * $weights['macd']) +
                  ($rsiScore * $weights['rsi']);

    // Risk Management
    $atrVal = calcATR($klines, 14) ?: ($price * 0.02);
    $sl = round($price - 2 * $atrVal, 8);
    $tp = round($price + 3 * $atrVal, 8);
    $rr_ratio = ($tp - $price) / ($price - $sl);

    return [
        'price' => $price,
        'score' => round($totalScore, 2),
        'rr_ratio' => round($rr_ratio, 2),
        'sl' => $sl,
        'tp' => $tp,
        'summary' => [
            "Trend" => $trendLabel,
            "EMA" => ($emaScore > 0 ? "Bullish" : "Bearish"),
            "MACD" => $macdLabel,
            "RSI" => round($rsiVal, 1),
        ],
        'ema' => ($emaScore > 0 ? "Bullish" : "Bearish"),
        'macd' => $macdLabel,
        'rsi' => round($rsiVal, 1)
    ];
}

// -------------------- Formatter --------------------
function formatAnalysisForTelegram($pair, $tfAnalyses) {
    global $USD_TO_IDR;

    $price = $tfAnalyses['H1']['price'] ?? 0;
    if ($price == 0) return "Data tidak cukup untuk {$pair}";

    $out = [];
    $out[] = "📊 *{$pair}* (Analisis Single Coin)";
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

    // AI Conclusion
    $aiData = [
        'pair' => $pair,
        'price' => $price,
        'score' => $tfAnalyses['H1']['score'] ?? 0,
        'ema' => $tfAnalyses['H1']['ema'] ?? 'N/A',
        'macd' => $tfAnalyses['H1']['macd'] ?? 'N/A',
        'rsi' => $tfAnalyses['H1']['rsi'] ?? 'N/A',
        'entry' => $tfAnalyses['H1']['price'] ?? 0,
        'sl' => $tfAnalyses['H1']['sl'] ?? 0,
        'tp' => $tfAnalyses['H1']['tp'] ?? 0
    ];
    $aiConclusion = getAIConclusion($aiData);
    $out[] = "*Kesimpulan AI:* {$aiConclusion}";

    return implode("\n", $out);
}

// -------------------- MAIN --------------------
echo date("Y-m-d H:i:s") . " UTC | Single Coin Analysis for {$coin}\n\n";

$tfAnalyses = [];
foreach (['1h'=>'H1', '4h'=>'H4', '1d'=>'1D'] as $int => $label) {
    $analysis = advancedAnalysisTF($coin, $int);
    if ($analysis) $tfAnalyses[$label] = $analysis;
}

if (empty($tfAnalyses)) {
    echo "Tidak ada data untuk {$coin}\n";
    exit;
}

$finalMsg = formatAnalysisForTelegram($coin, $tfAnalyses);

// Send to Telegram if configured
if ($TELEGRAM_BOT_TOKEN && $TELEGRAM_CHAT_ID) {
    postTelegram($TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID, $finalMsg);
    echo "Pesan terkirim ke Telegram.\n";
} else {
    echo "Telegram tidak dikonfigurasi. Mencetak pesan:\n";
}

echo $finalMsg . "\n";

?>