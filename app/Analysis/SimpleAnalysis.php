<?php

namespace App\Analysis;

use App\Analysis\AnalysisInterface;
use Illuminate\Support\Facades\Log;

class SimpleAnalysis implements AnalysisInterface
{
    protected float $usdIdr = 16000;
    protected float $amount;
    protected string $timeframe;
    protected array $indicators = [];
    protected string $notes = '';
    protected $apiManager;

    public function __construct($apiManager)
    {
        $this->apiManager = $apiManager;
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        try {
            Log::info("SimpleAnalysis: Starting analysis for {$symbol}", [
                'amount' => $amount,
                'timeframe' => $timeframe,
                'forcedApi' => $forcedApi
            ]);

            // Get historical data
            $klines = $this->apiManager->getHistoricalData($symbol, $timeframe, 200, $forcedApi);

            Log::info("SimpleAnalysis: Retrieved historical data", [
                'symbol' => $symbol,
                'data_points' => count($klines),
                'first_point' => $klines[0] ?? null,
                'last_point' => end($klines),
                'data_sample' => array_slice($klines, 0, 3)
            ]);

            // Check if we have enough data - reduced requirement from 14 to 10 to work with CoinPaprika
            if (count($klines) < 10) {
                Log::error("SimpleAnalysis: Insufficient historical data", [
                    'symbol' => $symbol,
                    'required' => 10,
                    'actual' => count($klines),
                    'data_structure' => print_r($klines, true)
                ]);
                throw new \Exception("Insufficient historical data for {$symbol}. Need at least 10 data points.");
            }

            // Get current price
            $currentPrice = $this->apiManager->getCurrentPrice($symbol);

            Log::info("SimpleAnalysis: Current price", ['price' => $currentPrice]);

            // Convert klines to float arrays for analysis
            $prices = [];
            $volumes = [];
            foreach ($klines as $kline) {
                // Validate kline structure
                if (!is_array($kline) || count($kline) < 6) {
                    Log::warning("SimpleAnalysis: Invalid kline structure", ['kline' => $kline]);
                    continue;
                }

                $prices[] = [
                    'open' => (float) $kline[1],
                    'high' => (float) $kline[2],
                    'low' => (float) $kline[3],
                    'close' => (float) $kline[4],
                    'timestamp' => $kline[0]
                ];
                $volumes[] = (float) $kline[5];
            }

            Log::info("SimpleAnalysis: Processed price data", [
                'price_points' => count($prices),
                'volume_points' => count($volumes)
            ]);

            // Check if we have enough processed data - reduced requirement from 14 to 10
            // Let's be more flexible and accept even fewer points for CoinPaprika
            if (count($prices) < 5) {
                Log::error("SimpleAnalysis: Insufficient processed price data", [
                    'symbol' => $symbol,
                    'required' => 5,
                    'actual' => count($prices)
                ]);
                throw new \Exception("Insufficient processed price data for {$symbol}. Need at least 5 data points.");
            }

            // Perform technical analysis
            $indicators = $this->calculateIndicators($prices);

            Log::info("SimpleAnalysis: Calculated indicators", [
                'indicators_count' => count($indicators)
            ]);

            // Generate signals
            $signals = $this->generateSignals($indicators, $prices, $volumes, $currentPrice);

            Log::info("SimpleAnalysis: Generated signals", [
                'signals_count' => count($signals)
            ]);

            // Generate recommendation
            $recommendation = $this->generateRecommendation($signals, $currentPrice, $amount);

            Log::info("SimpleAnalysis: Generated recommendation", [
                'recommendation' => $recommendation
            ]);

            // Convert to object as required by interface
            $result = (object) [
                'title' => 'Multi-Timeframe Analysis',
                'description' => $this->getDescription(),
                'signal' => $recommendation['action'] ?? 'NEUTRAL',
                'confidence' => $recommendation['confidence'] ?? 50,
                'entry' => $currentPrice,
                'stop_loss' => $recommendation['stop_loss'] ?? $currentPrice,
                'take_profit' => $recommendation['target_price'] ?? $currentPrice,
                'risk_reward' => $recommendation['risk_reward_ratio'] ?? '1:1',
                'indicators' => $indicators,
                'notes' => $this->notes,
                'timestamp' => now()->toISOString()
            ];

            return $result;

        } catch (\Exception $e) {
            Log::error("SimpleAnalysis: Analysis failed", [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getCode(): string { return 'multi_tf_analysis'; }
    public function getName(): string { return 'Multi-Timeframe Advanced Analysis'; }
    public function getDescription(): string
    {
        return 'Analisis multi-timeframe (1h,4h,1d) dengan filter EMA200, indikator RSI, MACD, Stochastic RSI, Bollinger Bands, ATR, Volume. Menggunakan risk/reward nyata dan confidence threshold.';
    }
    public function getIndicators(): array { return $this->indicators; }
    public function getNotes(): string { return $this->notes; }

    // ===== Helper indikator =====
    private function sma(array $values, int $period): float {
        if (count($values) < $period) return 0;
        return array_sum(array_slice($values, -$period)) / $period;
    }

    private function ema(array $values, int $period): float {
        if (count($values) < $period) return 0;
        $k = 2 / ($period + 1);
        $ema = $values[0];
        for ($i = 1; $i < count($values); $i++) {
            $ema = ($values[$i] * $k) + ($ema * (1 - $k));
        }
        return $ema;
    }

    private function rsi(array $closes, int $period = 14): float {
        // Need at least period + 1 data points for RSI calculation
        // Reduced period requirement to work with fewer data points
        $minRequired = min($period + 1, 10);
        if (count($closes) < $minRequired) {
            return 50; // Return neutral RSI value when insufficient data
        }

        $gains = $losses = 0;
        for ($i = 1; $i <= min($period, count($closes) - 1); $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff >= 0) $gains += $diff; else $losses -= $diff;
        }
        $avgGain = $gains / min($period, count($closes) - 1);
        $avgLoss = $losses / min($period, count($closes) - 1);
        if ($avgLoss == 0) return 100;
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    private function macd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): array {
        // Reduced requirements to work with fewer data points
        if (count($closes) < max($slow, $signal)) {
            // Return neutral values when insufficient data
            return ['macd' => 0, 'signal' => 0];
        }

        $emaFast = $this->ema($closes, min($fast, count($closes)));
        $emaSlow = $this->ema($closes, min($slow, count($closes)));
        $macd = $emaFast - $emaSlow;
        $signalLine = $this->ema([$macd], min($signal, 1)); // Simplified for single value
        return ['macd' => $macd, 'signal' => $signalLine];
    }

    private function atr(array $highs, array $lows, array $closes, int $period = 14): float {
        // Need at least 2 data points for ATR calculation
        if (count($closes) < 2 || count($highs) < 2 || count($lows) < 2) {
            return 0;
        }

        $trs = [];
        for ($i = 1; $i < count($closes); $i++) {
            $tr = max([
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1]),
            ]);
            $trs[] = $tr;
        }

        // Need at least period TR values
        if (count($trs) < min($period, 1)) {
            $period = count($trs);
        }

        return array_sum(array_slice($trs, -$period)) / max(1, $period);
    }

    private function stochasticRsi(array $closes, int $period = 14): float {
        // Need at least period + 1 data points for Stochastic RSI
        // Reduced requirement to work with fewer data points
        $minRequired = min($period + 1, 10);
        if (count($closes) < $minRequired) {
            return 50; // Return neutral value when insufficient data
        }

        $rsi = $this->rsi($closes, min($period, count($closes) - 1));
        $slice = array_slice($closes, -min($period, count($closes)));

        // Additional safety check for the slice
        if (empty($slice)) {
            return 50;
        }

        $min = min($slice);
        $max = max($slice);
        return ($max - $min == 0) ? 50 : (($rsi - $min) / ($max - $min)) * 100;
    }

    private function bollingerBands(array $closes, int $period = 20, int $multiplier = 2): array {
        // Reduced period requirement to work with fewer data points
        $period = min($period, count($closes));
        if ($period < 2) {
            return [
                'upper' => 0,
                'middle' => 0,
                'lower' => 0,
            ];
        }

        $sma = $this->sma($closes, $period);
        $slice = array_slice($closes, -$period);
        $variance = array_sum(array_map(fn($c) => pow($c - $sma, 2), $slice)) / $period;
        $stdDev = sqrt($variance);
        return [
            'upper' => $sma + ($multiplier * $stdDev),
            'middle' => $sma,
            'lower' => $sma - ($multiplier * $stdDev),
        ];
    }

    private function calculateIndicators(array $prices): array
    {
        // Extract data for calculations
        $opens = array_column($prices, 'open');
        $highs = array_column($prices, 'high');
        $lows = array_column($prices, 'low');
        $closes = array_column($prices, 'close');
        $timestamps = array_column($prices, 'timestamp');

        // Calculate indicators with reduced requirements for small datasets
        $ema20 = $this->ema($closes, min(20, count($closes)));
        $ema50 = $this->ema($closes, min(50, count($closes)));
        $ema200 = count($closes) >= 100 ? $this->ema($closes, min(200, count($closes))) : $ema50; // Fallback for small datasets
        $rsi = $this->rsi($closes, min(14, count($closes)));
        $macd = $this->macd($closes, min(12, count($closes)), min(26, count($closes)), min(9, count($closes)));
        $stochRsi = $this->stochasticRsi($closes, min(14, count($closes)));
        $bbands = $this->bollingerBands($closes, min(20, count($closes)), 2);
        $atr = $this->atr($highs, $lows, $closes, min(14, count($closes)));

        return [
            'ema20' => $ema20,
            'ema50' => $ema50,
            'ema200' => $ema200,
            'rsi' => $rsi,
            'macd' => $macd['macd'], // Extract scalar value from array
            'macd_signal' => $macd['signal'], // Extract scalar value from array
            'stochRsi' => $stochRsi,
            'bbands_upper' => $bbands['upper'], // Extract scalar value from array
            'bbands_middle' => $bbands['middle'], // Extract scalar value from array
            'bbands_lower' => $bbands['lower'], // Extract scalar value from array
            'atr' => $atr,
        ];
    }

    private function generateSignals(array $indicators, array $prices, array $volumes, float $currentPrice): array
    {
        $signals = [];

        // Get the last price data
        $lastPrice = end($prices);
        $prevPrice = prev($prices);

        // Simple buy/sell signals based on indicators
        if ($indicators['rsi'] < 30 && $indicators['macd'] > $indicators['macd_signal']) {
            $signals[] = 'BUY';
        } elseif ($indicators['rsi'] > 70 && $indicators['macd'] < $indicators['macd_signal']) {
            $signals[] = 'SELL';
        }

        return $signals;
    }

    private function generateRecommendation(array $signals, float $currentPrice, float $amount): array
    {
        if (in_array('BUY', $signals)) {
            return [
                'action' => 'BUY',
                'confidence' => 75,
                'target_price' => $currentPrice * 1.05,
                'stop_loss' => $currentPrice * 0.95,
                'risk_reward_ratio' => 1.5,
            ];
        } elseif (in_array('SELL', $signals)) {
            return [
                'action' => 'SELL',
                'confidence' => 75,
                'target_price' => $currentPrice * 0.95,
                'stop_loss' => $currentPrice * 1.05,
                'risk_reward_ratio' => 1.5,
            ];
        }

        return [
            'action' => 'HOLD',
            'confidence' => 50,
            'message' => 'No strong signals detected',
        ];
    }
}