<?php

namespace App\Console\Commands;

use App\Analysis\AnalysisServiceFactory;
use App\Analysis\Providers\ProviderFactory;
use App\Models\Coin;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AnalyzeCoin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:coin
        {symbol : The cryptocurrency symbol to analyze (e.g., BTCUSDT)}
        {method? : The analysis method to use (default: hma_breakout)}
        {--amount=100 : The trading amount in USD}
        {--api= : Force specific API provider (binance, coingecko, etc)}
        {--check-providers : Check available coins for all API providers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze a cryptocurrency using a specific method and send results to Telegram';

    public function handle()
    {
        $symbol = $this->argument('symbol');
        $method = $this->argument('method') ?? 'daytrade_buy';
        $amount = (float) $this->option('amount');

        if($symbol == 'all')
        {
            $currentHour = Carbon::now()->startOfHour()->toTimeString();

            $scanCoin = Coin::where('coin_watch', 1)
                ->whereTime('updated_at', '!=', $currentHour)
                ->orWhereNull('updated_at')
                ->where('coin_watch', 1)
                ->limit(env('LIMIT_SCAN_COIN', 2))
                ->get();

            foreach($scanCoin as $scan)
            {
                $this->analize($scan->field_key, $method);
                sleep(1);
                Coin::find($scan->field_key)->update([
                    'updated_at' => now()
                ]);
            }
        }
        else
        {
            $this->analize($symbol, $method);
        }
    }

    private function analize($symbol, $method)
    {
        $telegram = new TelegramService();

        // Check if symbol exists in database (like web interface)

        try {
            // Create provider based on user selection using factory pattern
            $forcedApi = $this->option('api') ? strtolower($this->option('api')) : 'binance';
            try {
                $provider = ProviderFactory::createProvider($forcedApi);
            } catch (\Exception $e) {
                // Fallback to default provider
                $provider = ProviderFactory::createProvider('binance');
            }

            $analysisService = AnalysisServiceFactory::createAnalysis($method, $provider);

            $this->info('📊 Performing analysis...');

            // Hasil SimpleAnalysis (object sesuai AnalysisInterface)
            $result = $analysisService->analyze($symbol, 100, '5m', $forcedApi);
            $result = Http::get('/testing/'.$symbol);
            dd($result);

            $currentPrice = $result->price;

            $this->displayResults($symbol, $result, $currentPrice);

            if ($telegram->isConfigured()) {
                $this->info('📤 Sending results to Telegram...');
                $telegram->sendAnalysisResult($symbol, $result, $currentPrice, $forcedApi ?? 'Auto');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Analysis failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());

            if ($telegram->isConfigured()) {
                $errorMessage = "❌ <b>Analysis Error</b>\n\n";
                $errorMessage .= "Symbol: {$symbol}\n";
                $errorMessage .= "Method: {$method}\n";
                $errorMessage .= "Error: " . $e->getMessage();

                $telegram->sendMessage($errorMessage);
            }

            return 1;
        }
    }

    /**
     * Find a working symbol by trying different variations
     */
    private function findWorkingSymbol(string $convertedSymbol, string $originalSymbol): string
    {
        // First try the converted symbol
        if ($this->symbolExists($convertedSymbol)) {
            $this->info("✅ Using symbol: {$convertedSymbol}");
            return $convertedSymbol;
        }

        // If that doesn't work, try the original symbol
        if ($this->symbolExists($originalSymbol)) {
            $this->info("✅ Using symbol: {$originalSymbol} (fallback from {$convertedSymbol})");
            return $originalSymbol;
        }

        // If neither works, log a warning and return the converted symbol
        $this->warn("⚠️  Neither {$convertedSymbol} nor {$originalSymbol} found in data source");
        $this->warn("⚠️  Proceeding with {$convertedSymbol} but analysis may fail");
        return $convertedSymbol;
    }

    private function displayResults(string $symbol, object $result, float $currentPrice): void
    {
        $rupiah = getUsdToIdrRate();

        $this->info("\n" . str_repeat('=', 50));
        $this->info("📈 ANALYSIS RESULTS FOR {$symbol}");
        $this->info(str_repeat('=', 50));

        $this->line("📌 Title: {$result->title}");
        $descriptionText = is_array($result->description) ? implode(" | ", $result->description) : $result->description;
        $this->line("📝 Method: {$descriptionText}");
        $this->line("📊 Signal: {$result->signal}");
        $this->line("💯 Confidence: {$result->confidence}%");
        $this->line("💵 Current Price: $" . number_format($currentPrice, 2));
        $this->line("💵 Rupiah Price: Rp " . number_format($currentPrice * $rupiah, 0, ',', '.'));

        $this->line("\n🎯 Entry: $" . number_format($result->entry, 2));
        $this->line("🛑 Stop Loss: $" . number_format($result->stop_loss, 2));
        $this->line("✅ Take Profit: $" . number_format($result->take_profit, 2));
        $this->line("⚖️ Risk:Reward: {$result->risk_reward}");

        if (!empty($result->notes)) {
            $notesText = is_array($result->notes) ? implode(" | ", $result->notes) : $result->notes;
            $this->line("\n📝 Notes: {$notesText}");
        }

        if (!empty($result->indicators)) {
            $this->line("\n📊 Indicators:");
            foreach ($result->indicators as $name => $value) {
                if (is_array($value)) {
                    $this->line("   - {$name}: " . json_encode($value));
                } else {
                    $this->line("   - {$name}: " . (is_numeric($value) ? number_format($value, 2) : $value));
                }
            }
        }

        $this->info(str_repeat('=', 50) . "\n");
    }
}