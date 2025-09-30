<?php

namespace App\Analysis;

use Illuminate\Support\Facades\Log;

class DefaultAnalysis implements AnalysisInterface
{
    protected array $indicators = [];
    protected string $notes = '';
    protected ?string $lastAnalyzedSymbol = null;

    protected ?ApiProviderInterface $apiProvider = null;
    protected float $currentPrice = 0.0;

    public function setApiProvider(ApiProviderInterface $apiProvider): void
    {
        $this->apiProvider = $apiProvider;
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        try {
            if (!$this->apiProvider) {
                throw new \Exception('API Provider not set');
            }

            Log::info("KeltnerChannel: Starting analysis for {$symbol}");

            // Get historical data
            $historicalData = $this->apiProvider->getHistoricalData($symbol, $timeframe, 100);

            if (empty($historicalData) || count($historicalData) < 30) {
                throw new \Exception('Insufficient historical data. Need at least 30 data points.');
            }

            // Get current price
            $currentPrice = $this->apiProvider->getCurrentPrice($symbol);
            $this->currentPrice = $currentPrice;
            $this->lastAnalyzedSymbol = $symbol;

            // Extract prices from historical data
            $closePrices = array_map(fn($candle) => (float) $candle[4], $historicalData);
            $highPrices = array_map(fn($candle) => (float) $candle[2], $historicalData);
            $lowPrices = array_map(fn($candle) => (float) $candle[3], $historicalData);

            // Calculate Keltner Channel
            $keltnerChannel = $this->calculateKeltnerChannel($highPrices, $lowPrices, $closePrices);

            // Determine signal based on Keltner Channel breakout
            [$signal, $confidence] = $this->getKeltnerSignal($currentPrice, $keltnerChannel, $closePrices);

            // Calculate entry price based on breakout
            $suggestedEntry = $this->calculateKeltnerEntry($signal, $currentPrice, $keltnerChannel);

            // Calculate trading levels
            $levels = $this->calculateKeltnerLevels($signal, $suggestedEntry, $currentPrice, $keltnerChannel['atr']);

            // Set indicators
            $this->indicators = [
                'EMA20' => round($keltnerChannel['ema'], 4),
                'Upper_Channel' => round($keltnerChannel['upper'], 4),
                'Lower_Channel' => round($keltnerChannel['lower'], 4),
                'ATR' => round($keltnerChannel['atr'], 4),
                'Channel_Width' => round($keltnerChannel['width'], 4),
                'Current_Price' => round($currentPrice, 4),
                'Suggested_Entry' => round($suggestedEntry, 4)
            ];

            Log::info("KeltnerChannel: Analysis completed", [
                'signal' => $signal,
                'confidence' => $confidence,
                'entry' => $suggestedEntry
            ]);

            return (object)[
                'title' => "Keltner Channel Analysis for {$symbol} ({$timeframe})",
                'description' => $this->getDescription(),
                'signal' => $signal,
                'confidence' => $confidence,
                'entry' => $suggestedEntry,
                'price' => $currentPrice,
                'stop_loss' => $levels['stop_loss'],
                'take_profit' => $levels['take_profit'],
                'risk_reward' => $levels['risk_reward'],
                'indicators' => $this->indicators,
                'notes' => $this->getNotes(),
                'entry_strategy' => $this->getEntryStrategyFlow()
            ];

        } catch (\Exception $e) {
            Log::error("KeltnerChannel: Analysis failed", [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);

            return (object)[
                'title' => "Keltner Channel Analysis for {$symbol} ({$timeframe}) - Limited Data",
                'description' => $this->getDescription(),
                'signal' => 'NEUTRAL',
                'confidence' => 30,
                'entry' => $currentPrice ?? 0,
                'price' => $currentPrice ?? 0,
                'stop_loss' => ($currentPrice ?? 0) * 0.98,
                'take_profit' => ($currentPrice ?? 0) * 1.02,
                'risk_reward' => '1:1',
                'indicators' => [],
                'notes' => $this->getNotes(),
                'entry_strategy' => $this->getEntryStrategyFlow()
            ];
        }
    }

    public function getCode(): string
    {
        return 'keltner_channel';
    }

    public function getName(): string
    {
        return 'Default Analysis';
    }

    public function getDescription(): array
    {
        return [
            'analysis_type' => 'Keltner Channel Analysis',
            'indicators' => 'EMA20, ATR-based Channels, Volatility measurement',
            'features' => 'Channel breakout signals, Anti-delay execution',
            'signal_logic' => 'BUY on Upper Channel breakout, SELL on Lower Channel breakout',
            'risk_management' => 'ATR-based stop loss and take profit levels'
        ];
    }

    public function getIndicators(): array
    {
        return $this->indicators;
    }

    public function getNotes(): array
    {
        return [
            'main_notes' => $this->notes,
            'signal_strength' => $this->calculateKeltnerSignalStrength(),
            'market_condition' => $this->analyzeKeltnerMarketCondition(),
            'risk_level' => $this->assessKeltnerRiskLevel(),
            'execution_tips' => $this->getKeltnerExecutionTips()
        ];
    }

    public function getEntryStrategyFlow(): array
    {
        return [
            'strategy_name' => 'Keltner Channel Breakout Strategy',
            'channel_components' => 'Middle: EMA20, Upper: EMA20 + 2×ATR, Lower: EMA20 - 2×ATR',
            'breakout_signals' => 'BUY on Upper Channel breakout, SELL on Lower Channel breakout',
            'entry_points' => 'Enter at channel breakout level',
            'risk_management' => 'Stop Loss: 1.5x ATR from entry, Take Profit: 2:1 R:R ratio',
            'execution_tips' => 'Wait for candle close above/below channel, Use limit orders at channel levels'
        ];
    }

    /**
     * Calculate Keltner Channel (EMA20 + ATR-based channels)
     */
    private function calculateKeltnerChannel(array $highs, array $lows, array $closes): array
    {
        $ema20 = $this->calculateEMA($closes, 20);
        $atr = $this->calculateATR($highs, $lows, $closes, 14);

        $upperChannel = $ema20 + ($atr * 2.0);
        $lowerChannel = $ema20 - ($atr * 2.0);
        $channelWidth = $upperChannel - $lowerChannel;

        return [
            'ema' => $ema20,
            'upper' => $upperChannel,
            'lower' => $lowerChannel,
            'atr' => $atr,
            'width' => $channelWidth
        ];
    }

    /**
     * Get signal based on Keltner Channel breakout
     */
    private function getKeltnerSignal(float $currentPrice, array $keltnerChannel, array $closePrices): array
    {
        $ema = $keltnerChannel['ema'];
        $upper = $keltnerChannel['upper'];
        $lower = $keltnerChannel['lower'];
        $atr = $keltnerChannel['atr'];

        $confidence = 50;
        $signal = 'NEUTRAL';

        // Check for channel breakout
        $priceAboveUpper = $currentPrice > $upper;
        $priceBelowLower = $currentPrice < $lower;

        if ($priceAboveUpper) {
            $breakoutStrength = ($currentPrice - $upper) / $atr;
            if ($breakoutStrength > 1.0) {
                $signal = 'BUY';
                $confidence = min(95, 60 + ($breakoutStrength * 10));
                $this->notes = "Strong BUY: Price breakout above Upper Keltner Channel (Strength: {$breakoutStrength})";
            } elseif ($breakoutStrength > 0.5) {
                $signal = 'BUY';
                $confidence = min(85, 50 + ($breakoutStrength * 10));
                $this->notes = "BUY: Price breakout above Upper Keltner Channel (Strength: {$breakoutStrength})";
            }
        } elseif ($priceBelowLower) {
            $breakoutStrength = ($lower - $currentPrice) / $atr;
            if ($breakoutStrength > 1.0) {
                $signal = 'SELL';
                $confidence = min(95, 60 + ($breakoutStrength * 10));
                $this->notes = "Strong SELL: Price breakout below Lower Keltner Channel (Strength: {$breakoutStrength})";
            } elseif ($breakoutStrength > 0.5) {
                $signal = 'SELL';
                $confidence = min(85, 50 + ($breakoutStrength * 10));
                $this->notes = "SELL: Price breakout below Lower Keltner Channel (Strength: {$breakoutStrength})";
            }
        } else {
            $this->notes = "NEUTRAL: Price within Keltner Channel - waiting for breakout";
        }

        return [$signal, round($confidence)];
    }

    /**
     * Calculate entry price based on Keltner Channel breakout
     */
    private function calculateKeltnerEntry(string $signal, float $currentPrice, array $keltnerChannel): float
    {
        $upper = $keltnerChannel['upper'];
        $lower = $keltnerChannel['lower'];

        if ($signal === 'BUY') {
            return $upper * 1.001; // 0.1% above upper channel
        } elseif ($signal === 'SELL') {
            return $lower * 0.999; // 0.1% below lower channel
        } else {
            return $currentPrice;
        }
    }

    /**
     * Calculate trading levels for Keltner Channel
     */
    private function calculateKeltnerLevels(string $signal, float $entryPrice, float $currentPrice, float $atr): array
    {
        $atrMultiplier = 1.5;
        $minRiskReward = 2.0;

        if ($signal === 'BUY') {
            $stopLoss = $entryPrice - ($atr * $atrMultiplier);
            $risk = $entryPrice - $stopLoss;
            $reward = $risk * $minRiskReward;
            $takeProfit = $entryPrice + $reward;
        } elseif ($signal === 'SELL') {
            $stopLoss = $entryPrice + ($atr * $atrMultiplier);
            $risk = $stopLoss - $entryPrice;
            $reward = $risk * $minRiskReward;
            $takeProfit = $entryPrice - $reward;
        } else {
            $stopLoss = $entryPrice - ($atr * $atrMultiplier);
            $takeProfit = $entryPrice + ($atr * $atrMultiplier * $minRiskReward);
        }

        $risk = abs($entryPrice - $stopLoss);
        $reward = abs($takeProfit - $entryPrice);
        $riskReward = $risk > 0 ? round($reward / $risk, 2) . ":1" : "1:1";

        return [
            'stop_loss' => round($stopLoss, 4),
            'take_profit' => round($takeProfit, 4),
            'risk_reward' => $riskReward
        ];
    }

    /**
     * Calculate EMA
     */
    private function calculateEMA(array $prices, int $period): float
    {
        if (count($prices) < $period) {
            return end($prices);
        }

        $k = 2 / ($period + 1);
        $ema = $prices[count($prices) - $period];

        for ($i = count($prices) - $period + 1; $i < count($prices); $i++) {
            $ema = ($prices[$i] * $k) + ($ema * (1 - $k));
        }

        return $ema;
    }

    /**
     * Calculate ATR
     */
    private function calculateATR(array $highs, array $lows, array $closes, int $period): float
    {
        if (count($closes) < 2) {
            return 0;
        }

        $trs = [];
        for ($i = 1; $i < min(count($closes), $period + 1); $i++) {
            $tr = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
            $trs[] = $tr;
        }

        if (count($trs) < $period) {
            return array_sum($trs) / max(1, count($trs));
        }

        return array_sum(array_slice($trs, -$period)) / $period;
    }

    /**
     * Calculate Keltner Channel signal strength
     */
    private function calculateKeltnerSignalStrength(): string
    {
        if (empty($this->indicators)) {
            return 'Insufficient data';
        }

        $channelWidth = $this->indicators['Channel_Width'] ?? 0;
        $currentPrice = $this->indicators['Current_Price'] ?? 0;
        $atr = $this->indicators['ATR'] ?? 0;

        if ($currentPrice == 0 || $atr == 0) return 'Unknown';

        $volatility = $channelWidth / $currentPrice;

        if ($volatility > 0.03) return 'High volatility';
        if ($volatility > 0.02) return 'Moderate volatility';
        if ($volatility > 0.01) return 'Low volatility';
        return 'Very low volatility';
    }

    /**
     * Analyze Keltner Channel market condition
     */
    private function analyzeKeltnerMarketCondition(): string
    {
        if (empty($this->indicators)) {
            return 'Unknown';
        }

        $ema = $this->indicators['EMA20'] ?? 0;
        $upper = $this->indicators['Upper_Channel'] ?? 0;
        $lower = $this->indicators['Lower_Channel'] ?? 0;
        $currentPrice = $this->indicators['Current_Price'] ?? 0;

        if ($currentPrice > $upper) {
            return 'Breakout above channel - bullish momentum';
        } elseif ($currentPrice < $lower) {
            return 'Breakdown below channel - bearish momentum';
        } elseif ($currentPrice > $ema) {
            return 'Above EMA - bullish bias within channel';
        } elseif ($currentPrice < $ema) {
            return 'Below EMA - bearish bias within channel';
        } else {
            return 'At EMA level - neutral position';
        }
    }

    /**
     * Assess Keltner Channel risk level
     */
    private function assessKeltnerRiskLevel(): string
    {
        if (empty($this->indicators)) {
            return 'Unknown';
        }

        $channelWidth = $this->indicators['Channel_Width'] ?? 0;
        $currentPrice = $this->indicators['Current_Price'] ?? 0;
        $atr = $this->indicators['ATR'] ?? 0;

        if ($currentPrice == 0) return 'Unknown';

        $channelPercent = ($channelWidth / $currentPrice) * 100;
        $atrPercent = ($atr / $currentPrice) * 100;

        if ($channelPercent > 6 || $atrPercent > 4) return 'Very high risk';
        if ($channelPercent > 4 || $atrPercent > 3) return 'High risk';
        if ($channelPercent > 2 || $atrPercent > 2) return 'Medium risk';
        if ($channelPercent > 1 || $atrPercent > 1) return 'Low risk';
        return 'Very low risk';
    }

    /**
     * Get Keltner Channel execution tips
     */
    private function getKeltnerExecutionTips(): string
    {
        $signal = $this->getCurrentKeltnerSignal();

        if ($signal === 'BUY') {
            return 'Wait for price to close above Upper Channel. Consider buying on pullback to EMA.';
        } elseif ($signal === 'SELL') {
            return 'Wait for price to close below Lower Channel. Consider selling on rally to EMA.';
        } else {
            return 'No clear signal. Wait for breakout above Upper or below Lower Channel.';
        }
    }

    /**
     * Get current Keltner signal
     */
    private function getCurrentKeltnerSignal(): string
    {
        if (strpos($this->notes, 'BUY') !== false) return 'BUY';
        if (strpos($this->notes, 'SELL') !== false) return 'SELL';
        return 'NEUTRAL';
    }

    /**
     * Get historical data for analysis
     */
    public function getHistoricalData(string $symbol, string $timeframe = '1h', int $limit = 200): array
    {
        if (!$this->apiProvider) {
            throw new \Exception('API Provider not set');
        }

        return $this->apiProvider->getHistoricalData($symbol, $timeframe, $limit);
    }

    /**
     * Get current price
     */
    public function getPrice(string $symbol): float
    {
        if (!$this->apiProvider) {
            throw new \Exception('API Provider not set');
        }

        // If we have a stored price and it's for the requested symbol, return it
        if ($this->currentPrice && $this->lastAnalyzedSymbol === $symbol) {
            return $this->currentPrice;
        }

        // Otherwise, fetch fresh price from API
        return $this->apiProvider->getCurrentPrice($symbol);
    }
}