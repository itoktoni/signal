<?php

namespace App\Services;

use App\Services\AnalysisService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupportResistanceService extends AnalysisService
{
    public function getName(): string
    {
        return 'support_resistance';
    }

    public function analyze(string $symbol, float $amount = 1000): object
    {
        // Get market data
        $klines = $this->getKlines($symbol, '1d', 100);
        if (!$klines) {
            throw new \Exception("Failed to fetch market data for {$symbol}");
        }

        // Identify support and resistance levels
        $levels = $this->identifySupportResistanceLevels($klines);

        // Determine signal based on current price relative to levels
        $signal = $this->determineSignal($klines, $levels);

        // Calculate entry, stop loss, and take profit levels
        $tradeLevels = $this->calculateTradeLevels($levels, $signal);

        // Calculate confidence based on level strength
        $confidence = $this->calculateConfidence($levels, $signal);

        // Calculate risk-reward ratio
        $riskReward = $this->calculateRiskReward($tradeLevels['entry'], $tradeLevels['stop_loss'], $tradeLevels['take_profit']);

        // Format the result with dynamic amount
        return $this->formatResult(
            "Support/Resistance Analysis for {$symbol}",
            $signal,
            $confidence,
            $tradeLevels['entry'],
            $tradeLevels['stop_loss'],
            $tradeLevels['take_profit'],
            $tradeLevels['risk_reward'] !== '1:1' ? floatval(str_replace('1:', '', $tradeLevels['risk_reward'])) : 1.0,
            $amount // dynamic position size in USD
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
            $data = $response->json();

            // Debug: Log the first few data points to check values
            if (config('app.debug') && !empty($data)) {
                Log::info("Binance API Response Sample", [
                    'symbol' => $symbol,
                    'first_kline' => $data[0] ?? 'No data',
                    'last_kline' => $data[count($data) - 1] ?? 'No data'
                ]);
            }

            return $data;
        }

        return null;
    }

    /**
     * Identify key support and resistance levels
     */
    private function identifySupportResistanceLevels(array $klines): array
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $highs = array_map(fn($k) => floatval($k[2]), $klines);
        $lows = array_map(fn($k) => floatval($k[3]), $klines);
        $volumes = array_map(fn($k) => floatval($k[5]), $klines);

        // Find local highs and lows
        $localHighs = $this->findLocalHighs($highs);
        $localLows = $this->findLocalLows($lows);

        // Cluster similar levels
        $supportLevels = $this->clusterLevels($localLows, 0.01); // 1% proximity
        $resistanceLevels = $this->clusterLevels($localHighs, 0.01); // 1% proximity

        // Score levels based on frequency and volume
        $supportLevels = $this->scoreLevels($supportLevels, $lows, $volumes);
        $resistanceLevels = $this->scoreLevels($resistanceLevels, $highs, $volumes);

        // Sort by score (highest first)
        usort($supportLevels, fn($a, $b) => $b['score'] <=> $a['score']);
        usort($resistanceLevels, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'support' => array_slice($supportLevels, 0, 5), // Top 5 support levels
            'resistance' => array_slice($resistanceLevels, 0, 5) // Top 5 resistance levels
        ];
    }

    /**
     * Find local highs in price data
     */
    private function findLocalHighs(array $highs): array
    {
        $localHighs = [];
        $window = 5; // Look at 5 candles before and after

        for ($i = $window; $i < count($highs) - $window; $i++) {
            $isHigh = true;
            for ($j = $i - $window; $j <= $i + $window; $j++) {
                if ($j != $i && $highs[$j] > $highs[$i]) {
                    $isHigh = false;
                    break;
                }
            }
            if ($isHigh) {
                $localHighs[] = $highs[$i];
            }
        }

        return $localHighs;
    }

    /**
     * Find local lows in price data
     */
    private function findLocalLows(array $lows): array
    {
        $localLows = [];
        $window = 5; // Look at 5 candles before and after

        for ($i = $window; $i < count($lows) - $window; $i++) {
            $isLow = true;
            for ($j = $i - $window; $j <= $i + $window; $j++) {
                if ($j != $i && $lows[$j] < $lows[$i]) {
                    $isLow = false;
                    break;
                }
            }
            if ($isLow) {
                $localLows[] = $lows[$i];
            }
        }

        return $localLows;
    }

    /**
     * Cluster similar levels together
     */
    private function clusterLevels(array $levels, float $proximityThreshold): array
    {
        if (empty($levels)) {
            return [];
        }

        sort($levels);
        $clusters = [];
        $currentCluster = [$levels[0]];

        for ($i = 1; $i < count($levels); $i++) {
            $distance = abs($levels[$i] - end($currentCluster));
            $avgLevel = array_sum($currentCluster) / count($currentCluster);
            $threshold = $avgLevel * $proximityThreshold;

            if ($distance <= $threshold) {
                $currentCluster[] = $levels[$i];
            } else {
                $clusters[] = $currentCluster;
                $currentCluster = [$levels[$i]];
            }
        }

        $clusters[] = $currentCluster;

        // Convert clusters to level objects with average price
        $clusteredLevels = [];
        foreach ($clusters as $cluster) {
            $avgPrice = array_sum($cluster) / count($cluster);
            $clusteredLevels[] = [
                'price' => $avgPrice,
                'count' => count($cluster)
            ];
        }

        return $clusteredLevels;
    }

    /**
     * Score levels based on frequency and volume
     */
    private function scoreLevels(array $levels, array $prices, array $volumes): array
    {
        $scoredLevels = [];

        foreach ($levels as $level) {
            $score = $level['count']; // Base score on frequency

            // Add volume factor
            $volumeScore = 0;
            for ($i = 0; $i < count($prices); $i++) {
                $distance = abs($prices[$i] - $level['price']);
                $threshold = $level['price'] * 0.005; // 0.5% proximity

                if ($distance <= $threshold) {
                    $volumeScore += $volumes[$i];
                }
            }

            // Normalize volume score
            $avgVolume = array_sum($volumes) / count($volumes);
            $volumeFactor = $volumeScore / $avgVolume;

            $scoredLevels[] = [
                'price' => $level['price'],
                'count' => $level['count'],
                'volume_factor' => $volumeFactor,
                'score' => $score + $volumeFactor
            ];
        }

        return $scoredLevels;
    }

    /**
     * Determine signal based on current price relative to support/resistance levels
     */
    private function determineSignal(array $klines, array $levels): string
    {
        $closes = array_map(fn($k) => floatval($k[4]), $klines);
        $currentPrice = end($closes);

        // Use the current 1d close price as reference (not live market price)
        $referencePrice = $currentPrice;

        $nearestSupport = null;
        $nearestResistance = null;
        $supportDistance = PHP_FLOAT_MAX;
        $resistanceDistance = PHP_FLOAT_MAX;

        // Find nearest support level
        foreach ($levels['support'] as $support) {
            $distance = $referencePrice - $support['price'];
            if ($distance > 0 && $distance < $supportDistance) {
                $supportDistance = $distance;
                $nearestSupport = $support;
            }
        }

        // Find nearest resistance level
        foreach ($levels['resistance'] as $resistance) {
            $distance = $resistance['price'] - $referencePrice;
            if ($distance > 0 && $distance < $resistanceDistance) {
                $resistanceDistance = $distance;
                $nearestResistance = $resistance;
            }
        }

        // Determine signal based on proximity to levels
        if ($nearestSupport && $supportDistance < ($referencePrice * 0.01)) { // Within 1%
            return 'long'; // Near strong support
        }

        if ($nearestResistance && $resistanceDistance < ($referencePrice * 0.01)) { // Within 1%
            return 'short'; // Near strong resistance
        }

        return 'hold';
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
     * Calculate trade levels based on support/resistance
     */
    private function calculateTradeLevels(array $levels, string $signal): array
    {
        // Use the current 1d close price for calculations (not live market price)
        $marketPrice = null;

        if ($signal === 'hold') {
            // For hold signal, return current market price as safe default
            $referencePrice = $marketPrice ?: 1;
            return [
                'entry' => $referencePrice,
                'stop_loss' => $referencePrice * 0.99,
                'take_profit' => $referencePrice * 1.01,
                'risk_reward' => '1:1'
            ];
        }

        if ($signal === 'long') {
            // Find nearest support level for entry
            $entryLevel = null;
            $stopLossLevel = null;
            $takeProfitLevel = null;

            if (!empty($levels['support'])) {
                $entryLevel = $levels['support'][0]['price']; // Strongest support
                $stopLossLevel = $entryLevel * 0.98; // 2% below support
            }

            // Find nearest resistance level for take profit
            if (!empty($levels['resistance'])) {
                $takeProfitLevel = $levels['resistance'][0]['price']; // Strongest resistance
            } else {
                $takeProfitLevel = $entryLevel * 1.06; // 6% above entry if no resistance
            }

            // Ensure we have valid levels
            if ($entryLevel <= 0 || $stopLossLevel <= 0 || $takeProfitLevel <= 0) {
                $referencePrice = $marketPrice ?: 1;
                return [
                    'entry' => $referencePrice,
                    'stop_loss' => $referencePrice * 0.99,
                    'take_profit' => $referencePrice * 1.01,
                    'risk_reward' => '1:1'
                ];
            }

            $risk = abs($entryLevel - $stopLossLevel);
            $reward = abs($takeProfitLevel - $entryLevel);
            $riskReward = $risk > 0 ? round($reward / $risk, 1) : 1;

            return [
                'entry' => $entryLevel,
                'stop_loss' => $stopLossLevel,
                'take_profit' => $takeProfitLevel,
                'risk_reward' => "1:{$riskReward}"
            ];
        } else { // short
            // Find nearest resistance level for entry
            $entryLevel = null;
            $stopLossLevel = null;
            $takeProfitLevel = null;

            if (!empty($levels['resistance'])) {
                $entryLevel = $levels['resistance'][0]['price']; // Strongest resistance
                $stopLossLevel = $entryLevel * 1.02; // 2% above resistance
            }

            // Find nearest support level for take profit
            if (!empty($levels['support'])) {
                $takeProfitLevel = $levels['support'][0]['price']; // Strongest support
            } else {
                $takeProfitLevel = $entryLevel * 0.94; // 6% below entry if no support
            }

            // Ensure we have valid levels
            if ($entryLevel <= 0 || $stopLossLevel <= 0 || $takeProfitLevel <= 0) {
                $referencePrice = $marketPrice ?: 1;
                return [
                    'entry' => $referencePrice,
                    'stop_loss' => $referencePrice * 1.01,
                    'take_profit' => $referencePrice * 0.99,
                    'risk_reward' => '1:1'
                ];
            }

            $risk = abs($entryLevel - $stopLossLevel);
            $reward = abs($takeProfitLevel - $entryLevel);
            $riskReward = $risk > 0 ? round($reward / $risk, 1) : 1;

            return [
                'entry' => $entryLevel,
                'stop_loss' => $stopLossLevel,
                'take_profit' => $takeProfitLevel,
                'risk_reward' => "1:{$riskReward}"
            ];
        }
    }

    /**
     * Calculate confidence percentage
     */
    private function calculateConfidence(array $levels, string $signal): float
    {
        if ($signal === 'hold') {
            return 0;
        }

        $confidence = 50; // Base confidence

        // Adjust based on number of identified levels
        $supportCount = count($levels['support']);
        $resistanceCount = count($levels['resistance']);

        if ($supportCount > 3) {
            $confidence += 10;
        } elseif ($supportCount > 1) {
            $confidence += 5;
        }

        if ($resistanceCount > 3) {
            $confidence += 10;
        } elseif ($resistanceCount > 1) {
            $confidence += 5;
        }

        // Adjust based on level strength (top levels have higher scores)
        if (!empty($levels['support']) && $levels['support'][0]['score'] > 10) {
            $confidence += 10;
        }

        if (!empty($levels['resistance']) && $levels['resistance'][0]['score'] > 10) {
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
}