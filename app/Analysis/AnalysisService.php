<?php

namespace App\Analysis;

use App\Analysis\AnalysisInterface;
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

        $fees = $this->calculateFees($positionSize);

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
        float $positionSize,
        string $analystMethod = 'basic',
        array $indicators = [], // Add indicators parameter
        string $orderType = 'taker', // Add order type parameter
        array $indicatorConfig = [] // Add indicator configuration parameter
    ): object {
        $entryPrices = $this->formatPrice($entry);
        $stopLossPrices = $this->formatPrice($stopLoss);
        $takeProfitPrices = $this->formatPrice($takeProfit);
        $fees = $this->calculateFees($positionSize, $orderType); // Pass order type to calculateFees
        $pl = $this->calculatePotentialPL($entry, $stopLoss, $takeProfit, $positionSize, $signal);

        // Generate conclusion data
        $conclusion = $this->generateConclusion(
            $signal,
            $confidence,
            $riskReward,
            $analystMethod,
            $entry,
            $stopLoss,
            $takeProfit
        );

        return (object) [
            'title' => $title,
            'signal' => $signal, // long or short
            'confidence' => $confidence, // 95% (calculate any function that show percentage)
            'entry' => $entryPrices, // USD and Rupiah
            'stop_loss' => $stopLossPrices, // USD and Rupiah
            'take_profit' => $takeProfitPrices, // USD and Rupiah
            'risk_reward' => $riskReward, // 1:3 (risk:reward)
            'fee' => $fees, // USD and Rupiah (updated fee structure)
            'potential_profit' => $this->formatPrice($pl['potential_profit']), // USD and Rupiah after fee
            'potential_loss' => $this->formatPrice($pl['potential_loss']), // USD and Rupiah after fee
            'indicators' => $indicators, // Add indicators to the result
            'indicator_config' => $indicatorConfig, // Add indicator configuration to the result
            'conclusion' => $conclusion, // Generated conclusion data
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

    /**
     * Generate conclusion content based on analysis results
     */
    protected function generateConclusion(
        string $signal,
        float $confidence,
        float $rrRatio,
        string $analystMethod,
        float $entryPrice,
        float $stopLoss,
        float $takeProfit
    ): array {
        $conclusionData = [
            'signal' => $signal,
            'confidence' => $confidence,
            'rr_ratio' => $rrRatio,
            'analyst_method' => $analystMethod,
            'entry_price' => $entryPrice,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'recommendations' => [],
            'method_descriptions' => []
        ];

        // Generate signal-specific recommendations
        switch ($signal) {
            case 'BUY':
                $conclusionData['recommendations'] = [
                    'title' => '📈 REKOMENDASI PERDAGANGAN LONG',
                    'description' => 'Analisis ini menunjukkan peluang BUY dengan titik masuk di dekat harga pasar saat ini. Stop loss ditempatkan di bawah level support kunci, dan target take profit ditetapkan di zona resistance atau level Fibonacci.',
                    'trading_advice' => [
                        'Entry pada level $' . number_format($entryPrice, 3),
                        'Set stop loss di $' . number_format($stopLoss, 3) . ' untuk membatasi risiko',
                        'Target profit di $' . number_format($takeProfit, 3) . ' dengan potensi ' . $rrRatio . 'x lipat dari risiko',
                        'Confidence level ' . $confidence . '% - ' . ($confidence >= 80 ? 'SANGAT DIREKOMENDASIKAN' : ($confidence >= 60 ? 'DIREKOMENDASIKAN' : 'PERTIMBANGKAN HATI-HATI'))
                    ]
                ];
                break;

            case 'SELL':
                $conclusionData['recommendations'] = [
                    'title' => '📉 REKOMENDASI PERDAGANGAN SHORT',
                    'description' => 'Analisis ini menunjukkan peluang SELL dengan titik masuk di dekat harga pasar saat ini. Stop loss ditempatkan di atas level resistance kunci, dan target take profit ditetapkan di zona support atau level Fibonacci.',
                    'trading_advice' => [
                        'Entry short pada level $' . number_format($entryPrice, 3),
                        'Set stop loss di $' . number_format($stopLoss, 3) . ' untuk membatasi risiko',
                        'Target profit di $' . number_format($takeProfit, 3) . ' dengan potensi ' . $rrRatio . 'x lipat dari risiko',
                        'Confidence level ' . $confidence . '% - ' . ($confidence >= 80 ? 'SANGAT DIREKOMENDASIKAN' : ($confidence >= 60 ? 'DIREKOMENDASIKAN' : 'PERTIMBANGKAN HATI-HATI'))
                    ]
                ];
                break;

            default:
                $conclusionData['recommendations'] = [
                    'title' => '⏸️ KONDISI PASAR NETRAL',
                    'description' => 'Kondisi pasar saat ini tidak menunjukkan peluang perdagangan yang jelas.',
                    'trading_advice' => [
                        'Tunggu konfirmasi sinyal yang lebih jelas',
                        'Monitor pergerakan harga untuk setup yang lebih baik',
                        'Pertimbangkan untuk tidak entry sampai ada sinyal yang lebih konfirm',
                        'Confidence level ' . $confidence . '% - menunjukkan ketidakpastian pasar'
                    ]
                ];
                break;
        }

        // Generate method-specific descriptions
        switch ($analystMethod) {
            case 'sniper':
                $conclusionData['method_descriptions'] = [
                    'description' => 'Metode Sniper telah mengkonfirmasi keselarasan kuat dari beberapa indikator teknis untuk setup perdagangan ini.',
                    'details' => [
                        'Menggunakan volume dan price action untuk sinyal entry presisi tinggi',
                        'Penyelarasan beberapa indikator teknis untuk konfirmasi tren',
                        'RSI dalam rentang optimal (45-75 untuk beli, 25-55 untuk jual)',
                        'Target rasio risiko-hadiah 3:1 untuk entri sniper',
                        'Penilaian kepercayaan yang ditingkatkan berdasarkan kondisi yang terpenuhi'
                    ]
                ];
                break;

            case 'dynamic_rr':
                $conclusionData['method_descriptions'] = [
                    'description' => 'Metode RR Dinamis telah mengidentifikasi level optimal berdasarkan volatilitas pasar saat ini dan struktur teknis.',
                    'details' => [
                        'ATR (Average True Range) untuk penempatan stop loss berbasis volatilitas',
                        'Level retracement Fibonacci (23.6%, 38.2%, 50.0%, 61.8%, 78.6%) untuk target take profit',
                        'Perhitungan rasio risiko-hadiah dinamis berdasarkan kondisi pasar',
                        'Integrasi level support dan resistance untuk entri yang optimal',
                        'Persyaratan rasio risiko-hadiah minimum 1,5:1',
                        'Penilaian kepercayaan berdasarkan keselarasan indikator teknis'
                    ],
                    'advantages' => [
                        'Menghindari overtrading dalam kondisi volatilitas rendah',
                        'Memaksimalkan keuntungan di pasar yang tren kuat',
                        'Mengurangi kerugian dengan menempatkan stop berdasarkan volatilitas aktual',
                        'Menggunakan level Fibonacci untuk mengidentifikasi target harga alami'
                    ]
                ];
                break;

            default:
                $conclusionData['method_descriptions'] = [
                    'description' => 'Metode analisis Dasar menunjukkan kondisi pasar yang menguntungkan untuk arah perdagangan ini.',
                    'details' => [
                        'EMA 20 dan RSI 14 pada timeframe 1 jam',
                        'Sinyal beli/jual sederhana berdasarkan harga vs EMA',
                        'Identifikasi level support dan resistance',
                        'Target rasio risiko-hadiah 2:1',
                        'Penilaian kepercayaan yang mudah'
                    ]
                ];
                break;
        }

        // Add general trading advice
        $conclusionData['general_advice'] = [
            'Selalu gunakan ukuran posisi yang tepat sesuai toleransi risiko Anda',
            'Sinyal ini hanya untuk tujuan informasi, bukan saran keuangan',
            'Pertimbangkan berita pasar dan peristiwa yang dapat mempengaruhi pergerakan harga',
            'Gunakan metode konfirmasi tambahan sebelum memasuki perdagangan',
            'Pantau perdagangan dan sesuaikan stop sesuai kebutuhan berdasarkan kondisi pasar'
        ];

        return $conclusionData;
    }

    /**
     * Get indicator configurations for display
     */
    protected function getIndicatorConfigurations(): array
    {
        return [
            'sniper' => [
                'ema9' => ['label' => 'EMA 9', 'format' => 'price', 'class' => 'col-2'],
                'ema21' => ['label' => 'EMA 21', 'format' => 'price', 'class' => 'col-2'],
                'ema50' => ['label' => 'EMA 50', 'format' => 'price', 'class' => 'col-2'],
                'rsi' => ['label' => 'RSI 14', 'format' => 'number', 'class' => 'col-2'],
                'current_price' => ['label' => 'Current Price', 'format' => 'price', 'class' => 'col-2'],
                'support' => ['label' => 'Support', 'format' => 'price', 'class' => 'col-2', 'text_class' => 'text-success'],
                'resistance' => ['label' => 'Resistance', 'format' => 'price', 'class' => 'col-2', 'text_class' => 'text-danger'],
            ],
            'dynamic_rr' => [
                'ema20' => ['label' => 'EMA 20', 'format' => 'price', 'class' => 'col-2'],
                'ema50' => ['label' => 'EMA 50', 'format' => 'price', 'class' => 'col-2'],
                'rsi' => ['label' => 'RSI 14', 'format' => 'number', 'class' => 'col-2'],
                'atr' => ['label' => 'ATR', 'format' => 'price', 'class' => 'col-2'],
                'support' => ['label' => 'Support', 'format' => 'price', 'class' => 'col-2', 'text_class' => 'text-success'],
                'resistance' => ['label' => 'Resistance', 'format' => 'price', 'class' => 'col-2', 'text_class' => 'text-danger'],
            ],
            'default' => [
                'ema20' => ['label' => 'EMA 20', 'format' => 'price', 'class' => 'col-3'],
                'rsi' => ['label' => 'RSI 14', 'format' => 'number', 'class' => 'col-3'],
                'support' => ['label' => 'Support', 'format' => 'price', 'class' => 'col-3', 'text_class' => 'text-success'],
                'resistance' => ['label' => 'Resistance', 'format' => 'price', 'class' => 'col-3', 'text_class' => 'text-danger'],
            ]
        ];
    }

    /**
     * Get indicator configuration for a specific analysis method
     */
    protected function getIndicatorConfig(string $method): array
    {
        $configs = $this->getIndicatorConfigurations();
        return $configs[$method] ?? $configs['default'];
    }

    /**
     * Abstract method that must be implemented by all analysis services
     * This ensures all implementations return a standardized result structure
     */
    abstract public function analyze(string $symbol, float $amount = 1000): object;

    /**
     * Get the name/identifier of this analysis method
     */
    abstract public function getName(): string;
}