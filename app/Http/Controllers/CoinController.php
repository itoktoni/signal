<?php

namespace App\Http\Controllers;

use App\Models\Coin;
use App\Traits\ControllerHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CoinController extends Controller
{
    use ControllerHelper;

    protected $model;

    public function __construct(Coin $model)
    {
        $this->model = $model;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return redirect()->route($this->module('getData'));
    }

    public function getData()
    {
        $perPage = request('perpage', 10);
        $data = $this->model->filter(request())->orderBy('coin_watch', 'DESC')->paginate($perPage);
        $data->appends(request()->query());

        return $this->views($this->module(), [
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function getCreate()
    {
        return $this->views($this->module());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function postCreate()
    {
        return $this->create(request()->all());
    }

    /**
     * Display the specified resource.
     */
    public function getShow($code)
    {
        $model = $this->model->find($code);
        return $this->views($this->module());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function getUpdate($code)
    {
        $code = request('coin_code', $code);

        $model = $this->model->find($code);

        // Available analyst methods
        $analystMethods = [
            'sniper_entry' => 'Sniper Entry Analysis',
            'basic' => 'Basic Analysis',
            'dynamic_rr' => 'Dynamic RR Analysis'
        ];

        // Get analysis method from query parameter
        $analystMethod = request('analyst', 'sniper_entry');

        // Validate analyst method
        if (!array_key_exists($analystMethod, $analystMethods)) {
            $analystMethod = 'sniper_entry';
        }

        // Perform analysis based on method
        try {
            if ($analystMethod === 'sniper_entry') {
                $analysis = $this->performSniperEntryAnalysis($model);
            } elseif ($analystMethod === 'dynamic_rr') {
                $analysis = $this->performDynamicRRAnalysis($model);
            } else {
                $analysis = $this->performBasicAnalysis($model);
            }
        } catch (\Exception $e) {
            // Log the error and return error response
            Log::error('Crypto analysis failed', [
                'coin_code' => $model->coin_code ?? 'Unknown',
                'analyst_method' => $analystMethod,
                'error' => $e->getMessage()
            ]);

            $analysis = [
                'error' => $e->getMessage(),
                'symbol' => $model->coin_code ?? 'Unknown',
                'current_price' => null,
                'analysis' => null
            ];
        }

        $coin = Coin::getOptions('coin_code', 'coin_code');

        return $this->views($this->module(), $this->share([
            'model' => $model,
            'coin' => $coin->toArray(),
            'crypto_analysis' => $analysis,
            'analyst_method' => $analystMethod,
            'analyst_methods' => $analystMethods,
        ]));
    }

    /**
     * Perform crypto analysis for the given coin model
     */
    private function performCryptoAnalysis($model)
    {
        try {
            $symbol = $model->coin_code ?? 'BTCUSDT';

            // Get current price using BinanceService
            $binanceService = new \App\Services\BinanceService();
            $priceData = $binanceService->getTickerPriceWithFallback($symbol);

            if (!$priceData || !isset($priceData['price'])) {
                throw new \Exception('Unable to fetch current price data for ' . $symbol);
            }

            $currentPrice = (float) $priceData['price'];

            // Get candle data for analysis
            $candles = $binanceService->fetchKlines($symbol, '1h', 100);

            if (!$candles || empty($candles)) {
                throw new \Exception('Unable to fetch candle data for ' . $symbol . '. Binance API may be unavailable.');
            }

            // Perform technical analysis
            $analysis = $this->performTechnicalAnalysis($currentPrice, $candles);

            return [
                'symbol' => $symbol,
                'current_price' => $currentPrice,
                'analysis' => $analysis,
                'price_data' => $priceData,
                'last_updated' => now()->format('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            throw new \Exception('Analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform technical analysis on price and candle data
     */
    private function performTechnicalAnalysis($price, $candles)
    {
        if (empty($candles) || count($candles) < 20) {
            return [
                'signal' => 'INSUFFICIENT_DATA',
                'confidence' => 0,
                'entry' => $price,
                'stop_loss' => null,
                'take_profit' => null,
                'rr_ratio' => null
            ];
        }

        // Extract closing prices for analysis
        $closes = array_map(fn($c) => (float)$c[4], $candles);

        // Calculate EMA (20-period)
        $ema20 = array_sum(array_slice($closes, -20)) / 20;

        // Calculate RSI (14-period)
        $rsi = $this->calculateRSI($closes, 14);

        // Get support and resistance levels
        $highs = array_map(fn($c) => (float)$c[2], $candles);
        $lows = array_map(fn($c) => (float)$c[3], $candles);

        $resistance = max(array_slice($highs, -20));
        $support = min(array_slice($lows, -20));

        // Determine signal
        $signal = 'NEUTRAL';
        $confidence = 50;

        if ($price > $ema20 && $rsi > 50) {
            $signal = 'BUY';
            $confidence = $rsi > 60 ? 75 : 60;
        } elseif ($price < $ema20 && $rsi < 50) {
            $signal = 'SELL';
            $confidence = $rsi < 40 ? 75 : 60;
        }

        // Calculate entry, stop loss, and take profit
        $entry = $price;
        $stopLoss = $signal === 'BUY' ? $support : $resistance;

        // Calculate take profit with proper distance from entry
        if ($signal === 'BUY') {
            // For BUY signal: Take profit should be above entry
            $distanceToResistance = abs($resistance - $entry);
            $distanceToSupport = abs($entry - $support);

            if ($distanceToResistance > 0) {
                // Price is below resistance - use resistance as target
                $takeProfit = $resistance;
            } else {
                // Price is at or above resistance - calculate target based on risk
                $riskAmount = abs($entry - $stopLoss);
                $takeProfit = $entry + ($riskAmount * 2); // 2:1 reward ratio
            }
        } else {
            // For SELL signal: Take profit should be below entry
            $distanceToSupport = abs($entry - $support);
            $distanceToResistance = abs($resistance - $entry);

            if ($distanceToSupport > 0) {
                // Price is above support - use support as target
                $takeProfit = $support;
            } else {
                // Price is at or below support - calculate target based on risk
                $riskAmount = abs($entry - $stopLoss);
                $takeProfit = $entry - ($riskAmount * 2); // 2:1 reward ratio
            }
        }

        // Ensure take profit is different from entry
        if (abs($takeProfit - $entry) < ($entry * 0.001)) { // Less than 0.1% difference
            $riskAmount = abs($entry - $stopLoss);
            if ($signal === 'BUY') {
                $takeProfit = $entry + ($riskAmount * 1.5); // At least 1.5:1 ratio
            } else {
                $takeProfit = $entry - ($riskAmount * 1.5); // At least 1.5:1 ratio
            }
        }

        // Calculate risk-reward ratio
        $risk = abs($entry - $stopLoss);
        $reward = abs($takeProfit - $entry);
        $rrRatio = $risk > 0 ? round($reward / $risk, 2) : null;

        return [
            'signal' => $signal,
            'confidence' => $confidence,
            'entry' => round($entry, 8), // Keep more decimals for crypto
            'stop_loss' => $stopLoss ? round($stopLoss, 8) : null,
            'take_profit' => $takeProfit ? round($takeProfit, 8) : null,
            'rr_ratio' => $rrRatio,
            'indicators' => [
                'ema20' => round($ema20, 8),
                'rsi' => round($rsi, 2),
                'support' => round($support, 8),
                'resistance' => round($resistance, 8)
            ]
        ];
    }

    /**
     * Calculate RSI (Relative Strength Index)
     */
    private function calculateRSI($closes, $period = 14)
    {
        if (count($closes) < $period + 1) {
            return 50; // Default neutral RSI
        }

        $gains = 0;
        $losses = 0;

        for ($i = count($closes) - $period; $i < count($closes) - 1; $i++) {
            $change = $closes[$i + 1] - $closes[$i];
            if ($change > 0) {
                $gains += $change;
            } else {
                $losses -= $change; // Make positive for calculation
            }
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        if ($avgLoss == 0) {
            return 100; // Perfect upward trend
        }

        $rs = $avgGain / $avgLoss;
        return round(100 - (100 / (1 + $rs)), 2);
    }

    /**
     * Generate demo candle data when API is unavailable
     */
    private function getDemoCandles($symbol, $interval, $limit = 100)
    {
        $demoPrices = [
            'BTCUSDT' => 60000,
            'ETHUSDT' => 3000,
            'BNBUSDT' => 400,
            'ADAUSDT' => 0.35,
            'SOLUSDT' => 150,
            'HYPEUSDT' => 0.00000123,
        ];

        $basePrice = $demoPrices[strtoupper($symbol)] ?? 1.0;
        $candles = [];

        $currentTime = now()->timestamp * 1000;
        $intervalMs = $this->getIntervalMs($interval);

        for ($i = $limit; $i > 0; $i--) {
            $openTime = $currentTime - ($i * $intervalMs);
            $price = (float) $basePrice + (mt_rand(-1000, 1000) / 100);
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

    /**
     * Get interval in milliseconds
     */
    private function getIntervalMs($interval)
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

        return $intervals[$interval] ?? 60 * 60 * 1000; // Default to 1h
    }

    /**
     * Perform sniper entry analysis for optimal entry points
     */
    private function performSniperEntryAnalysis($model)
    {
        try {
            $symbol = $model->coin_code ?? 'BTCUSDT';

            // Get current price using BinanceService
            $binanceService = new \App\Services\BinanceService();
            $priceData = $binanceService->getTickerPriceWithFallback($symbol);

            if (!$priceData || !isset($priceData['price'])) {
                throw new \Exception('Unable to fetch current price data for ' . $symbol);
            }

            $currentPrice = (float) $priceData['price'];

            // Get candle data for analysis (use 4h for sniper entry)
            $candles = $binanceService->fetchKlines($symbol, '4h', 200);

            if (!$candles || empty($candles)) {
                throw new \Exception('Unable to fetch candle data for ' . $symbol . '. Binance API may be unavailable.');
            }

            // Perform sniper entry analysis
            $analysis = $this->performSniperTechnicalAnalysis($currentPrice, $candles);

            return [
                'symbol' => $symbol,
                'current_price' => $currentPrice,
                'analysis' => $analysis,
                'price_data' => $priceData,
                'last_updated' => now()->format('Y-m-d H:i:s'),
                'analysis_type' => 'sniper_entry'
            ];

        } catch (\Exception $e) {
            throw new \Exception('Sniper analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform basic analysis (simplified version)
     */
    private function performBasicAnalysis($model)
    {
        try {
            $symbol = $model->coin_code ?? 'BTCUSDT';

            // Get current price using BinanceService
            $binanceService = new \App\Services\BinanceService();
            $priceData = $binanceService->getTickerPriceWithFallback($symbol);

            if (!$priceData || !isset($priceData['price'])) {
                throw new \Exception('Unable to fetch current price data for ' . $symbol);
            }

            $currentPrice = (float) $priceData['price'];

            // Get candle data for analysis
            $candles = $binanceService->fetchKlines($symbol, '1h', 50);

            if (!$candles || empty($candles)) {
                throw new \Exception('Unable to fetch candle data for ' . $symbol . '. Binance API may be unavailable.');
            }

            // Perform basic technical analysis
            $analysis = $this->performBasicTechnicalAnalysis($currentPrice, $candles);

            return [
                'symbol' => $symbol,
                'current_price' => $currentPrice,
                'analysis' => $analysis,
                'price_data' => $priceData,
                'last_updated' => now()->format('Y-m-d H:i:s'),
                'analysis_type' => 'basic'
            ];

        } catch (\Exception $e) {
            throw new \Exception('Basic analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform sniper technical analysis for optimal entry points
     */
    private function performSniperTechnicalAnalysis($price, $candles)
    {
        if (empty($candles) || count($candles) < 50) {
            return [
                'signal' => 'INSUFFICIENT_DATA',
                'confidence' => 0,
                'entry' => $price,
                'stop_loss' => null,
                'take_profit' => null,
                'rr_ratio' => null
            ];
        }

        // Extract closing prices for analysis
        $closes = array_map(fn($c) => (float)$c[4], $candles);

        // Calculate multiple EMAs for sniper entry
        $ema9 = array_sum(array_slice($closes, -9)) / 9;
        $ema21 = array_sum(array_slice($closes, -21)) / 21;
        $ema50 = array_sum(array_slice($closes, -50)) / 50;

        // Calculate RSI (14-period)
        $rsi = $this->calculateRSI($closes, 14);

        // Get support and resistance levels from recent candles
        $recentCandles = array_slice($candles, -20);
        $highs = array_map(fn($c) => (float)$c[2], $recentCandles);
        $lows = array_map(fn($c) => (float)$c[3], $recentCandles);

        $resistance = max($highs);
        $support = min($lows);

        // Sniper entry logic - look for optimal entry points
        $signal = 'NEUTRAL';
        $confidence = 50;

        // Enhanced Sniper BUY conditions (LONG)
        $buyConditions = 0;
        if ($price > $ema9) $buyConditions++;
        if ($ema9 > $ema21) $buyConditions++;
        if ($ema21 > $ema50) $buyConditions++;
        if ($rsi > 45 && $rsi < 75) $buyConditions++; // More flexible RSI range
        if ($price > $support) $buyConditions++; // Price above support

        // Enhanced Sniper SELL conditions (SHORT)
        $sellConditions = 0;
        if ($price < $ema9) $sellConditions++;
        if ($ema9 < $ema21) $sellConditions++;
        if ($ema21 < $ema50) $sellConditions++;
        if ($rsi < 55 && $rsi > 25) $sellConditions++; // More flexible RSI range
        if ($price < $resistance) $sellConditions++; // Price below resistance

        // Determine signal based on conditions met
        if ($buyConditions >= 4) {
            $signal = 'BUY';
            $confidence = 75 + ($buyConditions * 5); // 75-95% confidence
        } elseif ($sellConditions >= 4) {
            $signal = 'SELL';
            $confidence = 75 + ($sellConditions * 5); // 75-95% confidence
        } elseif ($buyConditions >= 3) {
            $signal = 'BUY';
            $confidence = 60 + ($buyConditions * 3); // 60-75% confidence
        } elseif ($sellConditions >= 3) {
            $signal = 'SELL';
            $confidence = 60 + ($sellConditions * 3); // 60-75% confidence
        }

        // Calculate optimal entry, stop loss, and take profit
        $entry = $price;

        // For BUY: Stop loss below recent support, take profit at resistance or calculated target
        if ($signal === 'BUY') {
            $stopLoss = $support * 0.995; // 0.5% below support

            // Look for the next resistance level or calculate target
            $distanceToResistance = abs($resistance - $entry);
            if ($distanceToResistance > 0 && $distanceToResistance < ($entry * 0.05)) {
                // Resistance is close - use it as target
                $takeProfit = $resistance * 1.002; // Slightly above resistance
            } else {
                // Calculate target based on risk
                $riskAmount = abs($entry - $stopLoss);
                $takeProfit = $entry + ($riskAmount * 3); // 3:1 reward ratio for sniper
            }
        } else {
            // For SELL: Stop loss above recent resistance, take profit at support or calculated target
            $stopLoss = $resistance * 1.005; // 0.5% above resistance

            $distanceToSupport = abs($entry - $support);
            if ($distanceToSupport > 0 && $distanceToSupport < ($entry * 0.05)) {
                // Support is close - use it as target
                $takeProfit = $support * 0.998; // Slightly below support
            } else {
                // Calculate target based on risk
                $riskAmount = abs($entry - $stopLoss);
                $takeProfit = $entry - ($riskAmount * 3); // 3:1 reward ratio for sniper
            }
        }

        // Ensure take profit is different from entry
        if (abs($takeProfit - $entry) < ($entry * 0.001)) { // Less than 0.1% difference
            $riskAmount = abs($entry - $stopLoss);
            if ($signal === 'BUY') {
                $takeProfit = $entry + ($riskAmount * 2); // At least 2:1 ratio
            } else {
                $takeProfit = $entry - ($riskAmount * 2); // At least 2:1 ratio
            }
        }

        // Calculate risk-reward ratio
        $risk = abs($entry - $stopLoss);
        $reward = abs($takeProfit - $entry);
        $rrRatio = $risk > 0 ? round($reward / $risk, 2) : null;

        return [
            'signal' => $signal,
            'confidence' => $confidence,
            'entry' => round($entry, 8),
            'stop_loss' => $stopLoss ? round($stopLoss, 8) : null,
            'take_profit' => $takeProfit ? round($takeProfit, 8) : null,
            'rr_ratio' => $rrRatio,
            'indicators' => [
                'ema9' => round($ema9, 8),
                'ema21' => round($ema21, 8),
                'ema50' => round($ema50, 8),
                'rsi' => round($rsi, 2),
                'support' => round($support, 8),
                'resistance' => round($resistance, 8)
            ]
        ];
    }

    /**
     * Perform basic technical analysis (simplified)
     */
    private function performBasicTechnicalAnalysis($price, $candles)
    {
        if (empty($candles) || count($candles) < 20) {
            return [
                'signal' => 'INSUFFICIENT_DATA',
                'confidence' => 0,
                'entry' => $price,
                'stop_loss' => null,
                'take_profit' => null,
                'rr_ratio' => null
            ];
        }

        // Extract closing prices for analysis
        $closes = array_map(fn($c) => (float)$c[4], $candles);

        // Calculate EMA (20-period)
        $ema20 = array_sum(array_slice($closes, -20)) / 20;

        // Calculate RSI (14-period)
        $rsi = $this->calculateRSI($closes, 14);

        // Get support and resistance levels
        $highs = array_map(fn($c) => (float)$c[2], $candles);
        $lows = array_map(fn($c) => (float)$c[3], $candles);

        $resistance = max(array_slice($highs, -20));
        $support = min(array_slice($lows, -20));

        // Determine signal
        $signal = 'NEUTRAL';
        $confidence = 50;

        if ($price > $ema20 && $rsi > 50) {
            $signal = 'BUY';
            $confidence = $rsi > 60 ? 75 : 60;
        } elseif ($price < $ema20 && $rsi < 50) {
            $signal = 'SELL';
            $confidence = $rsi < 40 ? 75 : 60;
        }

        // Calculate entry, stop loss, and take profit
        $entry = $price;
        $stopLoss = $signal === 'BUY' ? $support : $resistance;

        // Calculate take profit with proper distance from entry
        if ($signal === 'BUY') {
            $distanceToResistance = abs($resistance - $entry);
            if ($distanceToResistance > 0) {
                $takeProfit = $resistance;
            } else {
                $riskAmount = abs($entry - $stopLoss);
                $takeProfit = $entry + ($riskAmount * 2);
            }
        } else {
            $distanceToSupport = abs($entry - $support);
            if ($distanceToSupport > 0) {
                $takeProfit = $support;
            } else {
                $riskAmount = abs($entry - $stopLoss);
                $takeProfit = $entry - ($riskAmount * 2);
            }
        }

        // Ensure take profit is different from entry
        if (abs($takeProfit - $entry) < ($entry * 0.001)) {
            $riskAmount = abs($entry - $stopLoss);
            if ($signal === 'BUY') {
                $takeProfit = $entry + ($riskAmount * 1.5);
            } else {
                $takeProfit = $entry - ($riskAmount * 1.5);
            }
        }

        // Calculate risk-reward ratio
        $risk = abs($entry - $stopLoss);
        $reward = abs($takeProfit - $entry);
        $rrRatio = $risk > 0 ? round($reward / $risk, 2) : null;

        return [
            'signal' => $signal,
            'confidence' => $confidence,
            'entry' => round($entry, 8),
            'stop_loss' => $stopLoss ? round($stopLoss, 8) : null,
            'take_profit' => $takeProfit ? round($takeProfit, 8) : null,
            'rr_ratio' => $rrRatio,
            'indicators' => [
                'ema20' => round($ema20, 8),
                'rsi' => round($rsi, 2),
                'support' => round($support, 8),
                'resistance' => round($resistance, 8)
            ]
        ];
    }

    /**
     * Perform dynamic RR analysis with Fibonacci and support/resistance levels
     */
    private function performDynamicRRAnalysis($model)
    {
        try {
            $symbol = $model->coin_code ?? 'BTCUSDT';

            // Get current price using BinanceService
            $binanceService = new \App\Services\BinanceService();
            $priceData = $binanceService->getTickerPriceWithFallback($symbol);

            if (!$priceData || !isset($priceData['price'])) {
                throw new \Exception('Unable to fetch current price data for ' . $symbol);
            }

            $currentPrice = (float) $priceData['price'];

            // Get candle data for analysis (use 1h for dynamic RR)
            $candles = $binanceService->fetchKlines($symbol, '1h', 100);

            if (!$candles || empty($candles)) {
                throw new \Exception('Unable to fetch candle data for ' . $symbol . '. Binance API may be unavailable.');
            }

            // Perform dynamic RR analysis
            $analysis = $this->performDynamicRRTechnicalAnalysis($currentPrice, $candles);

            return [
                'symbol' => $symbol,
                'current_price' => $currentPrice,
                'analysis' => $analysis,
                'price_data' => $priceData,
                'last_updated' => now()->format('Y-m-d H:i:s'),
                'analysis_type' => 'dynamic_rr'
            ];

        } catch (\Exception $e) {
            throw new \Exception('Dynamic RR analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Perform dynamic RR technical analysis with Fibonacci and support/resistance levels
     */
    private function performDynamicRRTechnicalAnalysis($price, $candles)
    {
        if (empty($candles) || count($candles) < 50) {
            return [
                'signal' => 'INSUFFICIENT_DATA',
                'confidence' => 0,
                'entry' => $price,
                'stop_loss' => null,
                'take_profit' => null,
                'rr_ratio' => null
            ];
        }

        // Extract closing prices for analysis
        $closes = array_map(fn($c) => (float)$c[4], $candles);
        $highs = array_map(fn($c) => (float)$c[2], $candles);
        $lows = array_map(fn($c) => (float)$c[3], $candles);

        // Calculate EMAs
        $ema20 = array_sum(array_slice($closes, -20)) / 20;
        $ema50 = array_sum(array_slice($closes, -50)) / 50;

        // Calculate RSI (14-period)
        $rsi = $this->calculateRSI($closes, 14);

        // Get support and resistance levels from recent candles
        $recentCandles = array_slice($candles, -30);
        $recentHighs = array_map(fn($c) => (float)$c[2], $recentCandles);
        $recentLows = array_map(fn($c) => (float)$c[3], $recentCandles);

        $resistance = max($recentHighs);
        $support = min($recentLows);

        // Calculate ATR for volatility
        $atr = $this->calculateATR($candles, 14);

        // Calculate Fibonacci levels
        $fibLevels = $this->calculateFibonacciLevels($support, $resistance);

        // Determine signal
        $signal = 'NEUTRAL';
        $confidence = 50;

        // BUY conditions
        $buyConditions = 0;
        if ($price > $ema20 && $price > $ema50) $buyConditions++;
        if ($rsi > 40 && $rsi < 60) $buyConditions++; // RSI in middle range
        if ($price > $support) $buyConditions++; // Price above support
        if ($price < $ema50 * 1.02) $buyConditions++; // Not too far above EMA50

        // SELL conditions
        $sellConditions = 0;
        if ($price < $ema20 && $price < $ema50) $sellConditions++;
        if ($rsi > 40 && $rsi < 60) $sellConditions++; // RSI in middle range
        if ($price < $resistance) $sellConditions++; // Price below resistance
        if ($price > $ema50 * 0.98) $sellConditions++; // Not too far below EMA50

        // Determine signal based on conditions met
        if ($buyConditions >= 3) {
            $signal = 'BUY';
            $confidence = 65 + ($buyConditions * 5); // 65-85% confidence
        } elseif ($sellConditions >= 3) {
            $signal = 'SELL';
            $confidence = 65 + ($sellConditions * 5); // 65-85% confidence
        }

        // Calculate entry, stop loss, and take profit with dynamic RR
        $entry = $price;

        // Calculate stop loss using ATR
        if ($signal === 'BUY') {
            $stopLoss = $entry - ($atr * 1.5); // Stop loss below entry using ATR
        } else {
            $stopLoss = $entry + ($atr * 1.5); // Stop loss above entry using ATR
        }

        // Calculate dynamic take profit using Fibonacci levels or support/resistance
        $takeProfit = $this->calculateDynamicTakeProfit($entry, $stopLoss, $fibLevels, $support, $resistance, $signal);

        // Ensure take profit is different from entry
        if (abs($takeProfit - $entry) < ($entry * 0.001)) { // Less than 0.1% difference
            $riskAmount = abs($entry - $stopLoss);
            if ($signal === 'BUY') {
                $takeProfit = $entry + ($riskAmount * 1.5); // At least 1.5:1 ratio
            } else {
                $takeProfit = $entry - ($riskAmount * 1.5); // At least 1.5:1 ratio
            }
        }

        // Calculate risk-reward ratio
        $risk = abs($entry - $stopLoss);
        $reward = abs($takeProfit - $entry);
        $rrRatio = $risk > 0 ? round($reward / $risk, 2) : null;

        return [
            'signal' => $signal,
            'confidence' => min(100, $confidence), // Cap at 100%
            'entry' => round($entry, 8),
            'stop_loss' => $stopLoss ? round($stopLoss, 8) : null,
            'take_profit' => $takeProfit ? round($takeProfit, 8) : null,
            'rr_ratio' => $rrRatio,
            'indicators' => [
                'ema20' => round($ema20, 8),
                'ema50' => round($ema50, 8),
                'rsi' => round($rsi, 2),
                'atr' => round($atr, 8),
                'support' => round($support, 8),
                'resistance' => round($resistance, 8)
            ]
        ];
    }

    /**
     * Calculate ATR (Average True Range) for volatility
     */
    private function calculateATR($candles, $period = 14)
    {
        if (count($candles) < $period + 1) {
            return 0;
        }

        $trs = [];
        for ($i = 1; $i < count($candles); $i++) {
            $high = (float)$candles[$i][2];
            $low = (float)$candles[$i][3];
            $prevClose = (float)$candles[$i-1][4];

            $tr = max(
                $high - $low,           // High - Low
                abs($high - $prevClose), // |High - Previous Close|
                abs($low - $prevClose)   // |Low - Previous Close|
            );

            $trs[] = $tr;
        }

        // Calculate simple moving average of TRs
        $slice = array_slice($trs, -$period);
        return array_sum($slice) / count($slice);
    }

    /**
     * Calculate Fibonacci retracement levels
     */
    private function calculateFibonacciLevels($low, $high)
    {
        $range = $high - $low;

        return [
            '23.6%' => $high - ($range * 0.236),
            '38.2%' => $high - ($range * 0.382),
            '50.0%' => $high - ($range * 0.5),
            '61.8%' => $high - ($range * 0.618),
            '78.6%' => $high - ($range * 0.786),
        ];
    }

    /**
     * Calculate dynamic take profit based on Fibonacci levels or support/resistance
     */
    private function calculateDynamicTakeProfit($entry, $stopLoss, $fibLevels, $support, $resistance, $signal)
    {
        // Calculate minimum risk
        $risk = abs($entry - $stopLoss);

        // Try to find a suitable Fibonacci level that gives at least 1.5:1 RR
        if (!empty($fibLevels)) {
            foreach ($fibLevels as $ratio => $level) {
                $reward = abs($entry - $level);
                $rr = $risk > 0 ? $reward / $risk : 0;

                // If this level gives us at least 1.5:1 RR, use it
                if ($rr >= 1.5) {
                    if ($signal === 'BUY' && $level > $entry) {
                        return $level;
                    } elseif ($signal === 'SELL' && $level < $entry) {
                        return $level;
                    }
                }
            }
        }

        // Fallback to support/resistance levels with minimum 1.5:1 RR
        if ($signal === 'BUY') {
            // For BUY signals, look for resistance or calculate target
            $minReward = $risk * 1.5;
            if ($resistance > $entry && abs($resistance - $entry) >= $minReward) {
                return $resistance;
            } else {
                return $entry + $minReward;
            }
        } else {
            // For SELL signals, look for support or calculate target
            $minReward = $risk * 1.5;
            if ($support < $entry && abs($entry - $support) >= $minReward) {
                return $support;
            } else {
                return $entry - $minReward;
            }
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function getWatch($code)
    {
        $model = $this->model->find($code);
        $model->coin_watch = !$model->coin_watch;
        $model->save();

        return redirect()->route($this->module('getData'))->with('success', 'updated successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function postUpdate(Request $request)
    {
        return $this->update($request->all(), $this->model);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function getDelete($code)
    {
        $model = $this->model->find($code);
        $model->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }

    public function postDelete($code)
    {
        $model = $this->model->find($code);
        $model->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }

    public function getProfile()
    {
        return view($this->module());
    }

    public function getSecurity()
    {
        return view($this->module());
    }

    public function postBulkDelete(Request $request)
    {
        $ids = explode(',', $request->ids);
        $this->model->whereIn('id', $ids)->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }
}
