<?php

namespace App\Console\Commands;

use App\Analysis\AnalysisServiceFactory;
use App\Analysis\ApiProviderManager;
use App\Services\TelegramService;
use App\Settings\Settings;
use Illuminate\Console\Command;

class AnalyzeCoin extends Command
{
    private ApiProviderManager $apiManager;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:coin
        {symbol : The cryptocurrency symbol to analyze (e.g., BTCUSDT)}
        {method? : The analysis method to use (default: ma_rsi_volume_atr_macd)}
        {--amount=100 : The trading amount in USD}
        {--api= : Force specific API provider (binance, coingecko, etc)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze a cryptocurrency using a specific method and send results to Telegram';

    public function __construct()
    {
        parent::__construct();

        $settingsManager = app('settings');
        $driver = $settingsManager->driver();
        $settings = new Settings($driver);
        $this->apiManager = new ApiProviderManager($settings);
    }

    public function handle()
    {
        $symbol = strtoupper($this->argument('symbol'));
        $method = $this->argument('method') ?? 'ma_rsi_volume_atr_macd';
        $amount = (float) $this->option('amount');

        $this->info("🔍 Analyzing {$symbol} using {$method} method...");

        $this->showApiProviderInfo($symbol);

        $telegram = new TelegramService();

        try {
            $analysisService = AnalysisServiceFactory::create($method, $this->apiManager);

            $this->info('📊 Performing analysis...');
            $forcedApi = $this->option('api') ? strtolower($this->option('api')) : null;

            // Hasil SimpleAnalysis (object sesuai AnalysisInterface)
            $result = $analysisService->analyze($symbol, $amount, '1h', $forcedApi);

            $currentPrice = $this->getCurrentPrice($symbol);

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

    private function getCurrentPrice(string $symbol): float
    {
        try {
            return $this->apiManager->getCurrentPrice($symbol);
        } catch (\Exception $e) {
            $this->warn("⚠️  Could not fetch current price: " . $e->getMessage());
            return 0;
        }
    }

    private function showApiProviderInfo(string $symbol): void
    {
        $forcedApi = $this->option('api');
        $coinMapping = config('crypto.coin_api_mapping', []);
        $apiConfigs = config('crypto.api_providers.providers', []);

        if ($forcedApi) {
            $apiCode = strtolower($forcedApi);
            if (isset($apiConfigs[$apiCode])) {
                $apiConfig = $apiConfigs[$apiCode];
                $this->line("🌐 Using Forced API: " . strtoupper($apiCode));
                $this->line("   URL: " . ($apiConfig['base_url'] ?? 'Unknown'));
            } else {
                $this->warn("⚠️  Unknown API provider: {$apiCode}");
            }
        } else {
            $primaryApi = $coinMapping['primary_api'][$symbol] ?? 'binance';
            $fallbackApis = $coinMapping['fallback_apis'][$symbol] ?? [$primaryApi, 'coingecko'];

            $this->line("🌐 Intelligent API Routing:");
            $this->line("   Primary API: " . strtoupper($primaryApi));
            $this->line("   Fallback APIs: " . implode(', ', array_map('strtoupper', $fallbackApis)));
        }

        $this->newLine();
    }

    private function displayResults(string $symbol, object $result, float $currentPrice): void
    {
        $rupiah = getUsdToIdrRate();

        $this->info("\n" . str_repeat('=', 50));
        $this->info("📈 ANALYSIS RESULTS FOR {$symbol}");
        $this->info(str_repeat('=', 50));

        $this->line("📌 Title: {$result->title}");
        $this->line("📝 Method: {$result->description}");
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
