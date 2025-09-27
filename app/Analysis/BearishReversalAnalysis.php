<?php

namespace App\Analysis;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BearishReversalAnalysis implements AnalysisInterface
{
    private $exchangeRate;
    private $feePercentage;
    private $client;
    private $apiBaseUrl;

    public function __construct(float $exchangeRate = 15000, float $feePercentage = 0.1)
    {
        $this->exchangeRate = $exchangeRate;
        $this->feePercentage = $feePercentage;
        $this->client = new Client();
        $this->apiBaseUrl = 'https://api.binance.com/api/v3';
    }

    public function analyze(string $symbol, float $amount = 100): object
    {
        try {
            // Dapatkan data real-time dari Binance
            $priceData = $this->getBinanceData($symbol);

            if (empty($priceData)) {
                throw new \Exception("Failed to fetch data from Binance API");
            }

            // Dapatkan harga saat ini yang benar
            $currentPrice = $this->getCurrentPrice($symbol);

            // Analisis teknikal
            $analysis = $this->technicalAnalysis($priceData, $currentPrice);
            $riskAnalysis = $this->riskAnalysis($analysis, $amount, $currentPrice);

            // Collect all indicators data
            $indicators = [
                'rsi' => $analysis['rsi'] ?? 0,
                'macd_histogram' => $analysis['macd_histogram'] ?? 0,
                'volume_trend' => $analysis['volume_trend'] ?? 'neutral',
                'bollinger_upper' => $analysis['bollinger_bands']['upper'] ?? 0,
                'bollinger_middle' => $analysis['bollinger_bands']['middle'] ?? 0,
                'bollinger_lower' => $analysis['bollinger_bands']['lower'] ?? 0,
                'estimated_bottom' => $analysis['estimated_bottom'] ?? 0,
                'current_price' => $currentPrice
            ];

            return (object)[
                'title' => "Bearish Reversal Analysis for {$symbol}",
                'description' => $this->getDescription(),
                'signal' => $analysis['signal'],
                'confidence' => $analysis['confidence'],
                'entry' => $currentPrice, // Gunakan harga current, bukan dari analisis
                'stop_loss' => $analysis['stop_loss'],
                'take_profit' => $analysis['take_profit'],
                'risk_reward' => $analysis['risk_reward_ratio'],
                'fee' => $riskAnalysis['fee'],
                'potential_profit' => $riskAnalysis['potential_profit'],
                'potential_loss' => $riskAnalysis['potential_loss'],
                'indicators' => $indicators, // Add indicators data
                'timestamp' => time(),
                'success' => true,
                'current_price' => $currentPrice,
                'notes' => "Analisis real-time menggunakan data Binance API. Deteksi reversal bearish dengan RSI, Fibonacci, volume analysis, dan candlestick patterns."
            ];

        } catch (\Exception $e) {
            return (object)[
                'title' => "Analysis Error for {$symbol}",
                'description' => $this->getDescription(),
                'signal' => 'NEUTRAL',
                'confidence' => 0,
                'entry' => 0,
                'stop_loss' => 0,
                'take_profit' => 0,
                'risk_reward' => '0:0',
                'fee' => 0,
                'potential_profit' => 0,
                'potential_loss' => 0,
                'timestamp' => time(),
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getCode(): string
    {
        return 'bearish_reversal';
    }

    public function getName(): string
    {
        return 'Bearish Reversal Detection with Bottom Estimation (Binance API)';
    }

    public function getDescription(): string
    {
        return "Analisis real-time menggunakan data dari Binance API. "
             . "Metode: RSI oversold, Fibonacci retracement, volume analysis, "
             . "dan candlestick pattern detection untuk identifikasi reversal bearish.";
    }

    public function getIndicators(): array
    {
        return [
            'RSI_Period' => 14,
            'Fibonacci_Levels' => '0.236, 0.382, 0.5, 0.618, 0.786',
            'Volume_SMA' => 20,
            'MACD' => '12,26,9',
            'Bollinger_Bands' => '20,2',
            'Data_Points' => 100,
            'Timeframe' => '1h'
        ];
    }

    public function getNotes(): string
    {
        return "Data diambil langsung dari Binance API. "
             . "Pastikan koneksi internet stabil untuk analisis real-time. "
             . "Selalu gunakan risk management yang proper.";
    }

    private function getBinanceData(string $symbol, string $interval = '1h', int $limit = 100): array
    {
        $url = "{$this->apiBaseUrl}/klines";
        $params = [
            'symbol' => strtoupper($symbol),
            'interval' => $interval,
            'limit' => $limit
        ];

        try {
            $response = $this->client->get($url, ['query' => $params]);
            $data = json_decode($response->getBody(), true);

            $formattedData = [];
            foreach ($data as $candle) {
                $formattedData[] = [
                    'timestamp' => $candle[0],
                    'open' => (float)$candle[1],
                    'high' => (float)$candle[2],
                    'low' => (float)$candle[3],
                    'close' => (float)$candle[4],
                    'volume' => (float)$candle[5],
                    'close_time' => $candle[6],
                    'quote_volume' => (float)$candle[7]
                ];
            }

            return $formattedData;

        } catch (RequestException $e) {
            throw new \Exception("Binance API Error: " . $e->getMessage());
        }
    }

    private function getCurrentPrice(string $symbol): float
    {
        $url = "{$this->apiBaseUrl}/ticker/price";
        $params = ['symbol' => strtoupper($symbol)];

        try {
            $response = $this->client->get($url, ['query' => $params]);
            $data = json_decode($response->getBody(), true);
            return (float)$data['price'];
        } catch (RequestException $e) {
            // Fallback: ambil dari data klines terakhir
            $klines = $this->getBinanceData($symbol, '1h', 1);
            return $klines[0]['close'] ?? 0;
        }
    }

    private function technicalAnalysis(array $priceData, float $currentPrice): array
    {
        if (empty($priceData)) {
            return $this->getDefaultAnalysis($currentPrice);
        }

        $highs = array_column($priceData, 'high');
        $lows = array_column($priceData, 'low');
        $closes = array_column($priceData, 'close');
        $volumes = array_column($priceData, 'volume');

        // Calculate technical indicators
        $rsi = $this->calculateRSI($closes, 14);
        $macd = $this->calculateMACD($closes);
        $bollingerBands = $this->calculateBollingerBands($closes, 20, 2);
        $fibonacciLevels = $this->calculateFibonacciLevels($highs, $lows);
        $volumeAnalysis = $this->analyzeVolume($volumes);
        $supportLevels = $this->findSupportLevels($lows);
        $candlestickPattern = $this->detectCandlestickPatterns($priceData);

        // Bottom estimation dengan batasan realistis
        $estimatedBottom = $this->estimateBottom($lows, $fibonacciLevels, $supportLevels, $currentPrice);

        // Signal determination
        $signal = $this->determineSignal($rsi, $macd, $bollingerBands, $candlestickPattern, $volumeAnalysis, $currentPrice, $estimatedBottom);

        // Risk management dengan harga yang realistis
        $riskManagement = $this->calculateRiskManagement($currentPrice, $estimatedBottom, $signal, $bollingerBands);

        return array_merge($riskManagement, [
            'signal' => $signal,
            'confidence' => $this->calculateConfidence($rsi, $macd, $candlestickPattern, $volumeAnalysis, $bollingerBands, $currentPrice),
            'rsi' => $rsi,
            'macd_histogram' => $macd['histogram'] ?? 0,
            'bollinger_bands' => $bollingerBands,
            'fibonacci_levels' => $fibonacciLevels,
            'estimated_bottom' => $estimatedBottom,
            'volume_trend' => $volumeAnalysis['trend'],
            'candlestick_patterns' => $candlestickPattern
        ]);
    }

    private function getDefaultAnalysis(float $currentPrice): array
    {
        return [
            'signal' => 'NEUTRAL',
            'confidence' => 0,
            'entry_price' => $currentPrice,
            'stop_loss' => $currentPrice * 0.95,
            'take_profit' => $currentPrice * 1.05,
            'risk_reward_ratio' => '1:1',
            'rsi' => 50,
            'volume_trend' => 'neutral'
        ];
    }

    private function calculateRSI(array $closes, int $period): float
    {
        if (count($closes) < $period + 1) {
            return 50;
        }

        $changes = [];
        for ($i = 1; $i < count($closes); $i++) {
            $changes[] = $closes[$i] - $closes[$i - 1];
        }

        $gains = $losses = [];
        foreach ($changes as $change) {
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        // Calculate for the last period
        $recentGains = array_slice($gains, -$period);
        $recentLosses = array_slice($losses, -$period);

        $avgGain = array_sum($recentGains) / $period;
        $avgLoss = array_sum($recentLosses) / $period;

        if ($avgLoss == 0) return 100;

        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    private function calculateMACD(array $closes): array
    {
        if (count($closes) < 26) {
            return ['macd_line' => 0, 'signal_line' => 0, 'histogram' => 0];
        }

        $ema12 = $this->calculateEMA($closes, 12);
        $ema26 = $this->calculateEMA($closes, 26);

        // Pastikan array memiliki panjang yang sama
        $minLength = min(count($ema12), count($ema26));
        $ema12 = array_slice($ema12, -$minLength);
        $ema26 = array_slice($ema26, -$minLength);

        $macdLine = [];
        for ($i = 0; $i < $minLength; $i++) {
            $macdLine[] = $ema12[$i] - $ema26[$i];
        }

        $signalLine = $this->calculateEMA($macdLine, 9);

        // Ambil nilai terakhir
        $lastMacd = end($macdLine) ?? 0;
        $lastSignal = end($signalLine) ?? 0;

        return [
            'macd_line' => $lastMacd,
            'signal_line' => $lastSignal,
            'histogram' => $lastMacd - $lastSignal
        ];
    }

    private function calculateEMA(array $data, int $period): array
    {
        if (count($data) < $period) {
            return array_fill(0, count($data), 0);
        }

        $ema = [];
        $multiplier = 2 / ($period + 1);

        // SMA as first EMA value
        $sma = array_sum(array_slice($data, 0, $period)) / $period;
        $ema[] = $sma;

        for ($i = $period; $i < count($data); $i++) {
            $ema[] = ($data[$i] * $multiplier) + ($ema[count($ema)-1] * (1 - $multiplier));
        }

        return $ema;
    }

    private function calculateBollingerBands(array $closes, int $period = 20, float $multiplier = 2): array
    {
        if (count($closes) < $period) {
            $currentPrice = end($closes) ?? 0;
            return [
                'upper' => $currentPrice * 1.1,
                'middle' => $currentPrice,
                'lower' => $currentPrice * 0.9,
                'width' => 20
            ];
        }

        $slices = array_slice($closes, -$period);
        $sma = array_sum($slices) / $period;

        $variance = 0;
        foreach ($slices as $close) {
            $variance += pow($close - $sma, 2);
        }
        $stddev = sqrt($variance / $period);

        return [
            'upper' => $sma + ($stddev * $multiplier),
            'middle' => $sma,
            'lower' => $sma - ($stddev * $multiplier),
            'width' => ($stddev * $multiplier * 2) / $sma * 100
        ];
    }

    private function calculateFibonacciLevels(array $highs, array $lows): array
    {
        if (empty($highs) || empty($lows)) {
            return [];
        }

        $highestHigh = max($highs);
        $lowestLow = min($lows);
        $difference = $highestHigh - $lowestLow;

        if ($difference <= 0) {
            return [];
        }

        return [
            '0.236' => $highestHigh - ($difference * 0.236),
            '0.382' => $highestHigh - ($difference * 0.382),
            '0.5' => $highestHigh - ($difference * 0.5),
            '0.618' => $highestHigh - ($difference * 0.618),
            '0.786' => $highestHigh - ($difference * 0.786)
        ];
    }

    private function analyzeVolume(array $volumes): array
    {
        if (count($volumes) < 10) {
            return ['trend' => 'neutral', 'ratio' => 1.0];
        }

        $recentVolume = array_slice($volumes, -5);
        $previousVolume = array_slice($volumes, -10, 5);

        $avgRecent = array_sum($recentVolume) / count($recentVolume);
        $avgPrevious = array_sum($previousVolume) / count($previousVolume);

        $ratio = $avgPrevious > 0 ? $avgRecent / $avgPrevious : 1.0;

        if ($ratio > 1.2) return ['trend' => 'increasing', 'ratio' => $ratio];
        if ($ratio < 0.8) return ['trend' => 'decreasing', 'ratio' => $ratio];
        return ['trend' => 'neutral', 'ratio' => $ratio];
    }

    private function findSupportLevels(array $lows): array
    {
        if (count($lows) < 10) {
            return [];
        }

        $supportLevels = [];
        $window = 5;

        for ($i = $window; $i < count($lows) - $window; $i++) {
            $currentLow = $lows[$i];
            $leftMin = min(array_slice($lows, $i - $window, $window));
            $rightMin = min(array_slice($lows, $i + 1, $window));

            if ($currentLow <= $leftMin && $currentLow <= $rightMin) {
                $supportLevels[] = $currentLow;
            }
        }

        return array_slice(array_unique($supportLevels), -3);
    }

    private function detectCandlestickPatterns(array $priceData): array
    {
        $patterns = [];
        if (count($priceData) < 3) {
            return $patterns;
        }

        $recentData = array_slice($priceData, -3);

        // Check patterns dari yang terbaru
        if ($this->isHammerPattern(end($recentData))) {
            $patterns[] = 'hammer';
        }

        if (count($recentData) >= 2) {
            $prevCandle = $recentData[count($recentData)-2];
            $currentCandle = $recentData[count($recentData)-1];

            if ($this->isBullishEngulfing($prevCandle, $currentCandle)) {
                $patterns[] = 'bullish_engulfing';
            }
        }

        if (count($recentData) >= 3) {
            $first = $recentData[0];
            $second = $recentData[1];
            $third = $recentData[2];

            if ($this->isMorningStar($first, $second, $third)) {
                $patterns[] = 'morning_star';
            }
        }

        return $patterns;
    }

    private function isHammerPattern(array $candle): bool
    {
        $bodySize = abs($candle['open'] - $candle['close']);
        $totalRange = $candle['high'] - $candle['low'];

        if ($totalRange == 0) return false;

        $lowerShadow = min($candle['open'], $candle['close']) - $candle['low'];
        $bodyBottom = min($candle['open'], $candle['close']);
        $upperShadow = $candle['high'] - max($candle['open'], $candle['close']);

        return ($lowerShadow >= 2 * $bodySize) &&
               ($upperShadow <= $bodySize * 0.3) &&
               (($bodyBottom - $candle['low']) / $totalRange > 0.6);
    }

    private function isBullishEngulfing(array $prevCandle, array $currentCandle): bool
    {
        $prevBodySize = abs($prevCandle['close'] - $prevCandle['open']);
        $currentBodySize = abs($currentCandle['close'] - $currentCandle['open']);

        return $prevCandle['close'] < $prevCandle['open'] && // Previous bearish
               $currentCandle['close'] > $currentCandle['open'] && // Current bullish
               $currentBodySize > $prevBodySize * 1.2 && // Current body larger
               $currentCandle['open'] <= $prevCandle['close'] &&
               $currentCandle['close'] >= $prevCandle['open'];
    }

    private function isMorningStar(array $first, array $second, array $third): bool
    {
        $firstBearish = $first['close'] < $first['open'];
        $secondSmallBody = abs($second['close'] - $second['open']) <
                          (abs($first['close'] - $first['open']) * 0.3);
        $thirdBullish = $third['close'] > $third['open'];

        return $firstBearish && $secondSmallBody && $thirdBullish;
    }

    private function estimateBottom(array $lows, array $fibonacciLevels, array $supportLevels, float $currentPrice): float
    {
        if (empty($lows)) {
            return $currentPrice * 0.9;
        }

        $recentLow = min(array_slice($lows, -10));
        $estimates = [$recentLow * 0.99]; // Conservative estimate

        if (!empty($fibonacciLevels)) {
            $estimates[] = $fibonacciLevels['0.618'] ?? $recentLow;
        }

        if (!empty($supportLevels)) {
            $estimates[] = min($supportLevels);
        }

        $estimatedBottom = min($estimates);

        // Pastikan estimasi bottom realistis (tidak lebih dari 30% di bawah harga current)
        $maxDrop = $currentPrice * 0.7;
        return max($estimatedBottom, $maxDrop);
    }

    private function determineSignal(float $rsi, array $macd, array $bollingerBands, array $candlestickPatterns, array $volumeAnalysis, float $currentPrice, float $estimatedBottom): string
    {
        $buySignals = 0;
        $sellSignals = 0;

        // RSI condition
        if ($rsi < 30) $buySignals++;
        elseif ($rsi > 70) $sellSignals++;

        // MACD condition
        if ($macd['histogram'] > 0) $buySignals++;
        elseif ($macd['histogram'] < 0) $sellSignals++;

        // Bollinger Bands condition
        if ($currentPrice <= $bollingerBands['lower']) $buySignals++;
        elseif ($currentPrice >= $bollingerBands['upper']) $sellSignals++;

        // Candlestick patterns
        if (count($candlestickPatterns) > 0) $buySignals++;

        // Volume confirmation
        if ($volumeAnalysis['trend'] === 'increasing' && $volumeAnalysis['ratio'] > 1.2) $buySignals++;

        // Price position relative to estimated bottom
        if ($currentPrice <= $estimatedBottom * 1.05) $buySignals++;

        if ($buySignals >= 4) return 'BUY';
        if ($sellSignals >= 4) return 'SELL';

        return 'NEUTRAL';
    }

    private function calculateConfidence(float $rsi, array $macd, array $candlestickPatterns, array $volumeAnalysis, array $bollingerBands, float $currentPrice): float
    {
        $confidence = 50;

        // RSI contribution
        if ($rsi < 25) $confidence += 25;
        elseif ($rsi < 30) $confidence += 15;
        elseif ($rsi > 70) $confidence -= 20;

        // MACD contribution
        if ($macd['histogram'] > 0) $confidence += 10;

        // Bollinger Bands contribution
        if ($currentPrice < $bollingerBands['lower']) $confidence += 15;

        // Candlestick patterns contribution
        $confidence += (count($candlestickPatterns) * 8);

        // Volume contribution
        if ($volumeAnalysis['trend'] === 'increasing') {
            $confidence += ($volumeAnalysis['ratio'] > 1.5 ? 12 : 8);
        }

        return max(10, min($confidence, 90));
    }

    private function calculateRiskManagement(float $currentPrice, float $estimatedBottom, string $signal, array $bollingerBands): array
    {
        if ($signal !== 'BUY') {
            return [
                'entry_price' => $currentPrice,
                'stop_loss' => $currentPrice * 0.95,
                'take_profit' => $currentPrice * 1.08,
                'risk_reward_ratio' => '1:1.6'
            ];
        }

        // Gunakan yang lebih konservatif antara Bollinger Band lower dan estimated bottom
        $stopLoss = min($estimatedBottom * 0.98, $bollingerBands['lower'] * 0.98, $currentPrice * 0.93);

        // Pastikan stop loss tidak terlalu jauh
        $maxStopLossDistance = $currentPrice * 0.15; // Maksimal 15% loss
        $stopLoss = max($stopLoss, $currentPrice - $maxStopLossDistance);

        $risk = $currentPrice - $stopLoss;
        $takeProfit = $currentPrice + ($risk * 2); // 1:2 risk-reward

        return [
            'entry_price' => $currentPrice,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward_ratio' => '1:2'
        ];
    }

    private function riskAnalysis(array $analysis, float $amount, float $currentPrice): array
    {
        $stopLoss = $analysis['stop_loss'];
        $takeProfit = $analysis['take_profit'];

        if ($currentPrice <= 0) {
            return [
                'fee' => 0,
                'potential_profit' => 0,
                'potential_loss' => 0
            ];
        }

        $units = $amount / $currentPrice;
        $riskPerUnit = $currentPrice - $stopLoss;
        $rewardPerUnit = $takeProfit - $currentPrice;

        $fee = $amount * ($this->feePercentage / 100);

        return [
            'fee' => $fee,
            'potential_profit' => max(0, ($rewardPerUnit * $units) - ($fee * 2)),
            'potential_loss' => max(0, ($riskPerUnit * $units) + $fee)
        ];
    }
}