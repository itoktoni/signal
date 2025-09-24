<?php

namespace App\Services;

use App\Models\TradingPlan;
use Illuminate\Support\Facades\Http;
use App\Helpers\CurrencyHelper;

class TradingPlanService
{
    protected float $usdToIdr;
    protected float $makerFee;
    protected float $cfxFee;

    public function __construct()
    {
        $this->usdToIdr = env('USD_TO_IDR', 16000);
        $this->makerFee = 0.0010; // 0.10%
        $this->cfxFee = 0.000222; // 0.0222%

        // Initialize currency helper with exchange rate
        CurrencyHelper::setExchangeRate($this->usdToIdr);
    }

    public function createTradingPlan(array $data): TradingPlan
    {
        // Calculate costs
        $costs = $this->calculateCosts($data['position_size']);

        // Calculate risk-reward ratio
        $riskRewardRatio = $this->calculateRiskRewardRatio(
            $data['entry_price'],
            $data['stop_loss'],
            $data['take_profit'],
            $data['position_type']
        );

        // Calculate success rate based on analysis
        $successRate = $this->calculateSuccessRate($data);

        // Calculate net RR
        $netRR = $this->calculateNetRR(
            $data['entry_price'],
            $data['stop_loss'],
            $data['take_profit'],
            $data['position_type'],
            $costs,
            $data['position_size']
        );

        $tradingPlan = TradingPlan::create([
            'symbol' => strtoupper($data['symbol']),
            'position_type' => $data['position_type'],
            'entry_price' => $data['entry_price'],
            'stop_loss' => $data['stop_loss'],
            'take_profit' => $data['take_profit'],
            'risk_reward_ratio' => $riskRewardRatio,
            'success_rate' => $successRate,
            'position_size' => $data['position_size'],
            'costs' => $costs,
            'net_rr' => $netRR,
            'timeframe' => $data['timeframe'] ?? '1h',
            'status' => 'active',
            'analysis_data' => $data['analysis_data'] ?? null,
            'user_id' => $data['user_id'] ?? null,
        ]);

        return $tradingPlan;
    }

    public function calculateCosts(float $positionSize): float
    {
        $makerFeeAmount = $positionSize * $this->makerFee;
        $cfxFeeAmount = $positionSize * $this->cfxFee;
        return $makerFeeAmount + $cfxFeeAmount;
    }

    public function calculateRiskRewardRatio(
        float $entryPrice,
        float $stopLoss,
        float $takeProfit,
        string $positionType
    ): float {
        $risk = abs($entryPrice - $stopLoss);
        $reward = abs($entryPrice - $takeProfit);

        if ($risk == 0) return 0;

        return round($reward / $risk, 2);
    }

    public function calculateSuccessRate(array $data): float
    {
        $baseRate = 50.0;

        // Adjust based on risk-reward ratio
        $rrRatio = $this->calculateRiskRewardRatio(
            $data['entry_price'],
            $data['stop_loss'],
            $data['take_profit'],
            $data['position_type']
        );

        if ($rrRatio >= 2.0) $baseRate += 10;
        elseif ($rrRatio >= 1.5) $baseRate += 5;
        elseif ($rrRatio < 1.0) $baseRate -= 10;

        // Adjust based on analysis data if available
        if (isset($data['analysis_data'])) {
            $analysis = $data['analysis_data'];

            // Trend strength
            if (isset($analysis['trend_score'])) {
                $baseRate += $analysis['trend_score'] * 5;
            }

            // Volume confirmation
            if (isset($analysis['volume_score']) && $analysis['volume_score'] > 0) {
                $baseRate += 5;
            }

            // RSI confirmation
            if (isset($analysis['rsi_score'])) {
                $baseRate += $analysis['rsi_score'] * 3;
            }
        }

        return max(0, min(100, $baseRate));
    }

    public function calculateNetRR(
        float $entryPrice,
        float $stopLoss,
        float $takeProfit,
        string $positionType,
        float $costs,
        float $positionSize
    ): float {
        $risk = abs($entryPrice - $stopLoss);
        if ($risk == 0) return 0;

        $reward = abs($entryPrice - $takeProfit);
        $feeImpact = $costs / $positionSize;

        return round(($reward / $risk) * (1 - $feeImpact), 2);
    }

    public function getTradingPlanSummary(TradingPlan $tradingPlan): array
    {
        $riskAmount = $tradingPlan->risk_amount;
        $potentialProfit = $tradingPlan->potential_profit;
        $netProfit = $tradingPlan->net_profit;

        return [
            'id' => $tradingPlan->id,
            'symbol' => $tradingPlan->symbol,
            'position_type' => $tradingPlan->position_type,
            'entry_price' => $tradingPlan->entry_price,
            'entry_price_formatted' => CurrencyHelper::formatBasedOnStyle($tradingPlan->entry_price),
            'stop_loss' => $tradingPlan->stop_loss,
            'stop_loss_formatted' => CurrencyHelper::formatBasedOnStyle($tradingPlan->stop_loss),
            'take_profit' => $tradingPlan->take_profit,
            'take_profit_formatted' => CurrencyHelper::formatBasedOnStyle($tradingPlan->take_profit),
            'risk_reward_ratio' => $tradingPlan->risk_reward_ratio,
            'success_rate' => $tradingPlan->success_rate,
            'position_size' => $tradingPlan->position_size,
            'position_size_formatted' => CurrencyHelper::formatTradingAmount($tradingPlan->position_size),
            'costs' => $tradingPlan->costs,
            'costs_formatted' => CurrencyHelper::formatBasedOnStyle($tradingPlan->costs),
            'net_rr' => $tradingPlan->net_rr,
            'timeframe' => $tradingPlan->timeframe,
            'status' => $tradingPlan->status,
            'risk_amount' => $riskAmount,
            'risk_amount_formatted' => CurrencyHelper::formatBasedOnStyle($riskAmount),
            'potential_profit' => $potentialProfit,
            'potential_profit_formatted' => CurrencyHelper::formatBasedOnStyle($potentialProfit),
            'net_profit' => $netProfit,
            'net_profit_formatted' => CurrencyHelper::formatBasedOnStyle($netProfit),
            'created_at' => $tradingPlan->created_at,
            'analysis_data' => $tradingPlan->analysis_data,
        ];
    }

    public function updateTradingPlan(TradingPlan $tradingPlan, array $data): TradingPlan
    {
        $updateData = [];

        if (isset($data['entry_price'])) $updateData['entry_price'] = $data['entry_price'];
        if (isset($data['stop_loss'])) $updateData['stop_loss'] = $data['stop_loss'];
        if (isset($data['take_profit'])) $updateData['take_profit'] = $data['take_profit'];
        if (isset($data['position_size'])) $updateData['position_size'] = $data['position_size'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];

        if (!empty($updateData)) {
            $tradingPlan->update($updateData);

            // Recalculate derived values
            $tradingPlan->updateNetRR();
        }

        return $tradingPlan->fresh();
    }

    public function getTickSize(string $symbol): float
    {
        $binanceApi = env('BINANCE_API', 'https://data-api.binance.vision');
        $exinfo = Http::timeout(10)->get($binanceApi . "/api/v3/exchangeInfo");

        if ($exinfo->successful()) {
            $data = $exinfo->json();
            foreach ($data['symbols'] as $s) {
                if ($s['symbol'] === $symbol) {
                    foreach ($s['filters'] as $f) {
                        if ($f['filterType'] === 'PRICE_FILTER') {
                            return floatval($f['tickSize']);
                        }
                    }
                }
            }
        }
        return 0.01; // default
    }

    public function roundToTickSize(float $price, float $tickSize): float
    {
        return round($price / $tickSize) * $tickSize;
    }
}