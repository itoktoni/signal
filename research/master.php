<?php
// crypto_signal_multi_tf.php
// PHP 8.1+ recommended. Requires cURL. Put .env next to script if needed.

date_default_timezone_set('UTC');
set_time_limit(0);

// -------------------- load .env (simple loader) --------------------
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

// -------------------- config --------------------
$BINANCE_API        = getenv('BINANCE_API') ?: 'https://api.binance.com';
$COINGECKO_API_KEY  = getenv('COINGECKO_API_KEY') ?: '';
$USD_TO_IDR         = floatval(getenv('USD_TO_IDR') ?: 16000);
$MAX_COINS          = intval(getenv('MAX_COINS') ?: 3);
$FIB_THRESHOLD_PCT  = floatval(getenv('FIB_THRESHOLD_PCT') ?: 1.0);
$REQUEST_DELAY_MS   = intval(getenv('REQUEST_DELAY_MS') ?: 200);
$SEND_ALWAYS        = intval(getenv('SEND_ALWAYS') ?: 0);
$LAST_HASH_FILE     = getenv('LAST_HASH_FILE') ?: __DIR__ . '/.last_signal_hash';
$FALLBACK_SYMBOLS   = array_map('trim', explode(',', getenv('FALLBACK_SYMBOLS') ?: 'BTC,ETH,BNB,SOL,XRP,AVAX,ADA,DOT,MATIC,LINK'));
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$TELEGRAM_CHAT_ID   = getenv('TELEGRAM_CHAT_ID') ?: '';

$STABLES = ['USDT','USDC','BUSD','DAI','TUSD','USDP','FDUSD'];

// -------------------- utilities --------------------
function sleep_ms($ms) { usleep(max(0,intval($ms)) * 1000); }

function httpGetJson($url, $headers = [], $timeout = 15) {
    $ch = curl_init($url);
    $hdr = array_merge(["User-Agent: crypto-signal/1.0","Accept: application/json"], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $hdr,
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
    $j = json_decode($resp, true);
    return is_array($j) ? $j : null;
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

// -------------------- Data fetchers --------------------
function getTrendingFromCoingecko($limit=15) {
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
        $it = $c['item'];
        $sym = strtoupper($it['symbol'] ?? '');
        $change = null;
        if (isset($it['data']['price_change_percentage_24h'])) {
            $pc = $it['data']['price_change_percentage_24h'];
            if (is_array($pc)) $change = $pc['usd'] ?? (array_values($pc)[0] ?? null);
            else $change = floatval($pc);
        }
        $out[$sym] = ['symbol'=>$sym,'change24h'=>is_null($change)?null:floatval($change),'name'=>$it['name'] ?? null];
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

function getBinanceTopSymbolsByVolume($limit=50) {
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
    if (!preg_match('/USDT$/',$pair)) $pair = $pair . 'USDT';
    $url = $BINANCE_API . "/api/v3/klines?symbol=" . urlencode($pair) . "&interval={$interval}&limit={$limit}";
    $res = httpGetJson($url);
    sleep_ms($REQUEST_DELAY_MS);
    return is_array($res) ? $res : null;
}

// -------------------- technicals --------------------
function emaLast(array $prices, int $period) {
    if (count($prices) < $period) return null;
    $k = 2 / ($period + 1);
    $ema = array_sum(array_slice($prices,0,$period)) / $period; // SMA seed
    for ($i=$period;$i<count($prices);$i++) {
        $ema = ($prices[$i] - $ema) * $k + $ema;
    }
    return $ema;
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

function supportResistanceSimple(array $closes, int $period=20) {
    $recent = array_slice($closes, -$period);
    return [min($recent), max($recent)];
}

// fibonacci using string keys (no float keys)
function fibonacciLevelsFromHighLow(float $high, float $low) {
    $range = $high - $low;
    return [
        "0.236" => $high - ($range * 0.236),
        "0.382" => $high - ($range * 0.382),
        "0.500" => $high - ($range * 0.5),
        "0.618" => $high - ($range * 0.618),
        "0.786" => $high - ($range * 0.786),
    ];
}

function checkFibProximity(float $price, array $levels, float $thresholdPct=1.0) {
    foreach ($levels as $ratioStr => $level) {
        $dist_pct = abs($price - $level) / max($price,1) * 100.0;
        if ($dist_pct <= $thresholdPct) {
            $type = (in_array($ratioStr,['0.382','0.500','0.618'])) ? 'support' : 'resistance';
            return ['near'=>true,'ratio'=>$ratioStr,'type'=>$type,'dist_pct'=>$dist_pct,'level'=>$level];
        }
    }
    return ['near'=>false,'ratio'=>null,'type'=>'neutral','dist_pct'=>null,'level'=>null];
}

// -------------------- scoring per TF (with SL/TP) --------------------
function analyzeTF($pair, $interval='1h') {
    global $USD_TO_IDR;
    $klines = getBinanceKlines($pair, $interval, 200);
    if (!$klines || count($klines) < 30) return null;

    $closes = array_map(fn($k)=>floatval($k[4]), $klines);
    $highs  = array_map(fn($k)=>floatval($k[2]), $klines);
    $lows   = array_map(fn($k)=>floatval($k[3]), $klines);
    $vols   = array_map(fn($k)=>floatval($k[5]), $klines);

    $price = end($closes);
    $emaShort = emaLast($closes, 9);
    $emaLong  = emaLast($closes, 21);
    $emaScore = ($emaShort !== null && $emaLong !== null && $emaShort > $emaLong) ? 1 : -1;

    $rsiVal = calcRSI($closes, 14);
    $rsiLabel = 'N/A'; $rsiScore = 0;
    if ($rsiVal !== null) {
        if ($rsiVal >= 45 && $rsiVal <= 70) { $rsiLabel='Bagus'; $rsiScore=1; }
        elseif ($rsiVal > 70 && $rsiVal <= 85) { $rsiLabel='Kuat tapi rawan'; $rsiScore=0; }
        elseif ($rsiVal < 45 && $rsiVal >= 30) { $rsiLabel='Lemah'; $rsiScore=-1; }
        elseif ($rsiVal < 30) { $rsiLabel='Jenuh Jual'; $rsiScore=1; }
        else { $rsiLabel='Overbought'; $rsiScore=-1; }
    }

    $medianVol = median($vols);
    $volNow = end($vols);
    if ($medianVol > 0 && $volNow >= $medianVol * 1.2) { $volLabel='Tinggi'; $volScore=1; }
    elseif ($medianVol > 0 && $volNow <= $medianVol * 0.8) { $volLabel='Rendah'; $volScore=-1; }
    else { $volLabel='Sedang'; $volScore=0; }

    list($support,$resistance) = supportResistanceSimple($closes, 20);
    $srLabel='Netral'; $srScore=0;
    if ($support !== null && abs($price - $support) <= ($price * 0.01)) { $srLabel='Dekat support'; $srScore=1; }
    elseif ($resistance !== null && abs($price - $resistance) <= ($price * 0.01)) { $srLabel='Dekat resistance'; $srScore=-1; }

    $atrVal = calcATR($klines, 14);
    $atrPct = $atrVal ? ($atrVal / $price * 100) : 0;
    $atrLabel = ($atrPct < 2) ? 'Tenang' : (($atrPct < 5) ? 'Normal' : 'Liar');
    $atrScore = ($atrPct >= 5) ? -1 : 0;

    // Fibonacci proximity using recent highs/lows
    $recentSlice = array_slice($closes, -20);
    $swingHigh = max($recentSlice); $swingLow = min($recentSlice);
    $fibLevels = fibonacciLevelsFromHighLow($swingHigh, $swingLow);
    $fibProx = checkFibProximity($price, $fibLevels, floatval(getenv('FIB_THRESHOLD_PCT') ?: 1.0));
    $fibLabel = $fibProx['near'] ? ($fibProx['type'] === 'support' ? "Fib support {$fibProx['ratio']}" : "Fib resistance {$fibProx['ratio']}") : "Fib jauh";
    $fibScore = ($fibProx['near'] && $fibProx['type']==='support') ? 1 : (($fibProx['near'] && $fibProx['type']==='resistance') ? -1 : 0);

    // total score
    $score = $emaScore + $rsiScore + $volScore + $srScore + $atrScore + $fibScore;

    // Entry/SL/TP using ATR (if no atr use percent)
    $atrUse = $atrVal ?: max(0.0001, $price * 0.01);
    // For long entry
    $entry = $price;
    $sl = round($entry - 1.5 * $atrUse, 8);
    $tp = round($entry + 2.5 * $atrUse, 8);

    return [
        'price'=>$price,
        'price_fmt'=>formatUSDIDR($price, $GLOBALS['USD_TO_IDR']),
        'ema'=> $emaScore>0 ? "Bullish (+1)" : "Bearish (-1)",
        'rsi'=> "{$rsiLabel} ({$rsiScore})",
        'volume'=> "{$volLabel} ({$volScore})",
        'sr'=> "{$srLabel} ({$srScore})",
        'atr'=> "{$atrLabel} ({$atrScore})",
        'fib'=> "{$fibLabel} ({$fibScore})",
        'score'=>$score,
        'entry'=>$entry,
        'sl'=>$sl,
        'tp'=>$tp,
        'fibProx'=>$fibProx
    ];
}

// -------------------- helpers --------------------
function median($arr){ if(!is_array($arr)||count($arr)==0) return 0; sort($arr); $c=count($arr); $m=intval($c/2); return ($c%2)?$arr[$m]:($arr[$m-1]+$arr[$m])/2; }

// -------------------- multi-TF per coin formatter --------------------
function analyzeMultiTFForPair($pair, $trendData=null) {
    global $USD_TO_IDR;
    $tfList = ['1h'=>'H1','4h'=>'H4','1d'=>'1D'];
    $blocks = [];
    $scores = [];
    $priceMain = null;
    foreach ($tfList as $int => $label) {
        $r = analyzeTF($pair, $int);
        if (!$r) { $blocks[$label] = null; $scores[$label] = null; continue; }
        if ($priceMain === null) $priceMain = $r['price'];
        $blocks[$label] = $r;
        $scores[$label] = $r['score'];
    }

    // overall recommendation logic
    $validScores = array_filter($scores, fn($s)=>$s !== null);
    $pos = count(array_filter($validScores, fn($s)=>$s>0));
    $neg = count(array_filter($validScores, fn($s)=>$s<0));
    if ($pos === count($validScores) && $pos>0) $overall = "✅ Swing OK (TF semua mendukung)";
    elseif ($validScores['H1']>0 && ($validScores['H4']<=0 || $validScores['1D']<=0)) $overall = "⚠️ H1 oke — hanya scalping (jangan hold lama)";
    elseif ($pos > $neg) $overall = "⚠️ Mixed — tunggu konfirmasi";
    else $overall = "❌ Hindari (TF mayoritas negatif)";

    // build text block
    $out = [];
    $out[] = "📊 *{$pair}* (Multi-TF)";
    $out[] = "";
    if ($priceMain) $out[] = "💰 Harga sekarang: " . formatUSDIDR($priceMain, $USD_TO_IDR);
    $out[] = "";
    foreach (['H1','H4','1D'] as $lab) {
        $r = $blocks[$lab] ?? null;
        if (!$r) {
            $out[] = "**{$lab}**: Data tidak cukup";
            continue;
        }
        $out[] = "**{$lab}** (score: {$r['score']})";
        $out[] = "- EMA: {$r['ema']}";
        $out[] = "- RSI: {$r['rsi']}";
        $out[] = "- Vol: {$r['volume']}";
        $out[] = "- S/R: {$r['sr']}";
        $out[] = "- ATR: {$r['atr']}";
        $out[] = "- Fib: {$r['fib']}";
        $out[] = "- ➤ Entry: $" . number_format($r['entry'], 6) . " (" . formatUSDIDR($r['entry'], $USD_TO_IDR) . ")";
        $out[] = "  🛑 SL: $" . number_format($r['sl'], 6) . " (" . formatUSDIDR($r['sl'], $USD_TO_IDR) . ")  ✅ TP: $" . number_format($r['tp'], 6) . " (" . formatUSDIDR($r['tp'], $USD_TO_IDR) . ")";
        $out[] = "";
    }

    // trend data from coingecko (if given)
    if ($trendData && isset($trendData['change24h'])) {
        $chg = round($trendData['change24h'],2);
        $out[] = "🔥 Coingecko trend (24h): {$chg}%";
        if ($chg > 5) $out[] = "→ Trending naik (+1 confidence)";
        elseif ($chg < -3) $out[] = "→ Trending turun (-1 caution)";
    }

    $out[] = "";
    $out[] = "*Kesimpulan multi-timeframe:* {$overall}";

    return implode("\n", $out);
}

// -------------------- MAIN --------------------
echo date("Y-m-d H:i:s") . " UTC\n\n";

// 1) fetch coingecko trending
$trending = getTrendingFromCoingecko(15);

// 2) map binance symbols
$exinfo = getBinanceExchangeInfo();
$binmap = [];
if ($exinfo && isset($exinfo['symbols'])) {
    foreach ($exinfo['symbols'] as $s) {
        if ($s['status'] === 'TRADING' && $s['quoteAsset'] === 'USDT') {
            $binmap[$s['baseAsset']] = $s['symbol'];
        }
    }
}

// 3) build candidate list: trending that exist on binance, else top-volume fallback, else static fallback
$candidates = [];
foreach ($trending as $sym => $obj) {
    if (in_array($sym, $STABLES)) continue;
    if (isset($binmap[$sym])) $candidates[] = $binmap[$sym];
    if (count($candidates) >= $MAX_COINS) break;
}
if (count($candidates) < $MAX_COINS) {
    $more = getBinanceTopSymbolsByVolume(80);
    foreach ($more as $p) {
        $base = preg_replace('/USDT$/','',$p);
        if (in_array($base,$STABLES)) continue;
        if (!in_array($p,$candidates)) $candidates[] = $p;
        if (count($candidates) >= $MAX_COINS) break;
    }
}
if (count($candidates) === 0) {
    foreach ($FALLBACK_SYMBOLS as $b) {
        $candidates[] = strtoupper($b) . 'USDT';
        if (count($candidates) >= $MAX_COINS) break;
    }
}

// 4) BTC barometer (multi-TF)
$btcTrend = $trending['BTC'] ?? null;
$btcBlock = analyzeMultiTFForPair('BTCUSDT', $btcTrend);
$header = "📊 *BTC Barometer*\n\n" . $btcBlock . "\n\n";
echo $header . "\n";

// 5) analyze each candidate and build message
$blocks = [];
foreach ($candidates as $pair) {
    sleep_ms($REQUEST_DELAY_MS);
    $base = preg_replace('/USDT$/','',$pair);
    $trendData = $trending[$base] ?? null;
    $blocks[] = analyzeMultiTFForPair($pair, $trendData);
}

// final message
$finalMsg = $header . implode("\n\n", $blocks);

// dedupe
$hash = md5($finalMsg);
$last = @file_get_contents($LAST_HASH_FILE);
if ($last === $hash && !$SEND_ALWAYS) {
    echo "No change in signal. Not sending Telegram.\n\n";
    echo $finalMsg . "\n";
    exit;
}
@file_put_contents($LAST_HASH_FILE, $hash);

// send to telegram if configured
if ($TELEGRAM_BOT_TOKEN && $TELEGRAM_CHAT_ID) {
    $send = postTelegram($TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID, $finalMsg);
    echo $send ? "Sent to Telegram\n" : "Failed sending Telegram\n";
} else {
    echo "Telegram not configured — printing message\n";
}

echo $finalMsg . "\n";
