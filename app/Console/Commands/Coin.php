<?php

namespace App\Console\Commands;

use App\Analysis\BearishReversalAnalysis;
use Illuminate\Console\Command;
use App\Analysis\MaAnalysis;
use App\Analysis\ReversalAnalysisSellToBuy;
use App\Analysis\ReversalDetectionV2Analysis;
use App\Analysis\RsiBollingerReversal;

class Coin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example usage:
     *   php artisan analysis:ma BTCUSDT 1000
     *
     * @var string
     */
    protected $signature = 'scan:coin {symbol : The trading pair symbol (e.g., BTCUSDT)} {amount=1000 : Trading amount in USD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run MA20/50 + RSI + Volume + Candle analysis using Binance free API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = strtoupper($this->argument('symbol'));
        $amount = (float) $this->argument('amount');

        // $service = new MaAnalysis();
        // $service = new ReversalAnalysisSellToBuy();
        // $service = new BearishReversalAnalysis();
        $service = new RsiBollingerReversal();

        try {
            $result = $service->analyze($symbol, $amount);

            $this->info("✅ Analysis Completed for {$symbol}");
            $this->line("----------------------------------------------------");
            $this->line("Title       : " . $result->title);
            $this->line("Description : " . $service->getDescription());
            $this->line("Signal      : " . $result->signal);
            $this->line("Confidence  : " . $result->confidence . "%");
            $this->line("Entry (USD) : " . $result->entry_usd);
            $this->line("Stop Loss   : " . $result->stop_loss_usd);
            $this->line("Take Profit : " . $result->take_profit_usd);
            $this->line("RR Ratio    : " . $result->risk_reward);
            $this->line("Notes       : " . $service->getNotes());
            $this->line("Amount       : " . $amount . " USD");
            $this->line("----------------------------------------------------");

            // tampilkan indikator dalam tabel
            $this->table(
                array_keys($service->getIndicators()),
                [array_values($service->getIndicators())]
            );

            // tampilkan hasil lengkap (opsional)
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
}
