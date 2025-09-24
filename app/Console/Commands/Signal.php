<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\CurrencyHelper;

// Original KiloNova Signal Analysis Engine
class Signal extends Command
{
    protected $signature = 'crypto:signal {symbol=BTCUSDT} {position=100} {--fallback : Use fallback data source if primary fails}';
    protected $description = 'KiloNova proprietary signal analysis engine with robust error handling';

    // KiloNova Configuration System
    protected $dataSource;
    protected $messagingToken;
    protected $messagingChannel;
    protected $currencyConverter;
    protected $fastTrendPeriod;
    protected $slowTrendPeriod;
    protected $momentumPeriod;
    protected $trendFast;
    protected $trendSlow;
    protected $trendSignal;
    protected $volatilityPeriod;
    protected $volatilityMultiplier;
    protected $momentumEntryThreshold;
    protected $momentumExitThreshold;
    protected $profitTargetPercent;
    protected $analysisIntervals;
    protected $signalHistoryFile;
    protected $forceSend;

    // KiloNova Adaptive Scoring Matrix
    protected $adaptiveWeights = [
        'trend_alignment' => 2.5,    // Primary trend direction
        'momentum_strength' => 2.0,  // Momentum confirmation
        'volatility_position' => 1.8, // Price position in bands
        'volume_flow' => 1.5,        // Volume trend analysis
        'support_resistance' => 1.7, // Key level proximity
        'market_structure' => 2.2,   // Overall market structure
    ];

    public function __construct()
    {
        parent::__construct();

        // KiloNova Environment Configuration
        $this->dataSource     = env('DATA_SOURCE', 'https://api.binance.com');
        $this->fallbackSource = env('FALLBACK_SOURCE', 'https://api.binance.com');
        $this->messagingToken  = env('MESSAGING_BOT_TOKEN');
        $this->messagingChannel = env('MESSAGING_CHAT_ID');
        $this->currencyConverter = env('CURRENCY_CONVERTER', 16000);

        // Initialize currency helper with exchange rate
        CurrencyHelper::setExchangeRate($this->currencyConverter);
        $this->fastTrendPeriod = env('FAST_TREND_PERIOD', 21);
        $this->slowTrendPeriod = env('SLOW_TREND_PERIOD', 55);
        $this->momentumPeriod = env('MOMENTUM_PERIOD', 14);
        $this->trendFast = env('TREND_FAST', 8);
        $this->trendSlow = env('TREND_SLOW', 21);
        $this->trendSignal = env('TREND_SIGNAL', 5);
        $this->volatilityPeriod = env('VOLATILITY_PERIOD', 20);
        $this->volatilityMultiplier = env('VOLATILITY_MULTIPLIER', 2.1);
        $this->momentumEntryThreshold = env('MOMENTUM_ENTRY_THRESHOLD', 28);
        $this->momentumExitThreshold = env('MOMENTUM_EXIT_THRESHOLD', 72);
        $this->profitTargetPercent = env('PROFIT_TARGET_PERCENT', 6);
        $this->analysisIntervals = ['15m' => '15M', '1h' => '1H', '4h' => '4H'];
        $this->signalHistoryFile = env('SIGNAL_HISTORY_FILE', storage_path('.kilnova_signal_hash'));
        $this->forceSend = env('FORCE_SEND', false);
    }

    public function handle()
    {
        ini_set('memory_limit', '512M');

        $asset = strtoupper($this->argument('symbol'));
        if (!str_ends_with($asset, 'USDT')) $asset .= 'USDT';
        $capitalAmount = floatval($this->argument('position'));

        $this->info(now()->toDateTimeString() . " | KiloNova Analysis Engine: {$asset} | Capital: \${$capitalAmount}");

        $precision = $this->getAssetPrecision($asset);
        $marketTrend = $this->getMarketTrend($asset);

        $timeframeData = [];
        $signalScores = [];
        $currentPrice = null;

        foreach ($this->analysisIntervals as $interval => $label) {
            $marketData = $this->fetchMarketData($asset, $interval, 500);
            if (!$marketData || count($marketData) < 150) {
                $this->warn("Insufficient data for {$label}");
                $timeframeData[$label] = null;
                $signalScores[$label] = null;
                continue;
            }

            $analysisResult = $this->performKiloNovaAnalysis($marketData, $precision);
            if (!$analysisResult) {
                $this->warn("Analysis failed for {$label}");
                $timeframeData[$label] = null;
                $signalScores[$label] = null;
                continue;
            }

            if ($currentPrice === null) {
                $closingPrices = array_map(fn($candle) => floatval($candle[4]), $marketData);
                $currentPrice = end($closingPrices);
            }

            $timeframeData[$label] = $analysisResult;
            $signalScores[$label] = $analysisResult['signal_strength'];
        }

        if (empty(array_filter($timeframeData))) {
            $this->error("❌ Analysis failed for {$asset}. No valid timeframe data received.");
            $this->info("💡 Troubleshooting tips:");
            $this->info("   • Check your internet connection");
            $this->info("   • Verify Binance API is accessible");
            $this->info("   • Try again in a few minutes");
            $this->info("   • Consider using a VPN if in restricted region");
            return self::FAILURE;
        }

        // Continue with available data even if some timeframes failed
        $validTimeframes = array_filter($timeframeData);
        if (count($validTimeframes) < count($this->analysisIntervals)) {
            $this->warn("⚠️ Some timeframes failed to load. Continuing with " . count($validTimeframes) . " valid timeframes.");
        }

        // KiloNova Adaptive Signal Processing
        $validSignals = array_filter($signalScores, fn($score) => $score !== null);
        $bullishCount = count(array_filter($validSignals, fn($score) => $score > 0.3));
        $bearishCount = count(array_filter($validSignals, fn($score) => $score < -0.3));
        $totalSignals = count($validSignals);

        $signalQuality = $this->calculateSignalQuality($validSignals, $bullishCount, $bearishCount, $totalSignals);

        if ($bullishCount === $totalSignals && $bullishCount > 0) $finalSignal = "🚀 STRONG BULLISH - Maximum conviction";
        elseif ($bullishCount >= $totalSignals * 0.75) $finalSignal = "📈 BULLISH - High confidence";
        elseif ($signalScores['1H'] > 0.2 && ($signalScores['4H'] <= 0.2 || $signalScores['15M'] <= 0.2)) $finalSignal = "⚡ SCALPING MODE - Quick trades only";
        elseif ($bullishCount > $bearishCount) $finalSignal = $this->generateAdaptiveSignal($timeframeData, $currentPrice);
        else $finalSignal = $this->generateBearishStrategy($timeframeData, $currentPrice, $signalQuality);

        $signalMessage = $this->createKiloNovaReport($asset, $currentPrice, $timeframeData, $finalSignal, $signalQuality, $marketTrend, $capitalAmount);

        // Signal History Management
        $signalHash = md5($signalMessage);
        $previousSignal = @file_get_contents($this->signalHistoryFile);
        if ($previousSignal === $signalHash && !$this->forceSend) {
            $this->info("Signal unchanged. Skipping notification.");
            $this->line($signalMessage);
            return self::SUCCESS;
        }
        @file_put_contents($this->signalHistoryFile, $signalHash);

        $this->dispatchSignal($signalMessage);
        $this->info("✅ KiloNova signal dispatched successfully.");
        $this->line($signalMessage);

        return self::SUCCESS;
    }

    protected function performKiloNovaAnalysis(array $marketData, float $precision = 0.01): ?array
    {
        $closingPrices = array_map(fn($candle) => floatval($candle[4]), $marketData);
        $highPrices = array_map(fn($candle) => floatval($candle[2]), $marketData);
        $lowPrices = array_map(fn($candle) => floatval($candle[3]), $marketData);
        $volumeData = array_map(fn($candle) => floatval($candle[5]), $marketData);
        $currentPrice = end($closingPrices);

        // KiloNova Adaptive Moving Averages
        $fastMA = $this->calculateAdaptiveMA($closingPrices, $this->fastTrendPeriod);
        $slowMA = $this->calculateAdaptiveMA($closingPrices, $this->slowTrendPeriod);
        $momentumIndicator = $this->calculateKiloNovaMomentum($closingPrices, $this->momentumPeriod);

        // KiloNova Trend Analysis
        $trendData = $this->calculateKiloNovaTrend($closingPrices, $this->trendFast, $this->trendSlow, $this->trendSignal);
        $trendLine = end($trendData['trend']);
        $trendSignal = end($trendData['signal']);
        $trendHistogram = end($trendData['histogram']);

        // KiloNova Volatility Bands
        $volatilityBands = $this->calculateKiloNovaBands($closingPrices, $this->volatilityPeriod, $this->volatilityMultiplier);
        $upperBand = end($volatilityBands['upper']);
        $middleBand = end($volatilityBands['middle']);
        $lowerBand = end($volatilityBands['lower']);

        if ($fastMA === null || $slowMA === null || $momentumIndicator === null || $trendLine === null) return null;

        // KiloNova Volatility Measure
        $volatilityMeasure = $this->calculateKiloNovaVolatility($marketData, 14);
        $volatilityLevel = $volatilityMeasure ?: max(0.0001, $currentPrice * 0.005);

        // KiloNova Support/Resistance Matrix
        $recentPriceSlice = array_slice($closingPrices, -25);
        $supportLevel = min($recentPriceSlice);
        $resistanceLevel = max($recentPriceSlice);

        // KiloNova Volume Flow Analysis
        $volumeAverage = $this->calculateVolumeAverage($volumeData);
        $currentVolume = end($volumeData);
        $volumeFlow = 0;
        if ($volumeAverage > 0 && $currentVolume >= $volumeAverage * 1.4) $volumeFlow = 2;
        elseif ($volumeAverage > 0 && $currentVolume >= $volumeAverage * 1.1) $volumeFlow = 1;
        elseif ($volumeAverage > 0 && $currentVolume <= $volumeAverage * 0.6) $volumeFlow = -1;
        elseif ($volumeAverage > 0 && $currentVolume <= $volumeAverage * 0.4) $volumeFlow = -2;

        // KiloNova Adaptive Scoring Engine
        $trendAlignment = ($fastMA > $slowMA) ? $this->adaptiveWeights['trend_alignment'] : -$this->adaptiveWeights['trend_alignment'];
        $momentumStrength = ($momentumIndicator < 30) ? $this->adaptiveWeights['momentum_strength'] : (($momentumIndicator > 70) ? -$this->adaptiveWeights['momentum_strength'] : 0);
        $volatilityPosition = ($currentPrice < $lowerBand) ? $this->adaptiveWeights['volatility_position'] : (($currentPrice > $upperBand) ? -$this->adaptiveWeights['volatility_position'] : 0);
        $supportResistance = ($this->checkProximity($currentPrice, $supportLevel, $resistanceLevel)) ? $this->adaptiveWeights['support_resistance'] : 0;

        // KiloNova Market Structure Analysis
        $marketStructure = $this->analyzeMarketStructure($closingPrices, $highPrices, $lowPrices);

        $compositeScore = $trendAlignment + $momentumStrength + $volatilityPosition + $volumeFlow + $supportResistance + $marketStructure;

        // KiloNova Signal Generation Logic
        $entryCondition = false;
        $exitCondition = false;
        $riskLevel = null;
        $targetLevel = null;

        // KiloNova Entry Criteria
        if ($momentumIndicator < $this->momentumEntryThreshold &&
            $currentPrice > $slowMA &&
            $trendHistogram > 0 &&
            $trendLine > $trendSignal &&
            $currentPrice > $lowerBand &&
            $volumeFlow > 0) {
            $entryCondition = true;
            $riskLevel = $currentPrice - 1.5 * $volatilityLevel;
            $targetLevel = $currentPrice + 3.5 * $volatilityLevel;
        }

        // KiloNova Exit Criteria
        if ($momentumIndicator > $this->momentumExitThreshold || $currentPrice < $slowMA || $trendHistogram < 0) {
            $exitCondition = true;
        }

        // KiloNova Position Management
        $longPosition = $this->calculateOptimalEntry($supportLevel, $precision);
        $shortPosition = $this->calculateOptimalEntry($resistanceLevel, $precision);
        $longStop = $this->calculateOptimalEntry($supportLevel - $volatilityLevel * 1.5, $precision);
        $shortStop = $this->calculateOptimalEntry($resistanceLevel + $volatilityLevel * 1.5, $precision);

        // KiloNova Multi-Target System
        $longTarget1 = $this->calculateOptimalEntry($longPosition + ($longPosition - $longStop) * 3.0, $precision);
        $longTarget2 = $this->calculateOptimalEntry($longPosition + ($longPosition - $longStop) * 5.0, $precision);

        $shortTarget1 = $this->calculateOptimalEntry($shortPosition - ($shortStop - $shortPosition) * 3.0, $precision);
        $shortTarget2 = $this->calculateOptimalEntry($shortPosition - ($shortStop - $shortPosition) * 5.0, $precision);

        // KiloNova Risk-Reward Analysis
        $longRiskRatio = $longPosition > $longStop ? round(($longTarget1 - $longPosition) / ($longPosition - $longStop), 2) : 0;
        $shortRiskRatio = $shortPosition < $shortStop ? round(($shortPosition - $shortTarget1) / ($shortStop - $shortPosition), 2) : 0;

        // KiloNova Success Probability Engine
        $successProbability = $this->calculateKiloNovaProbability($compositeScore, $momentumIndicator, $trendHistogram, $volumeFlow, $volatilityPosition);

        $signalStatus = 'NEUTRAL';
        if ($entryCondition) $signalStatus = 'BULLISH_ENTRY';
        elseif ($exitCondition) $signalStatus = 'EXIT_SIGNAL';

        return [
            'signal_strength' => $compositeScore,
            'market_data' => [
                'Trend_Direction' => $currentPrice > $slowMA ? 'BULLISH_TREND' : 'BEARISH_TREND',
                'Momentum' => round($momentumIndicator, 2),
                'Fast_MA' => round($fastMA, 6),
                'Slow_MA' => round($slowMA, 6),
                'Trend_Line' => round($trendLine, 6),
                'Trend_Signal' => round($trendSignal, 6),
                'Trend_Momentum' => round($trendHistogram, 6),
                'Upper_Band' => round($upperBand, 6),
                'Lower_Band' => round($lowerBand, 6),
                'Volatility' => round($volatilityMeasure, 6),
                'Volume_Flow' => $volumeFlow > 1 ? 'STRONG_BUYING' : ($volumeFlow > 0 ? 'BUYING' : ($volumeFlow < -1 ? 'STRONG_SELLING' : 'SELLING')),
                'Support_Level' => round($supportLevel, 6),
                'Resistance_Level' => round($resistanceLevel, 6),
            ],
            'signal_status' => $signalStatus,
            'risk_management' => $riskLevel ? round($riskLevel, 6) : null,
            'profit_target' => $targetLevel ? round($targetLevel, 6) : null,
            'long_position' => round($longPosition, 6),
            'short_position' => round($shortPosition, 6),
            'long_stop' => round($longStop, 6),
            'long_target' => round($longTarget1, 6),
            'long_target2' => round($longTarget2, 6),
            'long_risk_ratio' => $longRiskRatio,
            'short_stop' => round($shortStop, 6),
            'short_target' => round($shortTarget1, 6),
            'short_target2' => round($shortTarget2, 6),
            'short_risk_ratio' => $shortRiskRatio,
            'success_probability' => $successProbability,
        ];
    }

    protected function calculateSignalQuality($signalData, $bullishSignals, $bearishSignals, $totalSignals): float
    {
        if ($totalSignals === 0) return 0;

        $averageStrength = array_sum($signalData) / $totalSignals;
        $signalConsensus = max($bullishSignals, $bearishSignals) / $totalSignals;

        return min(100, 45 + ($averageStrength * 15) + ($signalConsensus * 40));
    }

    protected function generateAdaptiveSignal($timeframeAnalysis, $currentPrice): string
    {
        $primaryTimeframe = $timeframeAnalysis['1H'] ?? null;
        $secondaryTimeframe = $timeframeAnalysis['4H'] ?? null;
        $tertiaryTimeframe = $timeframeAnalysis['15M'] ?? null;

        $guidance = "⚠️ ADAPTIVE SIGNAL - Dynamic Market Conditions\n";

        if ($primaryTimeframe) {
            $guidance .= "\n🎯 **KiloNova Adaptive Strategy:**\n";

            // Dynamic Long Strategy
            if ($primaryTimeframe['signal_strength'] > 0.1) {
                $guidance .= "📈 **LONG Strategy:**\n";
                $guidance .= "• Entry Zone: " . CurrencyHelper::formatBasedOnStyle($primaryTimeframe['long_position']) . "\n";
                $guidance .= "• Look for price rejection at support with volume confirmation\n";
                $guidance .= "• Momentum should be turning positive (<40)\n";
                $guidance .= "• Trend alignment: Price above slow MA\n";
                $guidance .= "• Primary Target: " . CurrencyHelper::formatBasedOnStyle($primaryTimeframe['long_target']) . " (3:1 RR)\n";
                $guidance .= "• Extended Target: " . CurrencyHelper::formatBasedOnStyle($primaryTimeframe['long_target2']) . " (5:1 RR)\n";
            }

            // Dynamic Short Strategy
            if ($primaryTimeframe['signal_strength'] < -0.1) {
                $guidance .= "📉 **SHORT Strategy:**\n";
                $guidance .= "• Entry Zone: " . CurrencyHelper::formatBasedOnStyle($primaryTimeframe['short_position']) . "\n";
                $guidance .= "• Look for resistance rejection with selling pressure\n";
                $guidance .= "• Momentum should be weakening (>60)\n";
                $guidance .= "• Trend alignment: Price below slow MA\n";
                $guidance .= "• Primary Target: " . CurrencyHelper::formatBasedOnStyle($primaryTimeframe['short_target']) . " (3:1 RR)\n";
                $guidance .= "• Extended Target: " . CurrencyHelper::formatBasedOnStyle($primaryTimeframe['short_target2']) . " (5:1 RR)\n";
            }

            // Critical Levels
            $guidance .= "\n🎯 **Critical Levels:**\n";
            $guidance .= "• Primary Support: $" . number_format($primaryTimeframe['market_data']['Support_Level'], 6) . "\n";
            $guidance .= "• Primary Resistance: $" . number_format($primaryTimeframe['market_data']['Resistance_Level'], 6) . "\n";
            $guidance .= "• Fast MA: $" . number_format($primaryTimeframe['market_data']['Fast_MA'], 6) . "\n";
            $guidance .= "• Slow MA: $" . number_format($primaryTimeframe['market_data']['Slow_MA'], 6) . "\n";

            // Timeframe Confirmation
            if ($secondaryTimeframe && $secondaryTimeframe['signal_strength'] > 0) {
                $guidance .= "• 4H timeframe confirms bullish bias\n";
            } elseif ($secondaryTimeframe && $secondaryTimeframe['signal_strength'] < 0) {
                $guidance .= "• 4H timeframe suggests caution\n";
            }

            if ($tertiaryTimeframe && $tertiaryTimeframe['signal_strength'] > 0.2) {
                $guidance .= "• 15M momentum supports entry\n";
            }

            // Market Timing
            $guidance .= "\n⏰ **Market Timing:**\n";
            $guidance .= "• Optimal: High volume periods (London/NY session overlap)\n";
            $guidance .= "• Avoid: Low liquidity periods (Asian session)\n";
            $guidance .= "• Monitor: Price action around key levels\n";

            // Risk Parameters
            $guidance .= "\n🛡️ **Risk Parameters:**\n";
            $guidance .= "• Position limit: 1.5% of total capital\n";
            $guidance .= "• Stop placement: 1.5x volatility below support\n";
            $guidance .= "• Profit taking: Scale out at 3:1 and 5:1 ratios\n";
            $guidance .= "• Invalidation: Close below slow MA\n";
        }

        return $guidance;
    }

    protected function generateBearishStrategy($timeframeAnalysis, $currentPrice, $signalQuality): string
    {
        $primaryTimeframe = $timeframeAnalysis['1H'] ?? null;
        $secondaryTimeframe = $timeframeAnalysis['4H'] ?? null;
        $tertiaryTimeframe = $timeframeAnalysis['15M'] ?? null;

        $guidance = "🔴 BEARISH - Market weakness detected (Quality: {$signalQuality}%)\n";

        if ($primaryTimeframe) {
            $guidance .= "\n🛡️ **BEARISH MARKET STRATEGY:**\n";

            // Risk Management First
            $guidance .= "⚠️ **PRIMARY ACTIONS:**\n";
            $guidance .= "• REDUCE POSITION SIZES by 50-70%\n";
            $guidance .= "• MOVE TO CASH or stable assets\n";
            $guidance .= "• TIGHTEN STOP LOSSES on existing positions\n";
            $guidance .= "• AVOID new long positions\n";
            $guidance .= "• CONSIDER hedging strategies\n\n";

            // Short Trading Opportunities
            if ($primaryTimeframe['signal_strength'] < -0.5) {
                $guidance .= "📉 **SHORT TRADING OPPORTUNITIES:**\n";
                $guidance .= "• Entry Zone: $" . number_format($primaryTimeframe['short_position'], 6) . "\n";
                $guidance .= "• Look for resistance rejection with volume confirmation\n";
                $guidance .= "• Momentum should be weakening (>60)\n";
                $guidance .= "• Trend alignment: Price below slow MA\n";
                $guidance .= "• Primary Target: $" . number_format($primaryTimeframe['short_target'], 2) . " (3:1 RR)\n";
                $guidance .= "• Extended Target: $" . number_format($primaryTimeframe['short_target2'], 2) . " (5:1 RR)\n";
                $guidance .= "• Risk Management: Use 0.5-1% of capital per trade\n\n";
            }

            // Critical Levels to Watch
            $guidance .= "🎯 **CRITICAL LEVELS TO MONITOR:**\n";
            $guidance .= "• Strong Resistance: $" . number_format($primaryTimeframe['market_data']['Resistance_Level'], 6) . "\n";
            $guidance .= "• Support Breakdown: $" . number_format($primaryTimeframe['market_data']['Support_Level'], 6) . "\n";
            $guidance .= "• Fast MA: $" . number_format($primaryTimeframe['market_data']['Fast_MA'], 6) . " (resistance)\n";
            $guidance .= "• Slow MA: $" . number_format($primaryTimeframe['market_data']['Slow_MA'], 6) . " (major resistance)\n\n";

            // Timeframe Confirmation
            if ($secondaryTimeframe && $secondaryTimeframe['signal_strength'] < -0.3) {
                $guidance .= "• 4H timeframe confirms bearish trend\n";
                $guidance .= "• Consider medium-term short positions\n";
            } elseif ($secondaryTimeframe && $secondaryTimeframe['signal_strength'] > 0) {
                $guidance .= "• 4H timeframe shows mixed signals - wait for confirmation\n";
            }

            if ($tertiaryTimeframe && $tertiaryTimeframe['signal_strength'] < -0.2) {
                $guidance .= "• 15M momentum supports short-term shorts\n";
            }

            // Alternative Strategies
            $guidance .= "🔄 **ALTERNATIVE STRATEGIES:**\n";
            $guidance .= "• WAIT for oversold bounce (RSI < 25) for quick longs\n";
            $guidance .= "• LOOK for failed breakouts to short\n";
            $guidance .= "• CONSIDER pairs trading (long weak vs short strong)\n";
            $guidance .= "• WATCH for trend reversal patterns\n";
            $guidance .= "• USE options for downside protection\n\n";

            // Exit Strategy
            $guidance .= "🚪 **REVERSAL SIGNALS TO WATCH:**\n";
            $guidance .= "• Price closes above Slow MA with volume\n";
            $guidance .= "• Momentum indicator crosses above 50\n";
            $guidance .= "• Bullish divergence on lower timeframes\n";
            $guidance .= "• Volume spike on upside moves\n";
            $guidance .= "• Multiple timeframe alignment for longs\n\n";

            // Risk Parameters
            $guidance .= "🛡️ **ENHANCED RISK MANAGEMENT:**\n";
            $guidance .= "• Maximum position size: 0.5-1% of capital\n";
            $guidance .= "• Stop losses: 1.5x volatility above resistance\n";
            $guidance .= "• Profit taking: Scale out at 3:1 and 5:1 ratios\n";
            $guidance .= "• Daily loss limit: 1-2% of total capital\n";
            $guidance .= "• Weekly review: Reassess market conditions\n\n";

            // Market Psychology
            $guidance .= "🧠 **MARKET PSYCHOLOGY:**\n";
            $guidance .= "• Fear and uncertainty driving selling pressure\n";
            $guidance .= "• Look for panic selling opportunities\n";
            $guidance .= "• Avoid catching falling knives\n";
            $guidance .= "• Be patient for better entries\n";
            $guidance .= "• Consider dollar-cost averaging into strength\n";
        }

        return $guidance;
    }

    protected function createKiloNovaReport($asset, $currentPrice, $timeframeAnalysis, $finalSignal, $signalQuality, $marketTrend, $capitalAmount): string
    {
        $message = "🚀 *KiloNova Signal Engine* - {$asset} (Quality: {$signalQuality}%)\n\n";
        if ($currentPrice) $message .= "💰 Current Price: " . CurrencyHelper::formatBasedOnStyle($currentPrice) . "\n\n";

        foreach (['15M', '1H', '4H'] as $timeframe) {
            $analysis = $timeframeAnalysis[$timeframe] ?? null;
            if (!$analysis) {
                $message .= "**{$timeframe}**: Data unavailable\n\n";
                continue;
            }

            $message .= "**{$timeframe}** (Strength: " . number_format($analysis['signal_strength'], 2) . " | Success: {$analysis['success_probability']}%\n";
            $message .= "- Trend: {$analysis['market_data']['Trend_Direction']}\n";
            $message .= "- Momentum: {$analysis['market_data']['Momentum']}\n";
            $message .= "- Fast MA: $" . number_format($analysis['market_data']['Fast_MA'], 6) . "\n";
            $message .= "- Slow MA: $" . number_format($analysis['market_data']['Slow_MA'], 6) . "\n";
            $message .= "- Trend: {$analysis['market_data']['Trend_Line']} (Signal: {$analysis['market_data']['Trend_Signal']})\n";
            $message .= "- Bands: {$analysis['market_data']['Upper_Band']} / {$analysis['market_data']['Lower_Band']}\n";
            $message .= "- Volume: {$analysis['market_data']['Volume_Flow']}\n";
            $message .= "- Support: $" . number_format($analysis['market_data']['Support_Level'], 6) . "\n";
            $message .= "- Resistance: $" . number_format($analysis['market_data']['Resistance_Level'], 6) . "\n";

            if ($analysis['signal_status'] !== 'NEUTRAL') {
                $message .= "- Status: {$analysis['signal_status']}\n";
                if ($analysis['risk_management']) $message .= "  🛑 Risk: $" . number_format($analysis['risk_management'], 6) . "\n";
                if ($analysis['profit_target']) $message .= "  ✅ Target: $" . number_format($analysis['profit_target'], 6) . "\n";
            }
            $message .= "\n";
        }

        if ($marketTrend !== null) {
            $message .= "📈 Market Trend (24h): {$marketTrend}%\n";
        }

        $message .= "*KiloNova Signal:* {$finalSignal}\n";
        $message .= "*Signal Quality:* {$signalQuality}%\n\n";

        // KiloNova Trading Strategy - Enhanced for Bearish Markets
        $primaryAnalysis = $timeframeAnalysis['1H'] ?? null;
        if ($primaryAnalysis) {
            if ($primaryAnalysis['signal_status'] === 'BULLISH_ENTRY') {
                $message .= "**🎯 KiloNova Trading Strategy (BULLISH):**\n\n";
                $message .= "📈 Entry Zone: " . CurrencyHelper::formatBasedOnStyle($primaryAnalysis['long_position']) . "\n";
                $message .= "🛑 Stop Level: " . CurrencyHelper::formatBasedOnStyle($primaryAnalysis['long_stop']) . "\n";
                $message .= "✅ Target 1 (3:1): " . CurrencyHelper::formatBasedOnStyle($primaryAnalysis['long_target']) . "\n";
                $message .= "🎯 Target 2 (5:1): " . CurrencyHelper::formatBasedOnStyle($primaryAnalysis['long_target2']) . "\n";
                $message .= "📊 Risk-Reward: {$primaryAnalysis['long_risk_ratio']}:1\n";

                // Position sizing calculations
                $entryRisk = $primaryAnalysis['long_position'] - $primaryAnalysis['long_stop'];
                $stopLossAmount = -$entryRisk * ($capitalAmount / $primaryAnalysis['long_position']);
                $target1Profit = ($primaryAnalysis['long_target'] - $primaryAnalysis['long_position']) * ($capitalAmount / $primaryAnalysis['long_position']);
                $target2Profit = ($primaryAnalysis['long_target2'] - $primaryAnalysis['long_position']) * ($capitalAmount / $primaryAnalysis['long_position']);

                $message .= "💸 Risk Amount: " . CurrencyHelper::formatBasedOnStyle($stopLossAmount) . "\n";
                $message .= "💰 Target 1 Profit: " . CurrencyHelper::formatBasedOnStyle($target1Profit) . "\n";
                $message .= "🚀 Target 2 Profit: " . CurrencyHelper::formatBasedOnStyle($target2Profit) . "\n";
                $message .= "🎯 Success Probability: {$primaryAnalysis['success_probability']}%\n\n";
            } elseif ($primaryAnalysis['signal_status'] === 'EXIT_SIGNAL') {
                $message .= "**🛡️ KiloNova Risk Management Strategy:**\n";
                $message .= "⚠️  REDUCE exposure and protect capital\n\n";
                $message .= "📉 Short opportunities available at: " . CurrencyHelper::formatBasedOnStyle($primaryAnalysis['short_position']) . "\n";
                $message .= "🛑 Stop Loss: " . CurrencyHelper::formatBasedOnStyle($primaryAnalysis['short_stop']) . "\n";
                $message .= "✅ Target 1 (3:1): " . CurrencyHelper::formatBasedOnStyle($primaryAnalysis['short_target']) . "\n";
                $message .= "🎯 Target 2 (5:1): " . CurrencyHelper::formatBasedOnStyle($primaryAnalysis['short_target2']) . "\n";
                $message .= "📊 Risk-Reward: {$primaryAnalysis['short_risk_ratio']}:1\n\n";
            }
        }

        $message .= "**⚡ KiloNova Engine Features:**\n";
        $message .= "• Adaptive moving averages with dynamic periods\n";
        $message .= "• KiloNova volatility bands for optimal entries\n";
        $message .= "• Multi-timeframe momentum analysis\n";
        $message .= "• Volume flow confirmation system\n";
        $message .= "• Dynamic risk-reward optimization\n";
        $message .= "• Adaptive signal quality scoring\n\n";

        return $message;
    }

    protected function buildEnhancedMessage($symbol, $priceMain, $tfBlocks, $overall, $confidence, $trendChange, $positionUSD): string
    {
        $message = "🚀 *Enhanced {$symbol} Signal* (Win Rate: {$confidence}%)\n\n";
        if ($priceMain) $message .= "💰 Current Price: " . CurrencyHelper::formatBasedOnStyle($priceMain) . "\n\n";

        foreach (['H1', 'H4', '1D'] as $lab) {
            $r = $tfBlocks[$lab] ?? null;
            if (!$r) {
                $message .= "**{$lab}**: Insufficient data\n\n";
                continue;
            }

            $message .= "**{$lab}** (Score: " . number_format($r['score'], 1) . " | Win Rate: {$r['probability']}%)\n";
            $message .= "- Trend: {$r['summary']['Trend']}\n";
            $message .= "- RSI: {$r['summary']['RSI']}\n";
            $message .= "- EMA{$this->ema50Period}: {$r['summary']['EMA50']}\n";
            $message .= "- EMA{$this->ema200Period}: {$r['summary']['EMA200']}\n";
            $message .= "- MACD: {$r['summary']['MACD']} (Hist: {$r['summary']['Histogram']})\n";
            $message .= "- BB: {$r['summary']['Upper BB']} / {$r['summary']['Lower BB']}\n";
            $message .= "- Volume: {$r['summary']['Volume']}\n";
            $message .= "- Support: $" . number_format($r['summary']['Support'], 6) . "\n";
            $message .= "- Resistance: $" . number_format($r['summary']['Resistance'], 6) . "\n";

            if ($r['signal'] !== 'Hold') {
                $message .= "- Signal: {$r['signal']}\n";
                if ($r['stopLoss']) $message .= "  🛑 SL: $" . number_format($r['stopLoss'], 6) . "\n";
                if ($r['takeProfit']) $message .= "  ✅ TP: $" . number_format($r['takeProfit'], 6) . "\n";
            }
            $message .= "\n";
        }

        if ($trendChange !== null) {
            $message .= "📈 Coingecko 24h Change: {$trendChange}%\n";
        }

        $message .= "*Overall Signal:* {$overall}\n";
        $message .= "*Confidence Level:* {$confidence}%\n\n";

        // Enhanced trading plan
        $h1 = $tfBlocks['H1'] ?? null;
        if ($h1 && $h1['signal'] === 'Strong Entry') {
            $message .= "**🎯 ENHANCED TRADING PLAN (H1):**\n";
            $message .= "📈 LONG Entry: $" . number_format($h1['longEntry'], 2, '.', '') . "\n";
            $message .= "🛑 Stop Loss: $" . number_format($h1['longSL'], 2, '.', '') . "\n";
            $message .= "✅ TP1 (2.5:1): $" . number_format($h1['longTP'], 2, '.', '') . "\n";
            $message .= "🎯 TP2 (4:1): $" . number_format($h1['longTP2'], 2, '.', '') . "\n";
            $message .= "📊 Risk-Reward: {$h1['longRR']}:1\n";

            // P/L calculations
            $riskUSDLong = $h1['longEntry'] - $h1['longSL'];
            $slPLUSDLong = -$riskUSDLong * ($positionUSD / $h1['longEntry']);
            $tp1PLUSDLong = ($h1['longTP'] - $h1['longEntry']) * ($positionUSD / $h1['longEntry']);
            $tp2PLUSDLong = ($h1['longTP2'] - $h1['longEntry']) * ($positionUSD / $h1['longEntry']);

            $message .= "💸 SL Risk: $" . number_format($slPLUSDLong, 2, '.', '') . "\n";
            $message .= "💰 TP1 Profit: $" . number_format($tp1PLUSDLong, 2, '.', '') . "\n";
            $message .= "🚀 TP2 Profit: $" . number_format($tp2PLUSDLong, 2, '.', '') . "\n";
            $message .= "🎯 Success Rate: {$h1['probability']}%\n\n";
        }

        $message .= "**⚡ Enhanced Signal Features:**\n";
        $message .= "• Multi-timeframe analysis with weighted scoring\n";
        $message .= "• Fibonacci proximity detection\n";
        $message .= "• Volume confirmation\n";
        $message .= "• Bollinger Band position analysis\n";
        $message .= "• Enhanced risk-reward ratios\n";
        $message .= "• Multiple take profit targets\n\n";

        return $message;
    }

    // KiloNova Original Implementation Methods
    protected function getAssetPrecision(string $asset): float {
        try {
            $exchangeInfo = Http::timeout(15)
                ->retry(3, 1000)
                ->get($this->dataSource . "/api/v3/exchangeInfo");

            if ($exchangeInfo->successful()) {
                $data = $exchangeInfo->json();
                foreach ($data['symbols'] as $symbol) {
                    if ($symbol['symbol'] === $asset) {
                        foreach ($symbol['filters'] as $filter) {
                            if ($filter['filterType'] === 'PRICE_FILTER') {
                                return floatval($filter['tickSize']);
                            }
                        }
                    }
                }
            } else {
                $this->warn("Failed to fetch exchange info. Status: " . $exchangeInfo->status());
            }
        } catch (\Exception $e) {
            $this->warn("Exchange info request failed: " . $e->getMessage());
        }

        // Fallback precision values for common assets
        $fallbackPrecisions = [
            'BTCUSDT' => 0.01,
            'ETHUSDT' => 0.01,
            'BNBUSDT' => 0.0001,
            'ADAUSDT' => 0.00001,
            'XRPUSDT' => 0.00001,
            'SOLUSDT' => 0.001,
            'DOTUSDT' => 0.001,
            'AVAXUSDT' => 0.001,
            'MATICUSDT' => 0.0001,
            'LINKUSDT' => 0.001,
        ];

        return $fallbackPrecisions[$asset] ?? 0.01;
    }

    protected function fetchMarketData(string $asset, string $interval, int $limit): ?array
    {
        $maxRetries = 3;
        $retryDelay = 2000; // 2 seconds
        $dataSources = [$this->dataSource];

        // Add fallback source if option is enabled
        if ($this->option('fallback')) {
            $dataSources[] = $this->fallbackSource;
        }

        foreach ($dataSources as $currentSource) {
            $this->info("Trying data source: {$currentSource}");

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $url = "{$currentSource}/api/v3/klines?symbol={$asset}&interval={$interval}&limit={$limit}";
                    $response = Http::withoutVerifying()
                        ->timeout(20)
                        ->retry(2, 1000)
                        ->get($url);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (is_array($data) && count($data) > 0) {
                            $this->info("✅ Successfully fetched data from {$currentSource}");
                            return $data;
                        } else {
                            $this->warn("Empty or invalid data from {$currentSource} for {$asset} {$interval} (attempt {$attempt})");
                        }
                    } else {
                        $this->warn("API request failed from {$currentSource} for {$asset} {$interval}. Status: " . $response->status() . " (attempt {$attempt})");
                    }
                } catch (\Exception $e) {
                    $this->warn("Network error from {$currentSource} for {$asset} {$interval} (attempt {$attempt}): " . $e->getMessage());

                    if ($attempt < $maxRetries) {
                        $this->info("Retrying in {$retryDelay}ms...");
                        usleep($retryDelay * 1000); // Convert to microseconds
                        $retryDelay *= 1.5; // Exponential backoff
                    }
                }
            }

            $this->warn("❌ All attempts failed for data source: {$currentSource}");
            $retryDelay = 2000; // Reset delay for next source
        }

        $this->error("Failed to fetch market data for {$asset} {$interval} from all available sources");
        Log::error("Market Data Fetch Failed", [
            'asset' => $asset,
            'interval' => $interval,
            'max_retries' => $maxRetries,
            'data_sources' => $dataSources
        ]);

        return null;
    }

    protected function dispatchSignal($text) {
        if (!$this->messagingToken || !$this->messagingChannel) {
            $this->warn("Messaging credentials not configured");
            return;
        }

        $url = "https://api.telegram.org/bot{$this->messagingToken}/sendMessage";
        $data = [
            'chat_id' => $this->messagingChannel,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];

        $response = Http::timeout(10)->post($url, $data);
        if (!$response->successful()) {
            $this->error("Failed to send signal: " . $response->status());
        }
    }

    protected function getMarketTrend($asset) {
        try {
            $base = str_replace('USDT', '', $asset);
            $url = "https://api.coingecko.com/api/v3/simple/price?ids={$base}&vs_currencies=usd&include_24hr_change=true";

            $response = Http::timeout(15)
                ->retry(2, 2000)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data[$base]['usd_24h_change'])) {
                    return round($data[$base]['usd_24h_change'], 2);
                } else {
                    $this->warn("No 24h change data found for {$base}");
                }
            } else {
                $this->warn("CoinGecko API request failed. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->warn("CoinGecko API error: " . $e->getMessage());
        }

        return null;
    }

    // KiloNova Original Technical Analysis Methods
    protected function calculateAdaptiveMA($prices, $period) {
        if (count($prices) < $period) return null;
        $weights = range(1, $period);
        $weightedSum = 0;
        $weightTotal = array_sum($weights);

        for ($i = 0; $i < $period; $i++) {
            $weightedSum += $prices[count($prices) - 1 - $i] * $weights[$i];
        }

        return $weightedSum / $weightTotal;
    }

    protected function calculateKiloNovaMomentum($prices, $period) {
        if (count($prices) < $period + 1) return null;
        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i-1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        $avgGain = array_sum(array_slice($gains, -$period)) / $period;
        $avgLoss = array_sum(array_slice($losses, -$period)) / $period;

        if ($avgLoss == 0) return 100;
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    protected function calculateKiloNovaTrend($prices, $fast, $slow, $signal) {
        $fastEMA = $this->calculateEMA($prices, $fast);
        $slowEMA = $this->calculateEMA($prices, $slow);
        $trend = [];
        $minLen = min(count($fastEMA), count($slowEMA));

        for ($i = 0; $i < $minLen; $i++) {
            $trend[] = $fastEMA[$i] - $slowEMA[$i];
        }

        $signalLine = $this->calculateEMA($trend, $signal);
        $histogram = [];
        $minLen2 = min(count($trend), count($signalLine));

        for ($i = 0; $i < $minLen2; $i++) {
            $histogram[] = $trend[$i] - $signalLine[$i];
        }

        return ['trend' => $trend, 'signal' => $signalLine, 'histogram' => $histogram];
    }

    protected function calculateKiloNovaBands($prices, $period, $multiplier) {
        if (count($prices) < $period) return ['upper' => [], 'middle' => [], 'lower' => []];

        $middle = [];
        for ($i = $period - 1; $i < count($prices); $i++) {
            $slice = array_slice($prices, $i - $period + 1, $period);
            $middle[] = array_sum($slice) / $period;
        }

        $upper = [];
        $lower = [];
        for ($i = 0; $i < count($middle); $i++) {
            $slice = array_slice($prices, $i, $period);
            $variance = 0;
            foreach ($slice as $price) {
                $variance += pow($price - $middle[$i], 2);
            }
            $variance /= $period;
            $std = sqrt($variance);
            $upper[] = $middle[$i] + $multiplier * $std;
            $lower[] = $middle[$i] - $multiplier * $std;
        }

        return ['upper' => $upper, 'middle' => $middle, 'lower' => $lower];
    }

    protected function calculateKiloNovaVolatility($marketData, $period) {
        if (count($marketData) < $period + 1) return null;
        $trueRanges = [];

        for ($i = 1; $i < count($marketData); $i++) {
            $high = floatval($marketData[$i][2]);
            $low = floatval($marketData[$i][3]);
            $prevClose = floatval($marketData[$i-1][4]);
            $trueRanges[] = max($high - $low, abs($high - $prevClose), abs($low - $prevClose));
        }

        $slice = array_slice($trueRanges, -$period);
        return array_sum($slice) / count($slice);
    }

    protected function calculateVolumeAverage($volumes) {
        if (empty($volumes)) return 0;
        return array_sum($volumes) / count($volumes);
    }

    protected function checkProximity($price, $level1, $level2) {
        $distance1 = abs($price - $level1) / max($price, 1) * 100;
        $distance2 = abs($price - $level2) / max($price, 1) * 100;
        return min($distance1, $distance2) <= 1.5;
    }

    protected function analyzeMarketStructure($closes, $highs, $lows) {
        $recent = array_slice($closes, -10);
        $older = array_slice($closes, -20, 10);

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);

        $structure = ($recentAvg > $olderAvg) ? 1 : -1;
        $strength = abs($recentAvg - $olderAvg) / $olderAvg * 100;

        return $structure * min($this->adaptiveWeights['market_structure'], $strength / 15);
    }

    protected function calculateOptimalEntry($level, $precision) {
        return round($level / $precision) * $precision;
    }

    protected function calculateKiloNovaProbability($score, $momentum, $trendHist, $volumeFlow, $volatilityPos) {
        $baseProb = 50;

        $scoreBonus = min(25, max(-25, $score * 4));
        $momentumBonus = ($momentum < 30) ? 15 : (($momentum > 70) ? -15 : 0);
        $trendBonus = ($trendHist > 0) ? 12 : -12;
        $volumeBonus = $volumeFlow * 6;
        $volatilityBonus = $volatilityPos * 8;

        $totalBonus = $scoreBonus + $momentumBonus + $trendBonus + $volumeBonus + $volatilityBonus;

        return max(5, min(95, $baseProb + $totalBonus));
    }

    protected function calculateEMA($prices, $period) {
        if (count($prices) < $period) return [];
        $multiplier = 2 / ($period + 1);
        $ema = [];
        $ema[0] = $prices[0];

        for ($i = 1; $i < count($prices); $i++) {
            $ema[$i] = ($prices[$i] - $ema[$i-1]) * $multiplier + $ema[$i-1];
        }

        return $ema;
    }
}