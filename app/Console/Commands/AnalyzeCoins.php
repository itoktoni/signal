<?php

namespace App\Console\Commands;

use App\Models\Coin;
use App\Analysis\AnalysisServiceFactory;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AnalyzeCoins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coins:analyze
                            {--coin= : Analyze specific coin only}
                            {--method=ma_rsi_volume_atr_macd : Analysis method to use}
                            {--force : Force analysis even if recently analyzed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze coins automatically using configured analysis methods';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $telegram = new TelegramService();

        $this->info('🚀 Starting coin analysis...');

        if ($telegram->isConfigured()) {
            $this->info('📱 Telegram notifications enabled');
        } else {
            $this->warn('⚠️  Telegram not configured - notifications disabled');
        }

        try {
            // Get coins to analyze
            if ($this->option('coin')) {
                $coins = Coin::where('coin_code', $this->option('coin'))->get();
                if ($coins->isEmpty()) {
                    $this->error("Coin {$this->option('coin')} not found!");
                    return 1;
                }
            } else {
                $coins = $this->option('force')
                    ? Coin::where('coin_watch', true)->get()
                    : Coin::getCoinsNeedingAnalysis();
            }

            if ($coins->isEmpty()) {
                $this->info('✅ No coins need analysis at this time.');
                return 0;
            }

            $this->info("📊 Found {$coins->count()} coin(s) to analyze");

            $analysisMethod = $this->option('method');
            $successCount = 0;
            $errorCount = 0;
            $telegramCount = 0;

            foreach ($coins as $coin) {
                $this->line("🔍 Analyzing {$coin->coin_code}...");

                // Skip if not forced and recently analyzed
                if (!$this->option('force') && !$coin->needsAnalysis()) {
                    $this->line("⏭️  {$coin->coin_code} analyzed recently, skipping...");
                    continue;
                }

                try {
                    // Perform analysis
                    $analysisService = AnalysisServiceFactory::create($analysisMethod);
                    $result = $analysisService->analyze($coin->coin_code, 100); // Default $100 amount

                    // Update coin tracking
                    $coin->updateAnalysisTracking();

                    // Update price if available
                    if (isset($result->entry) && $result->entry > 0) {
                        $coin->update([
                            'coin_price_usd' => $result->entry,
                            'coin_price_idr' => $result->entry * 16000, // Default exchange rate
                        ]);
                    }

                    $this->line("✅ {$coin->coin_code}: {$result->signal} (Confidence: {$result->confidence}%)");
                    $successCount++;

                    // Send Telegram notification if confidence > threshold
                    $confidenceThreshold = config('telegram.confidence_threshold', 70);
                    if ($telegram->isConfigured() && $result->confidence > $confidenceThreshold) {
                        $telegram->sendAnalysisResult($coin->coin_code, $result, $result->entry);
                        $telegramCount++;
                        $this->line("📱 Telegram notification sent for {$coin->coin_code} (Confidence: {$result->confidence}%)");
                    }

                    // Log analysis result
                    Log::info('Coin analysis completed', [
                        'coin_code' => $coin->coin_code,
                        'signal' => $result->signal,
                        'confidence' => $result->confidence,
                        'analysis_method' => $analysisMethod,
                    ]);

                } catch (\Exception $e) {
                    $this->line("❌ {$coin->coin_code}: {$e->getMessage()}");
                    $errorCount++;

                    Log::error('Coin analysis failed', [
                        'coin_code' => $coin->coin_code,
                        'error' => $e->getMessage(),
                        'analysis_method' => $analysisMethod,
                    ]);
                }
            }

            // Summary
            $this->newLine();
            $this->info("📈 Analysis Summary:");
            $this->line("✅ Successful: {$successCount}");
            $this->line("❌ Failed: {$errorCount}");
            $this->line("📱 Telegram Notifications: {$telegramCount}");
            $this->line("⏭️  Skipped: " . ($coins->count() - $successCount - $errorCount));

            if ($errorCount > 0) {
                return 1;
            }

            $this->info('🎉 Coin analysis completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Fatal error during analysis: ' . $e->getMessage());
            Log::error('Fatal error in coin analysis command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}