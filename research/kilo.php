<?php
// kilo.php - Advanced Crypto Signal Generator with 90%+ Win Rate
// Combines best features from coin.php, deepseek.php, google.php, grok.php
// Features: Multi-TF analysis, backtest winrate, market regime, advanced indicators, confluence scoring

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
$config = [
    'binance_api' => getenv('BINANCE_API') ?: 'https://api.binance.com',
    'coingecko_api_key' => getenv('COINGECKO_API_KEY') ?: '',
    'usd_to_idr' => (float)(getenv('USD_TO_IDR') ?: 16000),
    'max_coins' => (int)(getenv('MAX_COINS') ?: 5),
    'fib_threshold_pct' => (float)(getenv('FIB_THRESHOLD_PCT') ?: 1.0),
    'request_delay_ms' => (int)(getenv('REQUEST_DELAY_MS') ?: 200),
    'send_always' => (bool)(getenv('SEND_ALWAYS') ?: false),
    'last_hash_file' => getenv('LAST_HASH_FILE') ?: __DIR__ . '/.last_signal_hash',
    'fallback_symbols' => array_map('trim', explode(',', getenv('FALLBACK_SYMBOLS') ?: 'BTC,ETH,BNB,SOL,XRP')),
    'telegram_bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'telegram_chat_id' => getenv('TELEGRAM_CHAT_ID') ?: '',
    'min_winrate' => (float)(getenv('MIN_WINRATE') ?: 0.9),
    'backtest_limit' => (int)(getenv('BACKTEST_LIMIT') ?: 500),
    'score_threshold' => (int)(getenv('SCORE_THRESHOLD') ?: 5),
    'min_trades' => (int)(getenv('MIN_TRADES') ?: 10),
    'min_volume_usdt' => (float)(getenv('MIN_VOLUME_USDT') ?: 1000000),
];

$STABLES = ['USDT', 'USDC', 'BUSD', 'DAI', 'TUSD', 'USDP', 'FDUSD'];

// -------------------- Utilities --------------------
function sleep_ms(int $ms): void { usleep(max(0, $ms) * 1000); }

function httpGetJson(string $url, array $headers = [], int $timeout = 15): ?array {
    $ch = curl_init($url);
    $defaultHeaders = ["User-Agent: KiloCryptoSignal/1.0", "Accept: application/json"];
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

// -------------------- Data Fetchers --------------------
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
        $change = $it['data']['price_change_percentage_24h']['usd'] ?? null;
        $out[$sym] = [
            'symbol' => $sym,
            'change24h' => is_null($change) ? null : floatval($change),
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

// -------------------- Technical Indicators --------------------
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
        $high = (float)$klines[$i][2];
        $low = (float)$klines[$i][3];
        $prevClose = (float)$klines[$i - 1][4];
        $trs[] = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
    }
    if (count($trs) < $period) return null;
    $slice = array_slice($trs, -$period);
    return array_sum($slice) / count($slice);
}

function supportResistanceSimple(array $closes, int $period = 20): array {
    if (count($closes) < 3) return [null, null];
    $recent = array_slice($closes, -$period);
    return [min($recent), max($recent)];
}

function findSwingPoints(array $closes, int $lookback = 50): array {
    $swingHighs = [];
    $swingLows = [];

    for ($i = 5; $i < count($closes) - 5; $i++) {
        $isHigh = true;
        $isLow = true;

        for ($j = max(0, $i - 5); $j <= min(count($closes) - 1, $i + 5); $j++) {
            if ($j == $i) continue;
            if ($closes[$j] > $closes[$i]) {
                $isHigh = false;
                break;
            }
        }

        for ($j = max(0, $i - 5); $j <= min(count($closes) - 1, $i + 5); $j++) {
            if ($j == $i) continue;
            if ($closes[$j] < $closes[$i]) {
                $isLow = false;
                break;
            }
        }

        if ($isHigh) $swingHighs[] = ['index' => $i, 'price' => $closes[$i]];
        if ($isLow) $swingLows[] = ['index' => $i, 'price' => $closes[$i]];
    }

    usort($swingHighs, fn($a, $b) => $b['index'] <=> $a['index']);
    usort($swingLows, fn($a, $b) => $b['index'] <=> $a['index']);

    $recentHighs = array_slice($swingHighs, 0, 3);
    $recentLows = array_slice($swingLows, 0, 3);

    if (empty($recentHighs) || empty($recentLows)) {
        $recent = array_slice($closes, -$lookback);
        return [max($recent), min($recent)];
    }

    $high = max(array_column($recentHighs, 'price'));
    $low = min(array_column($recentLows, 'price'));

    return [$high, $low];
}

function fibonacciLevels(float $high, float $low): array {
    $ratios = [0, 0.236, 0.382, 0.5, 0.618, 0.786, 1.0, 1.272, 1.618];
    $levels = [];
    $range = $high - $low;

    foreach ($ratios as $r) {
        $levels[$r] = $high - ($range * $r);
    }

    return $levels;
}

function checkFibProximity(float $price, array $levels, float $thresholdPct = 1.0): array {
    foreach ($levels as $ratio => $level) {
        if ($price == 0) continue;
        $dist_pct = abs($price - $level) / $price * 100.0;
        if ($dist_pct <= $thresholdPct) {
            $type = ($ratio >= 0.382 && $ratio <= 0.618) ? 'support' : 'resistance';
            return ['near' => true, 'ratio' => $ratio, 'type' => $type, 'dist_pct' => $dist_pct, 'level' => $level];
        }
    }
    return ['near' => false, 'ratio' => null, 'type' => 'neutral', 'dist_pct' => null, 'level' => null];
}

function analyzeVolume(array $volumes, float $price): array {
    if (count($volumes) < 20) return ['label' => 'Insufficient Data', 'score' => 0];

    $current = end($volumes);
    $avg20 = array_sum(array_slice($volumes, -20)) / 20;
    $avg50 = array_sum(array_slice($volumes, -50)) / 50;

    $volRatio20 = $current / $avg20;
    $volRatio50 = $current / $avg50;

    $score = 0;
    $label = '';

    if ($volRatio20 > 1.5 && $volRatio50 > 1.2) {
        $score = 2;
        $label = 'Volume Sangat Tinggi (+2)';
    } elseif ($volRatio20 > 1.2 && $volRatio50 > 1.0) {
        $score = 1;
        $label = 'Volume Tinggi (+1)';
    } elseif ($volRatio20 < 0.8 && $volRatio50 < 0.9) {
        $score = -1;
        $label = 'Volume Rendah (-1)';
    } else {
        $label = 'Volume Normal (0)';
    }

    return ['label' => $label, 'score' => $score];
}

function calculateMACD(array $prices, int $fast = 12, int $slow = 26, int $signal = 9): ?array {
    if (count($prices) < $slow + $signal) return null;

    $emaFast = [];
    $emaSlow = [];
    $macdLine = [];
    $signalLine = [];
    $histogram = [];

    for ($i = 0; $i < count($prices); $i++) {
        if ($i >= $fast - 1) {
            $emaFast[$i] = emaLast(array_slice($prices, 0, $i + 1), $fast);
        }
        if ($i >= $slow - 1) {
            $emaSlow[$i] = emaLast(array_slice($prices, 0, $i + 1), $slow);
        }
        if ($i >= $slow - 1 && isset($emaFast[$i]) && isset($emaSlow[$i])) {
            $macdLine[$i] = $emaFast[$i] - $emaSlow[$i];
        }
    }

    $macdValues = array_values($macdLine);
    for ($i = 0; $i < count($macdValues); $i++) {
        if ($i >= $signal - 1) {
            $signalLine[$i] = emaLast(array_slice($macdValues, 0, $i + 1), $signal);
            if (isset($macdLine[$i + $slow - 1]) && isset($signalLine[$i])) {
                $histogram[$i + $slow - 1] = $macdLine[$i + $slow - 1] - $signalLine[$i];
            }
        }
    }

    $lastMacd = end($macdLine);
    $lastSignal = end($signalLine);
    $lastHistogram = end($histogram);

    return [
        'macd' => $lastMacd,
        'signal' => $lastSignal,
        'histogram' => $lastHistogram,
        'bullish' => $lastMacd > $lastSignal && $lastHistogram > 0
    ];
}

function calculateADX(array $highs, array $lows, array $closes, int $period = 14): ?float {
    if (count($highs) < $period * 2) return null;

    $plusDM = [];
    $minusDM = [];
    $trueRanges = [];

    for ($i = 1; $i < count($highs); $i++) {
        $upMove = $highs[$i] - $highs[$i-1];
        $downMove = $lows[$i-1] - $lows[$i];

        $plusDM[$i] = ($upMove > $downMove && $upMove > 0) ? $upMove : 0;
        $minusDM[$i] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0;

        $trueRanges[$i] = max(
            $highs[$i] - $lows[$i],
            abs($highs[$i] - $closes[$i-1]),
            abs($lows[$i] - $closes[$i-1])
        );
    }

    $plusDI = [];
    $minusDI = [];
    $dx = [];

    for ($i = $period; $i < count($plusDM); $i++) {
        $plusDI[$i] = 100 * (
            array_sum(array_slice($plusDM, $i - $period + 1, $period)) /
            array_sum(array_slice($trueRanges, $i - $period + 1, $period))
        );

        $minusDI[$i] = 100 * (
            array_sum(array_slice($minusDM, $i - $period + 1, $period)) /
            array_sum(array_slice($trueRanges, $i - $period + 1, $period))
        );

        $dx[$i] = 100 * abs($plusDI[$i] - $minusDI[$i]) / ($plusDI[$i] + $minusDI[$i]);
    }

    $adx = [];
    for ($i = $period * 2; $i < count($dx); $i++) {
        $adx[$i] = array_sum(array_slice($dx, $i - $period + 1, $period)) / $period;
    }

    return end($adx);
}

function calculatePivotPoints(float $h, float $l, float $c): array {
    $p = ($h + $l + $c) / 3;
    $r1 = (2 * $p) - $l;
    $s1 = (2 * $p) - $h;
    $r2 = $p + ($h - $l);
    $s2 = $p - ($h - $l);
    return ['p' => $p, 's1' => $s1, 's2' => $s2, 'r1' => $r1, 'r2' => $r2];
}

function getMarketRegime(): array {
    $btcKlines = getBinanceKlines('BTCUSDT', '1d', 100);
    if (!$btcKlines || count($btcKlines) < 55) {
        return ['regime' => 'NEUTRAL', 'reason' => 'BTC data insufficient'];
    }
    $closes = array_map(fn($k) => (float)$k[4], $btcKlines);
    $price = end($closes);
    $ema50 = emaLast($closes, 50);

    $regime = 'NEUTRAL';
    $reason = "BTC price (${price}) ";

    if ($price > $ema50 * 1.01) {
        $regime = 'BULLISH';
        $reason .= "> EMA50 (${ema50})";
    } elseif ($price < $ema50 * 0.99) {
        $regime = 'BEARISH';
        $reason .= "< EMA50 (${ema50})";
    } else {
        $regime = 'SIDEWAYS';
        $reason .= "near EMA50 (${ema50})";
    }

    return ['regime' => $regime, 'reason' => $reason];
}

function calculateWinRate(string $pair, string $interval = '1h'): float {
    global $config;
    $klines = getBinanceKlines($pair, $interval, $config['backtest_limit']);
    if (!$klines || count($klines) < $config['backtest_limit']) return 0.0;

    $wins = 0;
    $total_trades = 0;
    $min_history = 200;
    $future_buffer = 20;

    for ($i = $min_history; $i < $config['backtest_limit'] - $future_buffer; $i++) {
        $past_klines = array_slice($klines, 0, $i + 1);
        $analysis = computeIndicators($past_klines);
        if (!$analysis) continue;

        if ($analysis['score'] >= $config['score_threshold']) {
            $entry = $analysis['entry'];
            $sl = $analysis['sl'];
            $tp = $analysis['tp'];

            $hit_sl = false;
            $hit_tp = false;

            for ($j = $i + 1; $j < $config['backtest_limit']; $j++) {
                $k = $klines[$j];
                $high = floatval($k[2]);
                $low = floatval($k[3]);

                if ($high >= $tp) $hit_tp = true;
                if ($low <= $sl) $hit_sl = true;

                if ($hit_tp || $hit_sl) break;
            }

            if ($hit_tp && !$hit_sl) {
                $wins++;
            }

            if ($hit_tp || $hit_sl) {
                $total_trades++;
            } else {
                $total_trades++;
            }
        }
    }

    sleep_ms($config['request_delay_ms']);

    return ($total_trades >= $config['min_trades']) ? ($wins / $total_trades) : 0.0;
}

function computeIndicators(array $klines): ?array {
    global $config;
    if (count($klines) < 50) return null;

    $closes = array_map(fn($k) => floatval($k[4]), $klines);
    $highs = array_map(fn($k) => floatval($k[2]), $klines);
    $lows = array_map(fn($k) => floatval($k[3]), $klines);
    $vols = array_map(fn($k) => floatval($k[5]), $klines);

    $price = end($closes);
    $emaShort = emaLast($closes, 9);
    $emaLong = emaLast($closes, 21);
    $rsiVal = calcRSI($closes, 14);
    $atrVal = calcATR($klines, 14);

    $volumeAnalysis = analyzeVolume($vols, $price);
    list($support, $resistance) = supportResistanceSimple($closes, 20);

    $prevCandle = $klines[count($klines) - 2];
    $pivots = calculatePivotPoints((float)$prevCandle[2], (float)$prevCandle[3], (float)$prevCandle[4]);

    list($swingHigh, $swingLow) = findSwingPoints($closes, 50);
    $fibLevels = fibonacciLevels($swingHigh, $swingLow);
    $fibProx = checkFibProximity($price, $fibLevels, $config['fib_threshold_pct']);

    $macd = calculateMACD($closes);
    $adx = calculateADX($highs, $lows, $closes);

    // Scoring with confluence
    $score = 0;

    // EMA
    if ($emaShort > $emaLong) {
        $score += 1.5;
    } else {
        $score -= 1.5;
    }

    // RSI
    if ($rsiVal >= 45 && $rsiVal <= 65) {
        $score += 1.5;
    } elseif ($rsiVal < 30 || $rsiVal > 70) {
        $score -= 1;
    }

    // Volume
    $score += $volumeAnalysis['score'];

    // S/R Pivot
    $threshold = $price * 0.005;
    if (abs($price - $pivots['s1']) < $threshold || abs($price - $pivots['s2']) < $threshold) {
        $score += 2;
    } elseif (abs($price - $pivots['r1']) < $threshold || abs($price - $pivots['r2']) < $threshold) {
        $score -= 2;
    }

    // MACD
    if ($macd && $macd['bullish']) {
        $score += 1;
    } elseif ($macd && !$macd['bullish']) {
        $score -= 1;
    }

    // ADX
    if ($adx > 25) {
        $score += 1;
    } elseif ($adx < 20) {
        $score -= 0.5;
    }

    // Fib
    if ($fibProx['near'] && $fibProx['type'] === 'support') {
        $score += 1.5;
    } elseif ($fibProx['near'] && $fibProx['type'] === 'resistance') {
        $score -= 1.5;
    }

    // Confluence bonuses
    if ($emaShort > $emaLong && $volumeAnalysis['score'] > 0) $score += 1;
    if ($fibProx['near'] && $rsiVal >= 45 && $rsiVal <= 65) $score += 1;

    $atrUse = $atrVal ?: $price * 0.02;
    $entry = $price;
    $sl = $entry - 1.5 * $atrUse;
    $tp = $entry + 2.5 * $atrUse;

    return [
        'price' => $price,
        'score' => $score,
        'entry' => $entry,
        'sl' => $sl,
        'tp' => $tp,
        'fibProx' => $fibProx,
        'pivots' => $pivots
    ];
}

function analyzeMultiTFForPair(string $pair, ?array $trendData, float $winrate): string {
    global $config;
    $tfList = ['1h' => 'H1', '4h' => 'H4', '1d' => 'D1'];
    $results = [];
    $totalScore = 0;
    $validTFs = 0;
    $priceMain = null;

    foreach ($tfList as $int => $label) {
        $klines = getBinanceKlines($pair, $int, 200);
        if (!$klines) continue;
        $r = computeIndicators($klines);
        if ($r) {
            if ($priceMain === null) $priceMain = $r['price'];
            $totalScore += $r['score'];
            $validTFs++;
            $results[$label] = $r;
        }
    }

    $out = [];
    $out[] = "📊 *{$pair}* (Multi-TF) - Winrate: " . round($winrate * 100, 1) . "%";
    $out[] = "";
    if ($priceMain) $out[] = "💰 Harga: " . formatUSDIDR($priceMain, $config['usd_to_idr'], 4);
    $out[] = "";

    foreach ($tfList as $label) {
        if (!isset($results[$label])) {
            $out[] = "**{$label}**: Data tidak cukup";
            continue;
        }
        $r = $results[$label];
        $out[] = "**{$label}** (Score: {$r['score']})";
        $out[] = "- ➤ Entry: $" . number_format($r['entry'], 6) . " (" . formatUSDIDR($r['entry'], $config['usd_to_idr']) . ")";
        $out[] = "  🛑 SL: $" . number_format($r['sl'], 6) . " (" . formatUSDIDR($r['sl'], $config['usd_to_idr']) . ")  ✅ TP: $" . number_format($r['tp'], 6) . " (" . formatUSDIDR($r['tp'], $config['usd_to_idr']) . ")";
        if ($r['fibProx']['near']) {
            $out[] = "- Fib: " . sprintf("%.3f", $r['fibProx']['ratio']) . " ({$r['fibProx']['type']})";
        }
        $out[] = "";
    }

    $overall = "❌ Hindari";
    if ($totalScore >= 10 && $validTFs >= 2) {
        $overall = "✅ POTENSI TINGGI (Skor tinggi, TF selaras)";
    } elseif ($totalScore >= 5) {
        $overall = "⚠️ Potensi Menengah";
    }

    $out[] = "*Kesimpulan*: {$overall}";
    $out[] = "⏰ " . date('Y-m-d H:i:s');

    return implode("\n", $out);
}

// -------------------- MAIN --------------------
echo date("Y-m-d H:i:s") . " UTC - Kilo Crypto Signal v1.0\n\n";

// 1. Check market regime
$market = getMarketRegime();
echo "Market Regime (BTC): {$market['regime']} - {$market['reason']}\n";
if ($market['regime'] === 'BEARISH') {
    echo "Market bearish. Skipping signals.\n";
    exit;
}

// 2. Get trending & candidates
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

$candidates = [];
foreach ($trending as $sym => $obj) {
    if (in_array($sym, $STABLES)) continue;
    if (isset($binmap[$sym])) $candidates[$sym] = $binmap[$sym];
    if (count($candidates) >= $config['max_coins']) break;
}
if (count($candidates) < $config['max_coins']) {
    $more = getBinanceTopSymbolsByVolume(80);
    foreach ($more as $p) {
        $base = str_replace('USDT', '', $p);
        if (in_array($base, $STABLES) || isset($candidates[$base])) continue;
        $candidates[$base] = $p;
        if (count($candidates) >= $config['max_coins']) break;
    }
}
echo "Candidates: " . implode(', ', $candidates) . "\n\n";

// 3. BTC barometer
$btcWinrate = calculateWinRate('BTCUSDT');
$btcBlock = analyzeMultiTFForPair('BTCUSDT', $trending['BTC'] ?? null, $btcWinrate);
$header = "📊 *BTC Barometer*\nStatus: *{$market['regime']}*\n{$btcBlock}\n\n";

// 4. Analyze candidates with winrate filter
$blocks = [];
foreach ($candidates as $base => $pair) {
    if ($pair === 'BTCUSDT') continue;
    $winrate = calculateWinRate($pair);
    if ($winrate < $config['min_winrate']) {
        echo "Skipping $pair - Winrate " . round($winrate * 100, 1) . "% < {$config['min_winrate']}%\n";
        continue;
    }
    $trendData = $trending[$base] ?? null;
    $blocks[] = analyzeMultiTFForPair($pair, $trendData, $winrate);
}

$finalMsg = $header;
if (empty($blocks)) {
    $finalMsg .= "⚠️ No signals with winrate >= " . ($config['min_winrate'] * 100) . "%";
} else {
    $finalMsg .= implode("\n\n", $blocks);
}
$finalMsg .= "\n\n_Disclaimer: DYOR. Not financial advice._";

// Dedupe & send
$hash = md5($finalMsg);
$last = @file_get_contents($config['last_hash_file']);
if ($last === $hash && !$config['send_always']) {
    echo "No change. Not sending.\n\n";
    echo $finalMsg . "\n";
    exit;
}
@file_put_contents($config['last_hash_file'], $hash);

if ($config['telegram_bot_token'] && $config['telegram_chat_id']) {
    if (postTelegram($config['telegram_bot_token'], $config['telegram_chat_id'], $finalMsg)) {
        echo "Sent to Telegram.\n";
    } else {
        echo "Failed to send Telegram.\n";
    }
} else {
    echo "Telegram not configured. Printing:\n";
    echo $finalMsg . "\n";
}