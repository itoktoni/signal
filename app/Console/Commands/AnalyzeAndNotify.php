<?php

namespace App\Console\Commands;

use App\Analysis\AnalysisServiceFactory;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class AnalyzeAndNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:notify
        {symbol : The cryptocurrency symbol to analyze (e.g., BTCUSDT)}
        {method? : The analysis method to use (default: ma_rsi_volume_atr_macd)}
        {--amount=100 : The trading amount in USD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze a cryptocurrency using a specific method and send results to Telegram';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = $this->argument('symbol');
        $method = $this->argument('method') ?? 'ma_rsi_volume_atr_macd';
        $amount = (float) $this->option('amount');

        $this->info("🔍 Analyzing {$symbol} using {$method} method...");

        // Initialize Telegram service
        $telegram = new TelegramService();

        // Check if Telegram is configured
        if (!$telegram->isConfigured()) {
            $this->warn('⚠️  Telegram not configured. Results will be displayed in console only.');
            $this->line('To enable Telegram notifications, add these to your .env file:');
            $this->line('TELEGRAM_BOT_TOKEN=your_bot_token_here');
            $this->line('TELEGRAM_CHAT_ID=your_chat_id_here');
        }

        try {
            // Create analysis service
            $analysisService = AnalysisServiceFactory::create($method);

            // Perform analysis
            $this->info('📊 Performing analysis...');
            $result = $analysisService->analyze($symbol, $amount);

            // Get current price
            $currentPrice = $this->getCurrentPrice($symbol);

            // Display results in console
            $this->displayResults($symbol, $result, $currentPrice);

            // Send to Telegram if configured
            if ($telegram->isConfigured()) {
                $this->info('📤 Sending results to Telegram...');
                $success = $telegram->sendAnalysisResult($symbol, $result, $currentPrice);

                if ($success) {
                    $this->info('✅ Results sent to Telegram successfully!');
                } else {
                    $this->error('❌ Failed to send results to Telegram!');
                    $this->line('Check your Telegram configuration and network connectivity.');
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Analysis failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());

            // Send error to Telegram if configured
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
     * Get current price of a symbol from Binance
     */
    private function getCurrentPrice(string $symbol): float
    {
        try {
            $client = new Client();
            $response = $client->get("https://api.binance.com/api/v3/ticker/price", [
                'query' => ['symbol' => strtoupper($symbol)]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return (float) $data['price'];
        } catch (\Exception $e) {
            $this->warn("⚠️  Could not fetch current price: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Display analysis results in console
     */
    private function displayResults(string $symbol, object $result, float $currentPrice): void
    {
        $this->info("\n" . str_repeat('=', 50));
        $this->info("📈 ANALYSIS RESULTS FOR {$symbol}");
        $this->info(str_repeat('=', 50));

        $this->line("Title: {$result->title}");
        $this->line("Signal: {$result->signal}");
        $this->line("Confidence: {$result->confidence}%");
        $this->line("Current Price: $" . number_format($currentPrice, 4));
        $this->line("Entry: $" . number_format($result->entry, 4));
        $this->line("Stop Loss: $" . number_format($result->stop_loss, 4));
        $this->line("Take Profit: $" . number_format($result->take_profit, 4));
        $this->line("Risk:Reward: {$result->risk_reward}");

        if (isset($result->notes) && !empty($result->notes)) {
            $this->line("\n📝 Notes: {$result->notes}");
        }

        $this->info(str_repeat('=', 50) . "\n");
    }
}