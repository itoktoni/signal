<?php

namespace App\Analysis;

use App\Analysis\AnalysisService;
use App\Enums\AnalysisType;
use App\Enums\TimeIntervalType;
use App\Enums\SignalType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SniperService extends AnalysisService
{
    public function getName(): string
    {
        return AnalysisType::SNIPER;
    }

    public function analyze(string $symbol, float $amount = 100): object
    {
        // Get market data
        $klines = $this->getKlines($symbol, TimeIntervalType::ONE_HOUR, 100);
        if (!$klines) {
            throw new \Exception("Failed to fetch market data for {$symbol}");
        }

        // Calculate indicators
        $indicators = $this->calculateIndicators($klines);

        // Determine signal based on sniper strategy
        $signal = $this->determineSignal($indicators);

        // Calculate entry, stop loss, and take profit levels
        $levels = $this->calculateLevels($klines, $signal);

        // Calculate confidence based on multiple factors
        $confidence = $this->calculateConfidence($indicators, $signal);

        // Calculate risk-reward ratio
        $riskReward = $this->calculateRiskReward($levels['entry'], $levels['stop_loss'], $levels['take_profit']);

        // Calculate additional indicators for display
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $ema50 = $this->calculateEMA($closes, 50);

        // For support and resistance, we'll use the levels from the calculated levels
        // or derive them from recent price action
        $support = $levels['stop_loss']; // Using stop loss as support for sniper
        $resistance = $levels['take_profit']; // Using take profit as resistance for sniper

        $displayIndicators = [
            'ema9' => $indicators['ema9'],
            'ema21' => $indicators['ema21'],
            'ema50' => $ema50,
            'rsi' => $indicators['rsi'],
            'current_price' => $indicators['current_price'],
            'support' => $support,
            'resistance' => $resistance
        ];

        // Format the result with dynamic amount
        return $this->formatResult(
            "Sniper Analysis for {$symbol}",
            $signal,
            $confidence,
            $levels['entry'],
            $levels['stop_loss'],
            $levels['take_profit'],
            $levels['risk_reward'] !== '1:1' ? floatval(str_replace('1:', '', $levels['risk_reward'])) : 1.0,
            $amount, // dynamic position size in USD
            'sniper', // analyst method
            $displayIndicators // pass indicators
        );
    }

    /**
     * Get klines data from Binance
     */
    private function getKlines(string $symbol, string $interval, int $limit): ?array
    {
        $binanceApi = env('BINANCE_API', 'https://data-api.binance.vision');
        $url = "{$binanceApi}/api/v3/klines?symbol={$symbol}&interval={$interval}&limit={$limit}";

        $response = Http::withoutVerifying()->timeout(15)->get($url);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    /**
     * Get current market price from Binance API
     */
    private function getCurrentMarketPrice(string $symbol): ?float
    {
        try {
            $binanceApi = env('BINANCE_API', 'https://data-api.binance.vision');
            $url = "{$binanceApi}/api/v3/ticker/price?symbol={$symbol}";

            $response = Http::withoutVerifying()->timeout(10)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return floatval($data['price'] ?? 0);
            }
        } catch (\Exception $e) {
            // Log error but don't throw exception
            Log::warning("Failed to get current market price for {$symbol}", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Calculate technical indicators for sniper strategy
     */
    private function calculateIndicators(array $klines): array
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $highs = array_map(fn($k) => floatval($k[2]), $klines);
        $lows = array_map(fn($k) => floatval($k[3]), $klines);
        $volumes = array_map(fn($k) => floatval($k[5]), $klines);

        // Calculate RSI
        $rsi = $this->calculateRSI($closes, 14);

        // Calculate EMA
        $ema9 = $this->calculateEMA($closes, 9);
        $ema21 = $this->calculateEMA($closes, 21);

        // Calculate volume profile
        $avgVolume = array_sum($volumes) / count($volumes);
        $currentVolume = end($volumes);
        $volumeRatio = $currentVolume / $avgVolume;

        // Calculate price action signals
        $currentPrice = end($closes);
        $prevPrice = $closes[count($closes) - 2];
        $priceChange = ($currentPrice - $prevPrice) / $prevPrice * 100;

        return [
            'rsi' => $rsi,
            'ema9' => $ema9,
            'ema21' => $ema21,
            'volume_ratio' => $volumeRatio,
            'price_change' => $priceChange,
            'current_price' => $currentPrice
        ];
    }

    /**
     * Determine signal based on sniper strategy
     */
    private function determineSignal(array $indicators): string
    {
        // Sniper strategy looks for:
        // 1. RSI < 30 (oversold) or RSI > 70 (overbought)
        // 2. EMA crossover (9 over 21 for long, 21 over 9 for short)
        // 3. High volume confirmation
        // 4. Strong price action

        $rsi = $indicators['rsi'];
        $ema9 = $indicators['ema9'];
        $ema21 = $indicators['ema21'];
        $volumeRatio = $indicators['volume_ratio'];
        $priceChange = $indicators['price_change'];

        // Long signal conditions
        if ($rsi < 30 && $ema9 > $ema21 && $volumeRatio > 1.5 && $priceChange > 1) {
            return SignalType::LONG;
        }

        // Short signal conditions
        if ($rsi > 70 && $ema9 < $ema21 && $volumeRatio > 1.5 && $priceChange < -1) {
            return SignalType::SHORT;
        }

        return SignalType::NEUTRAL;
    }

    /**
     * Calculate entry, stop loss, and take profit levels
     */
    private function calculateLevels(array $klines, string $signal): array
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $highs = array_map(fn($k) => floatval($k[2]), $klines);
        $lows = array_map(fn($k) => floatval($k[3]), $klines);

        $currentPrice = end($closes);
        $atr = $this->calculateATR($klines, 14);

        // Use the current 1h close price as reference (not live market price)
        $referencePrice = $currentPrice;

        if ($signal === SignalType::LONG) {
            // For long positions
            $entry = $referencePrice; // Market entry
            $stopLoss = $entry - ($atr * 1.5); // ATR-based stop loss
            $takeProfit = $entry + ($atr * 3); // 3:1 risk-reward
        } elseif ($signal === SignalType::SHORT) {
            // For short positions
            $entry = $referencePrice; // Market entry
            $stopLoss = $entry + ($atr * 1.5); // ATR-based stop loss
            $takeProfit = $entry - ($atr * 3); // 3:1 risk-reward
        } else {
            // For neutral positions - use safe defaults to prevent division by zero
            $entry = max($referencePrice, 1); // Ensure entry is never 0
            $stopLoss = $entry * 0.99; // 1% below entry
            $takeProfit = $entry * 1.01; // 1% above entry
        }

        // Ensure we have valid levels
        if ($entry <= 0 || $stopLoss <= 0 || $takeProfit <= 0) {
            return [
                'entry' => $referencePrice,
                'stop_loss' => $referencePrice * 0.99,
                'take_profit' => $referencePrice * 1.01,
                'risk_reward' => '1:1'
            ];
        }

        $risk = abs($entry - $stopLoss);
        $reward = abs($takeProfit - $entry);
        $riskReward = $risk > 0 ? round($reward / $risk, 1) : 1;

        return [
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward' => "1:{$riskReward}"
        ];
    }

    /**
     * Calculate confidence percentage
     */
    private function calculateConfidence(array $indicators, string $signal): float
    {
        if ($signal === 'hold') {
            return 0;
        }

        $confidence = 50; // Base confidence

        // Adjust based on RSI
        if ($indicators['rsi'] < 30) {
            $confidence += 15; // Strong oversold
        } elseif ($indicators['rsi'] > 70) {
            $confidence += 15; // Strong overbought
        }

        // Adjust based on EMA alignment
        if (($signal === SignalType::LONG && $indicators['ema9'] > $indicators['ema21']) ||
            ($signal === SignalType::SHORT && $indicators['ema9'] < $indicators['ema21'])) {
            $confidence += 10;
        }

        // Adjust based on volume
        if ($indicators['volume_ratio'] > 2) {
            $confidence += 10;
        } elseif ($indicators['volume_ratio'] > 1.5) {
            $confidence += 5;
        }

        // Adjust based on price action
        if (($signal === SignalType::LONG && $indicators['price_change'] > 2) ||
            ($signal === SignalType::SHORT && $indicators['price_change'] < -2)) {
            $confidence += 10;
        }

        return min(95, max(0, $confidence)); // Cap between 0-95%
    }

    /**
     * Calculate risk-reward ratio
     */
    private function calculateRiskReward(float $entry, float $stopLoss, float $takeProfit): float
    {
        $risk = abs($entry - $stopLoss);
        $reward = abs($takeProfit - $entry);

        if ($risk == 0) {
            return 0;
        }

        return round($reward / $risk, 2);
    }

    /**
     * Calculate RSI
     */
    private function calculateRSI(array $prices, int $period = 14): float
    {
        if (count($prices) < $period + 1) {
            return 50; // Neutral RSI
        }

        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;
        }

        if ($avgLoss == 0) {
            return 100;
        }

        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    /**
     * Calculate EMA
     */
    private function calculateEMA(array $prices, int $period): float
    {
        if (count($prices) < $period) {
            return end($prices); // Return last price if not enough data
        }

        $multiplier = 2 / ($period + 1);
        $ema = $prices[0];

        for ($i = 1; $i < count($prices); $i++) {
            $ema = ($prices[$i] - $ema) * $multiplier + $ema;
        }

        return $ema;
    }

    /**
     * Calculate ATR
     */
    private function calculateATR(array $klines, int $period = 14): float
    {
        if (count($klines) < $period + 1) {
            return 0.01; // Default ATR
        }

        $trs = [];
        for ($i = 1; $i < count($klines); $i++) {
            $h = floatval($klines[$i][2]);
            $l = floatval($klines[$i][3]);
            $pc = floatval($klines[$i - 1][4]);
            $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
        }

        $slice = array_slice($trs, -$period);
        return array_sum($slice) / count($slice);
    }

}