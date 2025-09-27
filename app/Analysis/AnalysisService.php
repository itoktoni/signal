<?php

namespace App\Analysis;

use App\Helpers\CurrencyHelper;
use Illuminate\Support\Facades\Http;

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
        string $riskReward,
        array $fees, // Fees array calculated by the service
        float $potentialProfit,
        float $potentialLoss,
        string $analystMethod = 'basic',
        array $indicators = [], // Add indicators parameter
        string $notes = '' // Add notes parameter
    ): object {
        $entryPrices = $this->formatPrice($entry);
        $stopLossPrices = $this->formatPrice($stopLoss);
        $takeProfitPrices = $this->formatPrice($takeProfit);

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
            'potential_profit' => $potentialProfit,
            'potential_loss' => $potentialLoss,
            'entry_usd' => $entryPrices['usd'],
            'entry_idr' => $entryPrices['rupiah'],
            'stop_loss_usd' => $stopLossPrices['usd'],
            'stop_loss_idr' => $stopLossPrices['rupiah'],
            'take_profit_usd' => $takeProfitPrices['usd'],
            'take_profit_idr' => $takeProfitPrices['rupiah'],
            'fee_usd' => $fees['total'],
            'fee_idr' => $fees['total'] * $this->usdToIdr,
            'potential_profit_usd' => $potentialProfit,
            'potential_profit_idr' => $potentialProfit * $this->usdToIdr,
            'potential_loss_usd' => $potentialLoss,
            'potential_loss_idr' => abs($potentialLoss) * $this->usdToIdr,
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