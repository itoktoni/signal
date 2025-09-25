<?php

namespace App\Console\Commands;

use App\Analysis\AnalysisServiceFactory;
use App\Enums\AnalysisType;
use Illuminate\Console\Command;

class SniperAnalysisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sniper:analyze
                            {symbol : The crypto symbol to analyze (e.g., BTCUSDT)}
                            {--amount=100 : The trading amount in USD}
                            {--json : Output results in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform sniper analysis on a cryptocurrency symbol';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = strtoupper($this->argument('symbol'));
        $amount = floatval($this->option('amount'));
        $isJson = $this->option('json');

        // Validate amount
        if ($amount <= 0) {
            $this->error('Amount must be greater than 0');
            return Command::FAILURE;
        }

        $this->info("🔍 Performing Sniper Analysis for {$symbol} with amount \${$amount}");

        try {
            // Create the sniper analysis service
            $analysisService = AnalysisServiceFactory::create(AnalysisType::SNIPER);

            // Perform the analysis
            $result = $analysisService->analyze($symbol, $amount);

            if ($isJson) {
                $this->outputJson($result);
            } else {
                $this->outputFormatted($result, $symbol, $amount);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Analysis failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Output results in formatted text
     */
    private function outputFormatted($result, $symbol, $amount)
    {
        $this->newLine();
        $this->line("📊 <fg=cyan>SNIPER ANALYSIS RESULTS</fg=cyan>");
        $this->line(str_repeat('=', 50));

        // Signal and confidence
        $signal = strtoupper($result->signal);
        $signalColor = $this->getSignalColor($signal);
        $confidence = $result->confidence;

        $this->line("Symbol: <fg=yellow>{$symbol}</fg=yellow>");
        $this->line("Amount: <fg=green>\${$amount}</fg=green>");
        $this->line("Signal: <fg={$signalColor}>{$signal}</fg={$signalColor}>");
        $this->line("Confidence: <fg=cyan>{$confidence}%</fg=cyan>");
        $this->line("Risk-Reward Ratio: <fg=magenta>{$result->risk_reward}:1</fg=magenta>");

        $this->newLine();
        $this->line("💰 <fg=cyan>TRADING LEVELS</fg=cyan>");
        $this->line(str_repeat('-', 30));

        $this->line("Entry Price:     <fg=green>\${$result->entry_usd}</fg=green>");
        $this->line("Stop Loss:       <fg=red>\${$result->stop_loss_usd}</fg=red>");
        $this->line("Take Profit:     <fg=green>\${$result->take_profit_usd}</fg=green>");

        $this->newLine();
        $this->line("💵 <fg=cyan>FEE INFORMATION</fg=cyan>");
        $this->line(str_repeat('-', 30));

        $this->line("Total Fee:       <fg=yellow>\${$result->fee_usd}</fg=yellow>");
        $this->line("Fee in IDR:      <fg=yellow>Rp " . number_format($result->fee_idr) . "</fg=yellow>");

        $this->newLine();
        $this->line("📈 <fg=cyan>PROFIT & LOSS POTENTIAL</fg=cyan>");
        $this->line(str_repeat('-', 35));

        $this->line("Potential Profit: <fg=green>\${$result->potential_profit_usd}</fg=green>");
        $this->line("Potential Loss:   <fg=red>\${$result->potential_loss_usd}</fg=red>");
        $this->line("Profit in IDR:    <fg=green>Rp " . number_format($result->potential_profit_idr) . "</fg=green>");
        $this->line("Loss in IDR:      <fg=red>Rp " . number_format(abs($result->potential_loss_idr)) . "</fg=red>");

        if (!empty($result->indicators)) {
            $this->newLine();
            $this->line("📊 <fg=cyan>TECHNICAL INDICATORS</fg=cyan>");
            $this->line(str_repeat('-', 30));

            foreach ($result->indicators as $key => $value) {
                $label = ucwords(str_replace('_', ' ', $key));
                $formattedValue = $this->formatIndicatorValue($key, $value);
                $this->line("{$label}: <fg=cyan>{$formattedValue}</fg=cyan>");
            }
        }

        $this->newLine();
        $this->line("⚠️  <fg=red>RISK WARNING</fg=red>");
        $this->line("Trading cryptocurrencies involves substantial risk of loss.");
        $this->line("Only invest money you can afford to lose.");
        $this->line("Past performance does not guarantee future results.");
    }

    /**
     * Output results in JSON format
     */
    private function outputJson($result)
    {
        $this->line(json_encode([
            'symbol' => $this->argument('symbol'),
            'amount' => $this->option('amount'),
            'analysis' => [
                'title' => $result->title,
                'signal' => $result->signal,
                'confidence' => $result->confidence,
                'risk_reward' => $result->risk_reward,
                'entry_usd' => $result->entry_usd,
                'entry_idr' => $result->entry_idr,
                'stop_loss_usd' => $result->stop_loss_usd,
                'stop_loss_idr' => $result->stop_loss_idr,
                'take_profit_usd' => $result->take_profit_usd,
                'take_profit_idr' => $result->take_profit_idr,
                'fee_usd' => $result->fee_usd,
                'fee_idr' => $result->fee_idr,
                'potential_profit_usd' => $result->potential_profit_usd,
                'potential_profit_idr' => $result->potential_profit_idr,
                'potential_loss_usd' => $result->potential_loss_usd,
                'potential_loss_idr' => $result->potential_loss_idr,
                'indicators' => $result->indicators,
                'last_updated' => now()->toDateTimeString()
            ]
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Get color for signal display
     */
    private function getSignalColor($signal)
    {
        return match ($signal) {
            'BUY', 'LONG' => 'green',
            'SELL', 'SHORT' => 'red',
            default => 'yellow'
        };
    }

    /**
     * Format indicator value for display
     */
    private function formatIndicatorValue($key, $value)
    {
        if (!is_numeric($value)) {
            return $value;
        }

        if (str_contains(strtolower($key), 'price') || str_contains(strtolower($key), 'usd')) {
            return '$' . number_format($value, 3);
        } elseif (str_contains(strtolower($key), 'percentage') || str_contains(strtolower($key), 'percent')) {
            return number_format($value, 2) . '%';
        } else {
            return number_format($value, 4);
        }
    }
}