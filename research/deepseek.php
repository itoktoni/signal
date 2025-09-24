<?php
// crypto_signal_fib_improved.php
// Improved crypto signal generator with better win rate

// -------------------- Load .env --------------------
function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        $k = trim($parts[0]); $v = trim($parts[1]);
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
}
loadEnv();

// -------------------- Config --------------------
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$TELEGRAM_CHAT_ID   = getenv('TELEGRAM_CHAT_ID') ?: '';
$BINANCE_API        = getenv('BINANCE_API') ?: 'https://api.binance.com';
$COINGECKO_API_KEY  = getenv('COINGECKO_API_KEY') ?: '';
$USD_TO_IDR         = floatval(getenv('USD_TO_IDR') ?: 16000);
$EMA_SHORT          = intval(getenv('EMA_SHORT') ?: 9);
$EMA_LONG           = intval(getenv('EMA_LONG') ?: 21);
$RSI_PERIOD         = intval(getenv('RSI_PERIOD') ?: 14);
$ATR_PERIOD         = intval(getenv('ATR_PERIOD') ?: 14);
$SNR_PERIOD         = intval(getenv('SNR_PERIOD') ?: 20);
$MAX_COINS          = intval(getenv('MAX_COINS') ?: 5);
$REQUEST_DELAY_MS   = intval(getenv('REQUEST_DELAY_MS') ?: 200);
$FIB_THRESHOLD_PCT  = floatval(getenv('FIB_THRESHOLD_PCT') ?: 1.0);
$LAST_HASH_FILE     = getenv('LAST_HASH_FILE') ?: __DIR__ . '/.last_signal_hash';
$SEND_ALWAYS        = intval(getenv('SEND_ALWAYS') ?: 0);
$FALLBACK_SYMBOLS   = array_map('trim', explode(',', getenv('FALLBACK_SYMBOLS') ?: 'BTC,ETH,BNB,SOL,XRP,AVAX,ADA,DOT,MATIC,LINK'));
$MIN_VOLUME_USDT    = floatval(getenv('MIN_VOLUME_USDT') ?: 1000000); // Filter volume minimum

date_default_timezone_set('asia/jakarta');
set_time_limit(0);

// -------------------- Utilities --------------------
function sleep_ms($ms) { usleep(max(0,intval($ms)) * 1000); }

function httpGetJson($url, $headers = [], $timeout = 15) {
    $ch = curl_init($url);
    $default = ["User-Agent: crypto-signal-bot/1.0", "Accept: application/json"];
    $hdrs = array_merge($default, $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $hdrs,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        error_log("cURL error: $err -> $url");
        return null;
    }
    if ($code >= 400) {
        error_log("HTTP $code -> $url : " . substr($resp,0,300));
        return null;
    }
    $json = json_decode($resp, true);
    return is_array($json) ? $json : null;
}

function postTelegram($botToken, $chatId, $text) {
    if (!$botToken || !$chatId) return false;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 15
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code >= 400) error_log("Telegram send error: $code -> $res");
    return $res;
}

function isStablecoin($symbol) {
    $stables = ['USDT','USDC','BUSD','DAI','TUSD','USDP','FDUSD','EURT','GUSD','LUSD','USDJ','USDD','USTC'];
    return in_array(strtoupper($symbol), $stables);
}

function formatUSDIDR($usd, $usdToIdr, $dec = 2) {
    $usdFmt = '$' . number_format($usd, $dec, '.', ',');
    $idr = intval(round($usd * $usdToIdr));
    $idrFmt = 'Rp ' . number_format($idr, 0, ',', '.');
    return "{$usdFmt} ({$idrFmt})";
}

// -------------------- Data sources --------------------
function getTrendingFromCoingecko($limit = 15) {
    global $COINGECKO_API_KEY, $REQUEST_DELAY_MS;
    $base = $COINGECKO_API_KEY ? "https://pro-api.coingecko.com" : "https://api.coingecko.com";
    $url = $base . "/api/v3/search/trending";
    $headers = $COINGECKO_API_KEY ? ["x-cg-pro-api-key: $COINGECKO_API_KEY"] : [];
    $res = httpGetJson($url, $headers);
    sleep_ms($REQUEST_DELAY_MS);
    $out = [];
    if (!$res || !isset($res['coins'])) return $out;
    foreach ($res['coins'] as $c) {
        if (!isset($c['item'])) continue;
        $item = $c['item'];
        $sym = strtoupper($item['symbol'] ?? '');
        $change = null;
        if (isset($item['data']['price_change_percentage_24h'])) {
            $pc = $item['data']['price_change_percentage_24h'];
            if (is_array($pc)) {
                $change = $pc['usd'] ?? (array_values($pc)[0] ?? null);
            } else {
                $change = floatval($pc);
            }
        }
        $out[$sym] = ['symbol'=>$sym,'change24h'=>is_null($change)?null:floatval($change)];
        if (count($out) >= $limit) break;
    }
    return $out;
}

function getBinanceExchangeInfo() {
    global $BINANCE_API, $REQUEST_DELAY_MS;
    $res = httpGetJson($BINANCE_API . "/api/v3/exchangeInfo");
    sleep_ms($REQUEST_DELAY_MS);
    return $res;
}

function getBinanceTopSymbolsByVolume($limit = 50) {
    global $BINANCE_API, $REQUEST_DELAY_MS;
    $res = httpGetJson($BINANCE_API . "/api/v3/ticker/24hr");
    sleep_ms($REQUEST_DELAY_MS);
    if (!$res) return [];
    $usdt = array_filter($res, fn($t) => substr($t['symbol'], -4) === 'USDT');
    usort($usdt, fn($a,$b) => floatval($b['quoteVolume']) <=> floatval($a['quoteVolume']));
    $symbols = array_map(fn($t) => $t['symbol'], array_slice($usdt, 0, $limit));
    return $symbols;
}

function getBinanceKlines($pair, $interval='1h', $limit=200) {
    global $BINANCE_API, $REQUEST_DELAY_MS;
    if (!preg_match('/USDT$/', $pair)) $pair = $pair . 'USDT';
    $url = $BINANCE_API . "/api/v3/klines?symbol=" . urlencode($pair) . "&interval={$interval}&limit={$limit}";
    $res = httpGetJson($url);
    sleep_ms($REQUEST_DELAY_MS);
    return is_array($res) ? $res : null;
}

// -------------------- Technicals (Improved) --------------------
function emaLast($prices, $period) {
    if (!is_array($prices) || count($prices) < $period) return null;
    $k = 2 / ($period + 1);
    $ema = array_sum(array_slice($prices, 0, $period)) / $period;
    for ($i = $period; $i < count($prices); $i++) {
        $ema = ($prices[$i] - $ema) * $k + $ema;
    }
    return $ema;
}

function calcRSI($prices, $period = 14) {
    if (!is_array($prices) || count($prices) < $period + 1) return null;
    $gains = []; $losses = [];
    for ($i = 1; $i < count($prices); $i++) {
        $d = $prices[$i] - $prices[$i-1];
        $gains[] = $d > 0 ? $d : 0;
        $losses[] = $d < 0 ? abs($d) : 0;
    }
    $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
    $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;
    for ($i = $period; $i < count($gains); $i++) {
        $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
        $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;
    }
    if ($avgLoss == 0) return 100;
    $rs = $avgGain / $avgLoss;
    return 100 - (100 / (1 + $rs));
}

function calcATR($klines, $period = 14) {
    if (!is_array($klines) || count($klines) < $period + 1) return null;
    $trs = [];
    for ($i = 1; $i < count($klines); $i++) {
        $high = floatval($klines[$i][2]);
        $low = floatval($klines[$i][3]);
        $prevClose = floatval($klines[$i-1][4]);
        $trs[] = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
    }
    $slice = array_slice($trs, -$period);
    return array_sum($slice) / count($slice);
}

function supportResistanceSimple($closes, $period = 20) {
    if (!is_array($closes) || count($closes) < 3) return [null,null];
    $recent = array_slice($closes, -$period);
    return [min($recent), max($recent)];
}

// Improved Fibonacci detection with proper swing points
function findSwingPoints($closes, $lookback = 50) {
    $swingHighs = [];
    $swingLows = [];

    for ($i = 5; $i < count($closes) - 5; $i++) {
        $isHigh = true;
        $isLow = true;

        // Check if this is a swing high
        for ($j = max(0, $i - 5); $j <= min(count($closes) - 1, $i + 5); $j++) {
            if ($j == $i) continue;
            if ($closes[$j] > $closes[$i]) {
                $isHigh = false;
                break;
            }
        }

        // Check if this is a swing low
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

    // Get the most recent significant swings
    usort($swingHighs, fn($a, $b) => $b['index'] <=> $a['index']);
    usort($swingLows, fn($a, $b) => $b['index'] <=> $a['index']);

    $recentHighs = array_slice($swingHighs, 0, 3);
    $recentLows = array_slice($swingLows, 0, 3);

    if (empty($recentHighs) || empty($recentLows)) {
        // Fallback to simple min/max if no swings found
        $recent = array_slice($closes, -$lookback);
        return [max($recent), min($recent)];
    }

    $high = max(array_column($recentHighs, 'price'));
    $low = min(array_column($recentLows, 'price'));

    return [$high, $low];
}

function fibonacciLevels($high, $low) {
    $ratios = [0, 0.236, 0.382, 0.5, 0.618, 0.786, 1.0, 1.272, 1.618];
    $levels = [];
    $range = $high - $low;

    foreach ($ratios as $r) {
        $levels[$r] = $high - ($range * $r);
    }

    return $levels;
}

function checkFibProximity($price, $levels, $thresholdPct=1.0) {
    foreach ($levels as $ratio => $level) {
        $dist_pct = abs($price - $level) / max($price, 1) * 100.0;
        if ($dist_pct <= $thresholdPct) {
            $type = ($ratio >= 0.382 && $ratio <= 0.618) ? 'support' : 'resistance';
            return ['near'=>true, 'ratio'=>$ratio, 'type'=>$type, 'dist_pct'=>$dist_pct, 'level'=>$level];
        }
    }
    return ['near'=>false,'ratio'=>null,'type'=>'neutral','dist_pct'=>null,'level'=>null];
}

// New: Volume analysis with multiple metrics
function analyzeVolume($volumes, $price) {
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

// New: MACD indicator for momentum
function calculateMACD($prices, $fast = 12, $slow = 26, $signal = 9) {
    if (count($prices) < $slow + $signal) return null;

    $emaFast = [];
    $emaSlow = [];
    $macdLine = [];
    $signalLine = [];
    $histogram = [];

    // Calculate EMAs
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

    // Calculate Signal line from MACD line
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

// New: ADX for trend strength
function calculateADX($highs, $lows, $closes, $period = 14) {
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

    // Smooth the values
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

    // ADX is smoothed DX
    $adx = [];
    for ($i = $period * 2; $i < count($dx); $i++) {
        $adx[$i] = array_sum(array_slice($dx, $i - $period + 1, $period)) / $period;
    }

    return end($adx);
}

function klinesToCloses($klines) {
    return array_map(fn($k) => floatval($k[4]), $klines);
}

function klinesToVolumes($klines) {
    return array_map(fn($k) => floatval($k[5]), $klines);
}

function klinesToHighs($klines) {
    return array_map(fn($k) => floatval($k[2]), $klines);
}

function klinesToLows($klines) {
    return array_map(fn($k) => floatval($k[3]), $klines);
}

// -------------------- Scoring & Formatter --------------------
function rsiShortLabel($rsi) {
    if ($rsi === null) return 'N/A';
    $r = floatval($rsi);
    if ($r <= 30) return 'Jenuh Jual';
    if ($r > 30 && $r < 45) return 'Lemah';
    if ($r >= 45 && $r < 60) return 'Bagus';
    if ($r >= 60 && $r < 75) return 'Kuat';
    return 'Jenuh Beli';
}

function generateReportForPair($pair, $interval='1h', $trendData = null) {
    global $EMA_SHORT, $EMA_LONG, $RSI_PERIOD, $ATR_PERIOD, $SNR_PERIOD, $USD_TO_IDR, $FIB_THRESHOLD_PCT, $MIN_VOLUME_USDT;

    $klines = getBinanceKlines($pair, $interval, 100);
    if (!is_array($klines) || count($klines) < 50) return null;

    $closes = klinesToCloses($klines);
    $highs = klinesToHighs($klines);
    $lows = klinesToLows($klines);
    $vols = klinesToVolumes($klines);
    $price = end($closes);

    // Check minimum volume requirement
    $avgVolume = array_sum(array_slice($vols, -20)) / 20;
    if ($avgVolume * $price < $MIN_VOLUME_USDT) {
        return null; // Skip low volume pairs
    }

    $emaShort = emaLast($closes, $EMA_SHORT);
    $emaLong  = emaLast($closes, $EMA_LONG);
    $emaBull = ($emaShort !== null && $emaLong !== null && $emaShort > $emaLong);

    $rsiVal = calcRSI($closes, $RSI_PERIOD);
    $rsiLabel = rsiShortLabel($rsiVal);

    // Improved volume analysis
    $volumeAnalysis = analyzeVolume($vols, $price);

    list($support, $resistance) = supportResistanceSimple($closes, $SNR_PERIOD);
    $srLabel = 'Netral';
    if ($support && $resistance) {
        $srLabel = (abs($price - $support) <= ($price * 0.01)) ? 'Dekat support' : ((abs($price - $resistance) <= ($price * 0.01)) ? 'Dekat resistance' : 'Netral');
    }

    $atrVal = calcATR($klines, $ATR_PERIOD);
    $atrLabelArr = atrShortLabel($atrVal, $price);

    // Improved Fibonacci with proper swing points
    list($swingHigh, $swingLow) = findSwingPoints($closes, 50);
    $fibLevels = fibonacciLevels($swingHigh, $swingLow);
    $fibProx = checkFibProximity($price, $fibLevels, $FIB_THRESHOLD_PCT);

    // Additional indicators
    $macd = calculateMACD($closes);
    $adx = calculateADX($highs, $lows, $closes);

    // score components
    $score = 0;
    $lines = [];

    // EMA
    if ($emaBull) {
        $score += 1.5;
        $lines[] = "EMA: Bullish (+1.5) → Tren naik";
    } else {
        $score -= 1.5;
        $lines[] = "EMA: Bearish (-1.5) → Tren turun";
    }

    // RSI
    if ($rsiVal >= 45 && $rsiVal <= 65) {
        $score += 1.5;
        $lines[] = "RSI: {$rsiLabel} (+1.5) → Optimal";
    } elseif ($rsiVal < 30 || $rsiVal > 70) {
        $score -= 1;
        $lines[] = "RSI: {$rsiLabel} (-1) → Ekstrem";
    } else {
        $lines[] = "RSI: {$rsiLabel} (0)";
    }

    // Volume
    $score += $volumeAnalysis['score'];
    $lines[] = $volumeAnalysis['label'];

    // S/R
    if ($srLabel === 'Dekat support') {
        $score += 1.5;
        $lines[] = "S/R: Support (+1.5)";
    } elseif ($srLabel === 'Dekat resistance') {
        $score -= 1.5;
        $lines[] = "S/R: Resistance (-1.5)";
    } else {
        $lines[] = "S/R: Netral (0)";
    }

    // ATR
    if ($atrLabelArr[1] === '-1') {
        $score -= 1;
        $lines[] = "ATR: Liar (-1) → Volatilitas tinggi";
    } else {
        $lines[] = ($atrLabelArr[1] === '+0' ? "ATR: Tenang (0)" : "ATR: Normal (0)");
    }

    // MACD
    if ($macd && $macd['bullish']) {
        $score += 1;
        $lines[] = "MACD: Bullish (+1) → Momentum naik";
    } elseif ($macd && !$macd['bullish']) {
        $score -= 1;
        $lines[] = "MACD: Bearish (-1) → Momentum turun";
    } else {
        $lines[] = "MACD: N/A (0)";
    }

    // ADX (trend strength)
    if ($adx > 25) {
        $score += 1;
        $lines[] = "ADX: Kuat (+1) → Tren kuat ({$adx})";
    } elseif ($adx < 20) {
        $score -= 0.5;
        $lines[] = "ADX: Lemah (-0.5) → Tren lemah ({$adx})";
    } else {
        $lines[] = "ADX: Moderate (0) → ({$adx})";
    }

    // Trend (CoinGecko trending change)
    if ($trendData && isset($trendData['change24h'])) {
        $chg = $trendData['change24h'];
        if ($chg === null) {
            $lines[] = "Trend: Trending (0)";
        } else {
            if ($chg > 8) {
                $score += 1.5;
                $lines[] = "Trend: Naik kuat (+1.5) → {$chg}%";
            } elseif ($chg > 3) {
                $score += 1;
                $lines[] = "Trend: Naik (+1) → {$chg}%";
            } elseif ($chg < -5) {
                $score -= 1.5;
                $lines[] = "Trend: Turun kuat (-1.5) → {$chg}%";
            } elseif ($chg < -2) {
                $score -= 1;
                $lines[] = "Trend: Turun (-1) → {$chg}%";
            } else {
                $lines[] = "Trend: Netral (0) → {$chg}%";
            }
        }
    } else {
        $lines[] = "Trend: N/A (0)";
    }

    // Fibonacci proximity
    if ($fibProx['near']) {
        if ($fibProx['type'] === 'support') {
            $score += 1.5;
            $lines[] = sprintf("Fib: Dekat support %.3f (+1.5)", $fibProx['ratio']);
        } else {
            $score -= 1.5;
            $lines[] = sprintf("Fib: Dekat resistance %.3f (-1.5)", $fibProx['ratio']);
        }
    } else {
        $lines[] = "Fib: Jauh (0)";
    }

    // Entry/SL/TP: Improved risk management
    $atrUse = $atrVal ?: max(0.01, $price * 0.01);

    if ($score >= 5) {
        // Strong bullish signal
        $entry = $price;
        $sl = round($entry - 1.0 * $atrUse, 8);  // Tighter SL for strong signals
        $tp = round($entry + 3.0 * $atrUse, 8);  // Better R:R ratio
        $riskReward = "1:3";
    } elseif ($score >= 3) {
        // Moderate bullish signal
        $entry = $price;
        $sl = round($entry - 1.2 * $atrUse, 8);
        $tp = round($entry + 2.5 * $atrUse, 8);
        $riskReward = "1:2";
    } elseif ($score <= -4) {
        // Strong bearish signal
        $entry = $price;
        $sl = round($entry + 1.0 * $atrUse, 8);
        $tp = round($entry - 3.0 * $atrUse, 8);
        $riskReward = "1:3";
    } elseif ($score <= -2) {
        // Moderate bearish signal
        $entry = $price;
        $sl = round($entry + 1.2 * $atrUse, 8);
        $tp = round($entry - 2.5 * $atrUse, 8);
        $riskReward = "1:2";
    } else {
        // neutral/mixed
        $entry = $price;
        $sl = round($entry - 1.0 * $atrUse, 8);
        $tp = round($entry + 1.5 * $atrUse, 8);
        $riskReward = "1:1.5";
    }

    // format numbers
    $dec = ($price < 1) ? 6 : 2;
    $fmt = fn($v) => is_null($v) ? 'N/A' : number_format($v, $dec, '.', '');

    return [
        'pair' => $pair,
        'price' => $price,
        'price_fmt' => formatUSDIDR($price, $USD_TO_IDR, $dec),
        'support' => $support,
        'resistance' => $resistance,
        'lines' => $lines,
        'score' => $score,
        'decision' => ($score >= 5 ? '✅ STRONG ENTRY (setup sangat kuat)' :
                     ($score >= 3 ? '✅ Entry (setup kuat)' :
                     ($score <= -4 ? '❌ STRONG AVOID (hindari kuat)' :
                     ($score <= -2 ? '❌ Hindari' : '⚠️ Tunggu konfirmasi')))),
        'entry' => $fmt($entry),
        'sl' => $fmt($sl),
        'tp' => $fmt($tp),
        'risk_reward' => $riskReward,
        'fib' => $fibProx
    ];
}

// -------------------- small helpers --------------------
function median($arr){ if(!is_array($arr)||count($arr)==0) return 0; sort($arr); $c=count($arr); $m=intval($c/2); return ($c%2)?$arr[$m]:($arr[$m-1]+$arr[$m])/2; }
function atrShortLabel($atr, $price) {
    if (!$atr || !$price) return ['N/A','0'];
    $pct = ($atr / $price) * 100;
    if ($pct < 1.5) return ['Tenang','+0'];
    if ($pct < 4) return ['Normal','0'];
    return ['Liar','-1'];
}

// -------------------- Formatter for Telegram-friendly output --------------------
function formatSignalBlock($report, $usdToIdr) {
    $pair = $report['pair'] ?? 'N/A';
    $tf = 'H1';
    $priceTxt = $report['price_fmt'] ?? formatUSDIDR($report['price'], $usdToIdr);
    $out = [];
    $out[] = "📊 *{$pair}* ({$tf})";
    $out[] = "";
    $out[] = "💰 Harga: {$priceTxt}";
    $out[] = "";
    $out[] = "*Analisis Teknikal:*";
    foreach ($report['lines'] as $l) $out[] = "• {$l}";
    $out[] = "";
    $out[] = "🎯 *Total Skor: {$report['score']}* → {$report['decision']}";
    $out[] = "";
    $out[] = "*Trading Plan:*";
    $out[] = "🎯 Entry: $" . $report['entry'] . " (" . formatUSDIDR(floatval($report['entry']), $usdToIdr) . ")";
    $out[] = "🛑 SL: $" . $report['sl'] . " (" . formatUSDIDR(floatval($report['sl']), $usdToIdr) . ")";
    $out[] = "✅ TP: $" . $report['tp'] . " (" . formatUSDIDR(floatval($report['tp']), $usdToIdr) . ")";
    $out[] = "📊 R/R Ratio: " . $report['risk_reward'];

    if (isset($report['fib']) && $report['fib']['near']) {
        $out[] = "";
        $out[] = "*Fibonacci Analysis:*";
        $out[] = sprintf("Level: %.3f at %s (distance: %.2f%%)",
            $report['fib']['ratio'],
            number_format($report['fib']['level'], 6, '.', ''),
            $report['fib']['dist_pct']);
    }

    $out[] = "";
    $out[] = "⏰ " . date('Y-m-d H:i:s');
    $out[] = "#{$pair} #CryptoSignal";

    return implode("\n", $out);
}

// -------------------- MAIN --------------------
echo date("Y-m-d H:i:s")." UTC\n\n";

// 1) get trending from coingecko
$trending = getTrendingFromCoingecko(15);

// 2) prepare candidate list by matching trending to binance exchangeInfo
$exinfo = getBinanceExchangeInfo();
$binanceSymbols = [];
if ($exinfo && isset($exinfo['symbols'])) {
    foreach ($exinfo['symbols'] as $s) {
        if ($s['status'] === 'TRADING' && $s['quoteAsset'] === 'USDT') {
            $binanceSymbols[$s['baseAsset']] = $s['symbol'];
        }
    }
}

// derive final list: trending that exists on binance, else fallback
$candidates = [];
foreach ($trending as $sym => $obj) {
    if (isset($binanceSymbols[$sym]) && !isStablecoin($sym)) {
        $candidates[] = $binanceSymbols[$sym];
    }
    if (count($candidates) >= $MAX_COINS) break;
}
if (count($candidates) < $MAX_COINS) {
    // fill with top volume pairs or fallback symbols
    $more = getBinanceTopSymbolsByVolume(50);
    foreach ($more as $p) {
        $base = preg_replace('/USDT$/', '', $p);
        if (isStablecoin($base)) continue;
        if (!in_array($p, $candidates)) $candidates[] = $p;
        if (count($candidates) >= $MAX_COINS) break;
    }
}
if (count($candidates) === 0) {
    // last resort fallback
    foreach ($FALLBACK_SYMBOLS as $b) {
        $p = strtoupper($b) . 'USDT';
        $candidates[] = $p;
        if (count($candidates) >= $MAX_COINS) break;
    }
}

// 3) BTC barometer
$btcReport = generateReportForPair('BTCUSDT', '1h', $trending['BTC']['change24h'] ?? null);
if ($btcReport) {
    // short barometer summary
    $btcLine = "{$btcReport['pair']} • {$btcReport['price_fmt']} • ➡️ {$btcReport['score']} → ";
    if ($btcReport['score'] >= 5) $btcLine .= "✅ Market sangat kuat, altcoin peluang bagus";
    elseif ($btcReport['score'] >= 3) $btcLine .= "✅ Market mendukung, altcoin lebih kuat";
    elseif ($btcReport['score'] >= 0) $btcLine .= "⚠️ Market ragu, hati-hati";
    else $btcLine .= "❌ Market lemah, waspada dump";
    $header = "📊 *BTC Barometer*: {$btcLine}\n\n";
} else {
    $header = "📊 *BTC Barometer*: N/A\n\n";
}

// 4) analyze candidates
$blocks = [];
foreach ($candidates as $pair) {
    sleep_ms($REQUEST_DELAY_MS);
    $base = preg_replace('/USDT$/', '', $pair);
    $trendData = $trending[$base] ?? null;
    $report = generateReportForPair($pair, '1h', $trendData);
    if ($report && $report['score'] >= 3) { // Only show signals with score >= 3
        $blocks[] = formatSignalBlock($report, $USD_TO_IDR);
    }
}

// build final message
if (empty($blocks)) {
    $finalMsg = $header . "⚠️ Tidak ada sinyal kuat saat ini. Tunggu setup yang lebih baik.";
} else {
    $finalMsg = $header . implode("\n\n", $blocks);
}

// dedupe & send telegram
$hash = md5($finalMsg);
$lastHash = @file_get_contents($LAST_HASH_FILE);
if ($lastHash === $hash && !$SEND_ALWAYS) {
    echo "No change in signal. Not sent to Telegram.\n";
    echo $finalMsg . "\n";
    exit;
}
@file_put_contents($LAST_HASH_FILE, $hash);

// send (if configured)
if ($TELEGRAM_BOT_TOKEN && $TELEGRAM_CHAT_ID) {
    $sent = postTelegram($TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID, $finalMsg);
    if ($sent === false) echo "Failed to send Telegram\n"; else echo "Sent to Telegram\n";
} else {
    echo "TELEGRAM not configured, printing output.\n";
}

echo $finalMsg . "\n";