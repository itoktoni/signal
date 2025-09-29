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
        {method? : The analysis method to use (default: multi_tf_analysis)}
        {--amount=100 : The trading amount in USD}
        {--api= : Force specific API provider (binance, coingecko, etc)}
        {--check-providers : Check available coins for all API providers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze a cryptocurrency using a specific method and send results to Telegram';

    /**
     * Check available coins for all API providers
     */
    private function checkApiProviderCoins(): array
    {
        $this->info('🔍 Checking available coins for all API providers...');
        $this->newLine();

        $results = [];
        $providers = $this->apiManager->getAvailableProviders();

        foreach ($providers as $providerCode => $provider) {
            $this->info("📊 Checking {$providerCode}...");

            try {
                $symbolInfo = $provider->getSymbolInfo();

                if (is_array($symbolInfo)) {
                    $coinCount = count($symbolInfo);
                    $results[$providerCode] = $coinCount;

                    $this->line("   ✅ {$providerCode}: {$coinCount} coins");
                } else {
                    $this->line("   ⚠️  {$providerCode}: Invalid response format");
                    $results[$providerCode] = 0;
                }
            } catch (\Exception $e) {
                $this->line("   ❌ {$providerCode}: " . $e->getMessage());
                $results[$providerCode] = 0;
            }
        }

        $this->newLine();
        return $results;
    }

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
        // Check if we should check API providers
        if ($this->option('check-providers')) {
            $results = $this->checkApiProviderCoins();

            // Find provider with most coins
            $bestProvider = null;
            $maxCoins = 0;

            foreach ($results as $provider => $count) {
                if ($count > $maxCoins) {
                    $maxCoins = $count;
                    $bestProvider = $provider;
                }
            }

            if ($bestProvider) {
                $this->info("🏆 Provider with most coins: {$bestProvider} ({$maxCoins} coins)");
                $this->info("💡 Consider updating your main API provider in config/crypto.php");
            }

            return 0;
        }

        $symbol = strtoupper($this->argument('symbol'));
        $method = $this->argument('method') ?? 'ma_rsi_volume_atr_macd';
        $amount = (float) $this->option('amount');

        // Convert symbol format if needed (BTC -> BTCUSDT)
        $symbolConverter = config('crypto.symbol_converter', []);
        $fullSymbol = $symbolConverter[$symbol] ?? $symbol;

        // Check if the converted symbol exists, if not try the original symbol
        $symbolToUse = $this->findWorkingSymbol($fullSymbol, $symbol);

        $this->info("🔍 Analyzing {$symbol} using {$method} method...");

        $this->showApiProviderInfo($symbolToUse);

        $telegram = new TelegramService();

        try {
            $analysisService = AnalysisServiceFactory::create($method, $this->apiManager);

            $this->info('📊 Performing analysis...');
            $forcedApi = $this->option('api') ? strtolower($this->option('api')) : null;

            // Hasil SimpleAnalysis (object sesuai AnalysisInterface)
            $result = $analysisService->analyze($symbolToUse, $amount, '1h', $forcedApi);

            $currentPrice = $this->getCurrentPrice($symbolToUse);

            $this->displayResults($symbol, $result, $currentPrice);

            if ($telegram->isConfigured()) {
                $this->info('📤 Sending results to Telegram...');
                $telegram->sendAnalysisResult($symbolToUse, $result, $currentPrice, $forcedApi ?? 'Auto');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Analysis failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());

            if ($telegram->isConfigured()) {
                $errorMessage = "❌ <b>Analysis Error</b>\n\n";
                $errorMessage .= "Symbol: {$symbolToUse}\n";
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

    /**
     * Check if a symbol exists in any of the available API providers
     */
    private function symbolExists(string $symbol): bool
    {
        try {
            // Try to get data for this symbol from any provider
            $this->apiManager->getHistoricalData($symbol, '1h', 10); // Reduced from 14 to 10 to work with CoinPaprika
            return true;
        } catch (\Exception $e) {
            // If we get an exception about insufficient data, that's actually good - it means the symbol exists
            if (strpos($e->getMessage(), 'Insufficient historical data') !== false) {
                // Even if there's insufficient data, the symbol exists
                $this->info("ℹ️  Symbol {$symbol} exists but has insufficient data");
                return true;
            }

            // If we get an exception about the symbol not being found, it doesn't exist
            if (strpos($e->getMessage(), 'not found') !== false ||
                strpos($e->getMessage(), 'Invalid symbol') !== false ||
                strpos($e->getMessage(), '404') !== false) {
                $this->info("ℹ️  Symbol {$symbol} not found in data source");
                return false;
            }

            // Any other exception means there might be a connectivity issue, but we can't determine if the symbol exists
            $this->info("ℹ️  Unable to determine if symbol {$symbol} exists due to: " . $e->getMessage());
            return false;
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