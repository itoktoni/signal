<?php

namespace App\Services;

use App\Services\AnalysisInterface;
use App\Helpers\CurrencyHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AnalysisService implements AnalysisInterface
{
    protected float $usdToIdr;
    protected array $config;

    public function __construct()
    {
        $this->usdToIdr = env('USD_TO_IDR', 16000);
        CurrencyHelper::setExchangeRate($this->usdToIdr);

        // Load configuration from config/crypto.php
        $this->config = config('crypto');
    }

    /**
     * Get the exchange rate service
     */
    protected function getExchangeRate(): float
    {
        return $this->usdToIdr;
    }

    /**
     * Format price in both USD and Rupiah
     */
    protected function formatPrice(float $amount): array
    {
        return [
            'usd' => $amount,
            'rupiah' => $amount * $this->usdToIdr,
            'formatted' => [
                'usd' => CurrencyHelper::formatUSD($amount),
                'rupiah' => CurrencyHelper::formatIDR($amount * $this->usdToIdr),
                'both' => CurrencyHelper::formatUSDIDR($amount)
            ]
        ];
    }

    /**
     * Calculate fees for a trade (Indonesian market context - Pluang PRO structure)
     */
    protected function calculateFees(float $positionSize, string $orderType = 'taker'): array
    {
        // Based on Pluang PRO fee structure:
        // Maker fee: 0.10% + PPN 0.011% = 0.111%
        // Taker fee: 0.15% + PPN 0.011% = 0.161%

        if ($orderType === 'maker') {
            $baseFee = $positionSize * 0.0010; // 0.10% maker fee
            $ppn = $positionSize * 0.00011; // 0.011% PPN
            $tradingFee = $baseFee + $ppn;
        } else { // taker (default)
            $baseFee = $positionSize * 0.0015; // 0.15% taker fee
            $ppn = $positionSize * 0.00011; // 0.011% PPN
            $tradingFee = $baseFee + $ppn;
        }

        // Slippage (estimated)
        $slippage = $positionSize * 0.005; // 0.5% slippage

        $totalFees = $tradingFee + $slippage;

        $formattedFees = $this->formatPrice($totalFees);

        return [
            'base_fee' => $baseFee, // 0.10% or 0.15%
            'ppn' => $ppn, // 0.011% PPN
            'slippage' => $slippage, // 0.5% - Estimated slippage
            'trading_fee' => $tradingFee, // Total trading fee (base + PPN)
            'total' => $totalFees,
            'formatted' => $formattedFees['formatted'],
            'description' => 'Biaya ' . ($orderType === 'maker' ? 'maker 0,10%' : 'taker 0,15%') . ' + PPN 0,011% + slippage 0,5%'
        ];
    }

    /**
     * Calculate potential profit/loss
     */
    protected function calculatePotentialPL(
        float $entryPrice,
        float $stopLoss,
        float $takeProfit,
        float $positionSize,
        string $positionType
    ): array {
        // Prevent division by zero
        if ($entryPrice <= 0) {
            return [
                'potential_loss' => 0,
                'potential_profit' => 0,
                'formatted' => [
                    'potential_loss' => 'Invalid entry price',
                    'potential_profit' => 'Invalid entry price'
                ]
            ];
        }

        $risk = abs($entryPrice - $stopLoss);
        $reward = abs($entryPrice - $takeProfit);

        // Default to taker fees for profit/loss calculations
        $fees = $this->calculateFees($positionSize, 'taker');

        // Calculate profit/loss without fees first
        if ($positionType === 'long') {
            $potentialLoss = -$risk * ($positionSize / $entryPrice);
            $potentialProfit = ($takeProfit - $entryPrice) * ($positionSize / $entryPrice);
        } else { // short
            $potentialLoss = -$risk * ($positionSize / $entryPrice);
            $potentialProfit = ($entryPrice - $takeProfit) * ($positionSize / $entryPrice);
        }

        // Apply fees to both profit and loss (more realistic)
        $potentialProfit = $potentialProfit - $fees['total'];
        $potentialLoss = $potentialLoss - $fees['total'];

        // Ensure loss is always negative (but not more negative than position size)
        $potentialLoss = min($potentialLoss, 0);

        // Debug logging
        if (config('app.debug')) {
            Log::info("Profit/Loss Calculation Debug", [
                'position_type' => $positionType,
                'entry_price' => $entryPrice,
                'stop_loss' => $stopLoss,
                'take_profit' => $takeProfit,
                'position_size' => $positionSize,
                'risk' => $risk,
                'reward' => $reward,
                'fees_total' => $fees['total'],
                'potential_loss' => $potentialLoss,
                'potential_profit' => $potentialProfit,
                'raw_profit' => $positionType === 'long' ? ($takeProfit - $entryPrice) * ($positionSize / $entryPrice) : ($entryPrice - $takeProfit) * ($positionSize / $entryPrice)
            ]);
        }

        $formattedLoss = $this->formatPrice(abs($potentialLoss)); // Use absolute for display
        $formattedProfit = $this->formatPrice($potentialProfit);

        return [
            'potential_loss' => $potentialLoss,
            'potential_profit' => $potentialProfit,
            'formatted' => [
                'potential_loss' => $formattedLoss['formatted']['both'],
                'potential_profit' => $formattedProfit['formatted']['both']
            ]
        ];
    }

    /**
     * Get tick size for a symbol
     */
    protected function getTickSize(string $symbol): float
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

    /**
     * Round price to tick size
     */
    protected function roundToTickSize(float $price, float $tickSize): float
    {
        return round($price / $tickSize) * $tickSize;
    }

    /**
     * Format the result object according to the specification
     */
    protected function formatResult(
        string $title,
        string $signal,
        float $confidence,
        float $entry,
        float $stopLoss,
        float $takeProfit,
        float $riskReward,
        float $positionSize
    ): object {
        $entryPrices = $this->formatPrice($entry);
        $stopLossPrices = $this->formatPrice($stopLoss);
        $takeProfitPrices = $this->formatPrice($takeProfit);
        // Default to taker fees for result formatting
        $fees = $this->calculateFees($positionSize, 'taker');
        $pl = $this->calculatePotentialPL($entry, $stopLoss, $takeProfit, $positionSize, $signal);

        return (object) [
            'title' => $title,
            'signal' => $signal, // long or short
            'confidence' => $confidence, // 95% (calculate any function that show percentage)
            'entry' => $entryPrices, // USD and Rupiah
            'stop_loss' => $stopLossPrices, // USD and Rupiah
            'take_profit' => $takeProfitPrices, // USD and Rupiah
            'risk_reward' => $riskReward, // 1:3 (risk:reward)
            'fee' => $fees, // USD and Rupiah (slippage 0.5%, taker 0,15%, maker 0,10%, tax 0,21%, CFX 0,0222%)
            'potential_profit' => $this->formatPrice($pl['potential_profit']), // USD and Rupiah after fee
            'potential_loss' => $this->formatPrice($pl['potential_loss']), // USD and Rupiah after fee
            'formatted' => [
                'entry' => $entryPrices['formatted']['both'],
                'stop_loss' => $stopLossPrices['formatted']['both'],
                'take_profit' => $takeProfitPrices['formatted']['both'],
                'fee' => $fees['formatted']['both'],
                'potential_profit' => $this->formatPrice($pl['potential_profit'])['formatted']['both'],
                'potential_loss' => $this->formatPrice($pl['potential_loss'])['formatted']['both']
            ]
        ];
    }
}