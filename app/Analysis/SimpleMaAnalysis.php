<?php

namespace App\Analysis;

class SimpleMaAnalysis implements AnalysisInterface
{
    protected array $indicators = [];
    protected string $notes = '';
    protected ?ApiProviderInterface $apiProvider = null;
    protected float $currentPrice = 0.0;

    public function __construct(ApiProviderInterface $apiProvider)
    {
        $this->apiProvider = $apiProvider;
    }

    public function setApiProvider(ApiProviderInterface $apiProvider): void
    {
        $this->apiProvider = $apiProvider;
    }

    public function analyze(string $symbol, float $amount = 100, string $timeframe = '1h', ?string $forcedApi = null): object
    {
        if (!$this->apiProvider) {
            throw new \Exception('API Provider not set');
        }

        $historicalData = $this->apiProvider->getHistoricalData($symbol, $timeframe, 150);
        if (empty($historicalData)) {
            throw new \Exception('No historical data available');
        }

        $this->currentPrice = $this->apiProvider->getCurrentPrice($symbol);

        $closePrices = array_map(fn($candle) => (float)$candle[4], $historicalData);

        // Indicators
        $ma20 = $this->calculateSMA($closePrices, 20);
        $ma50 = $this->calculateSMA($closePrices, 50);
        $ema20 = $this->calculateEMA($closePrices, 20);
        $ema50 = $this->calculateEMA($closePrices, 50);
        $atr14 = $this->calculateATR($historicalData, 14);

        $signal = $this->getSignal($ma20, $ma50, $ema20, $ema50, $closePrices);
        $suggestedEntry = $this->calculateSuggestedEntry($signal, $this->currentPrice);
        $levels = $this->calculateLevels($signal, $suggestedEntry, $ma20, $ma50, $atr14);

        $confidence = $this->calculateConfidence($signal, $ma20, $ma50, $ema20, $ema50);

        $this->indicators = [
            'MA20' => round($ma20, 4),
            'MA50' => round($ma50, 4),
            'EMA20' => round($ema20, 4),
            'EMA50' => round($ema50, 4),
            'ATR14' => round($atr14, 4),
            'Current_Price' => round($this->currentPrice, 4),
            'Suggested_Entry' => round($suggestedEntry, 4),
        ];

        return (object)[
            'title' => "Improved MA Analysis for {$symbol} ({$timeframe})",
            'description' => $this->getDescription(),
            'signal' => $signal,
            'confidence' => $confidence,
            'entry' => $suggestedEntry,
            'price' => $this->currentPrice,
            'stop_loss' => $levels['stop_loss'],
            'take_profit' => $levels['take_profit'],
            'risk_reward' => $levels['risk_reward'],
            'indicators' => $this->indicators,
            'notes' => $this->notes
        ];
    }

    public function getCode(): string
    {
        return 'improved_ma';
    }

    public function getName(): string
    {
        return 'Improved MA/EMA Analysis';
    }

    public function getDescription(): string
    {
        return 'Improved Moving Average analysis using SMA (20/50), EMA (20/50), and ATR(14) for risk management. '
            . 'Signals are based on crossovers with trend confirmation.';
    }

    public function getIndicators(): array
    {
        return $this->indicators;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    private function calculateSMA(array $prices, int $period): float
    {
        if (count($prices) < $period) {
            return end($prices);
        }
        return array_sum(array_slice($prices, -$period)) / $period;
    }

    private function calculateEMA(array $prices, int $period): float
    {
        if (count($prices) < $period) {
            return end($prices);
        }

        $k = 2 / ($period + 1);
        $ema = $this->calculateSMA(array_slice($prices, 0, $period), $period);

        for ($i = $period; $i < count($prices); $i++) {
            $ema = ($prices[$i] * $k) + ($ema * (1 - $k));
        }

        return $ema;
    }

    private function calculateATR(array $candles, int $period): float
    {
        if (count($candles) < $period + 1) {
            return 0.0;
        }

        $trs = [];
        for ($i = 1; $i < count($candles); $i++) {
            $high = (float)$candles[$i][2];
            $low = (float)$candles[$i][3];
            $prevClose = (float)$candles[$i - 1][4];

            $tr = max([
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose),
            ]);
            $trs[] = $tr;
        }

        return $this->calculateSMA($trs, $period);
    }

    private function getSignal(float $ma20, float $ma50, float $ema20, float $ema50, array $prices): string
    {
        $price = end($prices);

        if ($ma20 > $ma50 && $ema20 > $ema50 && $price > $ma20) {
            $this->notes = "Strong bullish alignment (MA20 > MA50, EMA20 > EMA50, price > MA20)";
            return 'BUY';
        }
        if ($ma20 < $ma50 && $ema20 < $ema50 && $price < $ma20) {
            $this->notes = "Strong bearish alignment (MA20 < MA50, EMA20 < EMA50, price < MA20)";
            return 'SELL';
        }

        $this->notes = "Mixed signals, trend unclear";
        return 'NEUTRAL';
    }

    private function calculateSuggestedEntry(string $signal, float $currentPrice): float
    {
        $buffer = 0.001;
        return match ($signal) {
            'BUY' => $currentPrice * (1 + $buffer),
            'SELL' => $currentPrice * (1 - $buffer),
            default => $currentPrice,
        };
    }

    private function calculateLevels(string $signal, float $entryPrice, float $ma20, float $ma50, float $atr): array
    {
        $atr = max($atr, $entryPrice * 0.01); // fallback ATR = 1% jika data kurang

        if ($signal === 'BUY') {
            $stopLoss = $entryPrice - (1.5 * $atr);
            $takeProfit = $entryPrice + (3 * $atr);
        } elseif ($signal === 'SELL') {
            $stopLoss = $entryPrice + (1.5 * $atr);
            $takeProfit = $entryPrice - (3 * $atr);
        } else {
            $stopLoss = $entryPrice - (1.2 * $atr);
            $takeProfit = $entryPrice + (1.2 * $atr);
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

    private function calculateConfidence(string $signal, float $ma20, float $ma50, float $ema20, float $ema50): int
    {
        if ($signal === 'NEUTRAL') {
            return 50;
        }

        $score = 60;
        if ($ma20 > $ma50 && $ema20 > $ema50) {
            $score += 20;
        } elseif ($ma20 < $ma50 && $ema20 < $ema50) {
            $score += 20;
        }

        return min($score, 90);
    }
}
