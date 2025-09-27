<?php

namespace App\Analysis;

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
     * Calculate fees for a trade (Indonesian market context)
     */
    protected function calculateFees(float $positionSize, string $orderType = 'taker'): array
    {
        // Based on Pluang PRO fee structure for Kripto Futures:
        // Maker fee: 0.10% + PPN 0.011% + CFX 0.05% + PPN on CFX 0.0055% = 0.15561%
        // Taker fee: 0.10% + PPN 0.011% + CFX 0.15% + PPN on CFX 0.0165% = 0.26661%

        $baseFee = $positionSize * 0.0010; // 0.10% base transaction fee
        $ppnOnBase = $positionSize * 0.00011; // 0.011% PPN on transaction fee

        if ($orderType === 'maker') {
            $cfxFee = $positionSize * 0.0005; // 0.05% CFX fee (maker)
            $ppnOnCfx = $positionSize * 0.000055; // 0.0055% PPN on CFX fee
            $feeDescription = 'maker 0,10% + PPN 0,011% + CFX 0,05% + PPN on CFX 0,0055%';
        } else { // taker (default)
            $cfxFee = $positionSize * 0.0015; // 0.15% CFX fee (taker)
            $ppnOnCfx = $positionSize * 0.000165; // 0.0165% PPN on CFX fee
            $feeDescription = 'taker 0,10% + PPN 0,011% + CFX 0,15% + PPN on CFX 0,0165%';
        }

        $tradingFee = $baseFee + $ppnOnBase + $cfxFee + $ppnOnCfx;

        // Slippage (estimated)
        $slippage = $positionSize * 0.005; // 0.5% slippage

        $totalFees = $tradingFee + $slippage;

        $formattedFees = $this->formatPrice($totalFees);

        return [
            'base_fee' => $baseFee, // 0.10% base transaction fee
            'ppn_on_base' => $ppnOnBase, // 0.011% PPN on transaction fee
            'cfx_fee' => $cfxFee, // 0.05% or 0.15% CFX fee (maker/taker)
            'ppn_on_cfx' => $ppnOnCfx, // 0.0055% or 0.0165% PPN on CFX fee
            'trading_fee' => $tradingFee, // Total trading fee
            'slippage' => $slippage, // 0.5% - Estimated slippage
            'total' => $totalFees,
            'formatted' => $formattedFees['formatted'],
            'description' => 'Biaya transaksi ' . $feeDescription . ' + slippage 0,5%'
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

        // Ensure risk is never exactly 0 to avoid division issues
        if ($risk == 0) {
            $risk = $entryPrice * 0.001; // Use 0.1% of entry price as minimum risk
        }

        $fees = $this->calculateFees($positionSize);

        // Calculate profit/loss without fees first
        if ($positionType === 'long') {
            $potentialLoss = -$risk * ($positionSize / $entryPrice);
            $potentialProfit = ($takeProfit - $entryPrice) * ($positionSize / $entryPrice);
        } else { // short
            // For short positions: loss when price goes UP from entry
            // Example: Short at $100, price goes to $110 = $10 loss per unit
            // You sold at $100, buy back at $110 = lose $10
            $potentialLoss = $risk * ($positionSize / $entryPrice);

            // For short positions: profit when price goes DOWN from entry
            // Example: Short at $100, price goes to $90 = $10 profit per unit
            // You sold at $100, buy back at $90 = gain $10
            $potentialProfit = ($entryPrice - $takeProfit) * ($positionSize / $entryPrice);
        }

        // Apply fees to both profit and loss (more realistic)
        // For profits: fees are subtracted (you get less profit)
        $potentialProfit = $potentialProfit - $fees['total'];
        // For losses: fees are added (you lose more money)
        $potentialLoss = $potentialLoss - $fees['total'];

        // Ensure loss is always negative and includes at least the fee amount
        // Even if risk is 0, you still pay fees on trade exit
        $potentialLoss = min($potentialLoss, -$fees['total']);

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
                'calculation_method' => $positionType === 'long' ? 'LONG: loss = -risk * (size/price), profit = (TP - entry) * (size/price)' : 'SHORT: loss = risk * (size/price), profit = (entry - TP) * (size/price)',
                'potential_loss_before_fees' => $positionType === 'long' ? -$risk * ($positionSize / $entryPrice) : $risk * ($positionSize / $entryPrice),
                'potential_profit_before_fees' => $positionType === 'long' ? ($takeProfit - $entryPrice) * ($positionSize / $entryPrice) : ($entryPrice - $takeProfit) * ($positionSize / $entryPrice),
                'potential_loss' => $potentialLoss,
                'potential_profit' => $potentialProfit,
                'minimum_loss_should_be' => -$fees['total'],
                'short_position_explanation' => 'For SHORT: loss when price UP (buy back higher), profit when price DOWN (buy back lower)'
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
        string $description,
        string $signal,
        float $confidence,
        float $entry,
        float $stopLoss,
        float $takeProfit,
        float $riskReward,
        float $positionSize,
        string $analystMethod = 'basic',
        array $indicators = [], // Add indicators parameter
        string $orderType = 'taker', // Add order type parameter
        string $notes = '' // Add notes parameter
    ): object {
        $entryPrices = $this->formatPrice($entry);
        $stopLossPrices = $this->formatPrice($stopLoss);
        $takeProfitPrices = $this->formatPrice($takeProfit);
        $fees = $this->calculateFees($positionSize, $orderType); // Pass order type to calculateFees
        $pl = $this->calculatePotentialPL($entry, $stopLoss, $takeProfit, $positionSize, $signal);

        // Return standardized result object as specified in AnalysisInterface and agents.md
        return (object) [
            'title' => $title,
            'description' => $description,
            'signal' => $signal,
            'confidence' => $confidence,
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_reward' => $riskReward,
            'fee' => $fees, // Return the full fees array
            'potential_profit' => $pl['potential_profit'],
            'potential_loss' => $pl['potential_loss'],
            'entry_usd' => $entryPrices['usd'],
            'entry_idr' => $entryPrices['rupiah'],
            'stop_loss_usd' => $stopLossPrices['usd'],
            'stop_loss_idr' => $stopLossPrices['rupiah'],
            'take_profit_usd' => $takeProfitPrices['usd'],
            'take_profit_idr' => $takeProfitPrices['rupiah'],
            'fee_usd' => $fees['total'],
            'fee_idr' => $fees['total'] * $this->usdToIdr,
            'potential_profit_usd' => $pl['potential_profit'],
            'potential_profit_idr' => $pl['potential_profit'] * $this->usdToIdr,
            'potential_loss_usd' => $pl['potential_loss'],
            'potential_loss_idr' => abs($pl['potential_loss']) * $this->usdToIdr,
            'indicators' => $indicators,
            'notes' => $notes,
        ];
    }

    /**
     * Abstract method that must be implemented by all analysis services
     * This ensures all implementations return a standardized result structure
     */
    abstract public function analyze(string $symbol, float $amount = 100): object;

    /**
     * Get the name/identifier of this analysis method
     */
    abstract public function getName(): string;
}