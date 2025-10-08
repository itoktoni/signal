<?php

namespace App\Console\Commands;

use App\Models\Trade;
use App\Services\TradeService;
use App\Services\TokocryptoIntegration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TokocryptoTradeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokocrypto:trade
                            {action : Action to perform (balance|ticker|execute|status|sync)}
                            {--symbol= : Trading symbol (e.g., BTC/USDT)}
                            {--amount= : Amount to trade in USDT (e.g., 100 for $100)}
                            {--price= : Price for limit orders}
                            {--side= : Buy or sell}
                            {--type= : Order type (market|limit)}
                            {--trade-id= : Specific trade ID for status/execute actions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute Tokocrypto trading operations via console';

    protected $tradeService;
    protected $tokocryptoIntegration;

    public function __construct(TradeService $tradeService, TokocryptoIntegration $tokocryptoIntegration)
    {
        parent::__construct();
        $this->tradeService = $tradeService;
        $this->tokocryptoIntegration = $tokocryptoIntegration;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $action = $this->argument('action');

            switch ($action) {
                case 'balance':
                    return $this->getBalance();
                case 'ticker':
                    return $this->getTicker();
                case 'execute':
                    return $this->executeTrade();
                case 'status':
                    return $this->getTradeStatus();
                case 'sync':
                    return $this->syncTrades();
                default:
                    $this->error("Unknown action: {$action}");
                    $this->showUsage();
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Tokocrypto trade command failed', [
                'action' => $this->argument('action'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Get account balance
     */
    private function getBalance()
    {
        $this->info('Fetching Tokocrypto balance...');

        try {
            // First check if credentials are configured
            if (empty(config('services.tokocrypto.api_key')) || empty(config('services.tokocrypto.secret'))) {
                $this->warn('API credentials not configured');
                $this->warn('Set TOKOCRYPTO_API_KEY and TOKOCRYPTO_SECRET in your .env file for trading operations');
                $this->warn('Note: Market data (ticker, prices) works without credentials');
                return 0;
            }

            $balance = $this->tokocryptoIntegration->getBalance();

            if (!$balance) {
                $this->warn('Failed to fetch balance - API credentials may not be configured');
                $this->warn('Set TOKOCRYPTO_API_KEY and TOKOCRYPTO_SECRET in your .env file for trading operations');
                $this->warn('Note: Market data (ticker, prices) works without credentials');
                return 0;
            }

            $this->info('Balance retrieved successfully:');
            $this->table(
                ['Currency', 'Free', 'Used', 'Total'],
                collect($balance)->map(function ($item, $currency) {
                    return [
                        $currency,
                        $item['free'] ?? 0,
                        $item['used'] ?? 0,
                        $item['total'] ?? 0
                    ];
                })->filter(function ($item) {
                    return $item[3] > 0; // Only show currencies with balance
                })->values()
            );

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to get balance: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get ticker information
     */
    private function getTicker()
    {
        $symbol = $this->option('symbol') ?: 'BTC/USDT';

        $this->info("Fetching ticker for {$symbol}...");

        try {
            $ticker = $this->tokocryptoIntegration->getTicker($symbol);

            if (!$ticker) {
                $this->error('Failed to fetch ticker');
                return 1;
            }

            $this->info('Ticker information:');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Symbol', $ticker['symbol'] ?? $symbol],
                    ['Last Price', $ticker['last'] ?? 'N/A'],
                    ['Bid', $ticker['bid'] ?? 'N/A'],
                    ['Ask', $ticker['ask'] ?? 'N/A'],
                    ['Volume', $ticker['baseVolume'] ?? 'N/A'],
                    ['Change 24h', $ticker['percentage'] ?? 'N/A'],
                    ['High 24h', $ticker['high'] ?? 'N/A'],
                    ['Low 24h', $ticker['low'] ?? 'N/A'],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to get ticker: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Execute a trade
     */
    private function executeTrade()
    {
        $symbol = $this->option('symbol') ?: $this->ask('Enter trading symbol (e.g., BTC/USDT)');
        $side = $this->option('side') ?: $this->choice('Select side', ['buy', 'sell'], 0);
        $type = $this->option('type') ?: $this->choice('Select order type', ['market', 'limit'], 0);

        // Always use USDT amount (this is now the only mode)
        $usdAmount = $this->option('amount') ?: $this->ask('Enter USDT amount (e.g., 100 for $100)');
        $amount = null; // Base amount will be calculated from USDT amount

        $price = null;
        if ($type === 'limit') {
            $price = $this->option('price') ?: $this->ask('Enter price');
        }

        $this->info("Executing {$type} {$side} order for {$usdAmount} USDT worth of {$symbol}" . ($price ? " at {$price}" : ''));
        $this->info("Trade will be executed immediately...");

        try {
            // Create trade record first (always USDT mode)
            $tradeData = [
                'symbol' => $symbol,
                'side' => $side,
                'type' => $type,
                'usd_amount' => $usdAmount,
                'amount_mode' => 'usd', // Always USDT mode now
                'price' => $price,
                'status' => 'pending',
            ];

            // Calculate base amount from USDT amount for database storage
            try {
                $ticker = $this->tokocryptoIntegration->getTicker($symbol);
                if ($ticker && isset($ticker['last']) && $ticker['last'] > 0) {
                    $currentPrice = (float)$ticker['last'];
                    $calculatedAmount = $usdAmount / $currentPrice;
                    $tradeData['amount'] = $calculatedAmount;
                } else {
                    // If we can't get price, set a placeholder (will be updated when trade executes)
                    $tradeData['amount'] = 0;
                }
            } catch (\Exception $e) {
                $this->warn('Could not calculate base amount from USDT amount: ' . $e->getMessage());
                $tradeData['amount'] = 0;
            }

            $trade = Trade::create($tradeData);

            // Execute the trade
            $result = $this->tradeService->executeTrade($trade);

            if ($result['success']) {
                $trade->updateStatus('open', $result['exchange_response']);
                $trade->exchange_order_id = $result['order_id'];
                $trade->save();

                $this->info("Trade executed successfully!");
                $this->info("Order ID: {$result['order_id']}");
                $this->info("Trade ID: {$trade->trade_id}");
            } else {
                $trade->updateStatus('rejected', ['error' => $result['error']]);

                if (str_contains($result['error'], 'API credentials not configured')) {
                    $this->warn('Trade execution failed: ' . $result['error']);
                    $this->warn('Configure your Tokocrypto API credentials in the .env file:');
                    $this->warn('TOKOCRYPTO_API_KEY=your_api_key_here');
                    $this->warn('TOKOCRYPTO_SECRET=your_secret_here');
                    $this->warn('TOKOCRYPTO_SANDBOX=false');
                } else {
                    $this->error('Trade execution failed: ' . $result['error']);
                }
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to execute trade: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get trade status
     */
    private function getTradeStatus()
    {
        $tradeId = $this->option('trade-id') ?: $this->ask('Enter trade ID');

        $trade = Trade::where('trade_id', $tradeId)->first();

        if (!$trade) {
            $this->error("Trade not found: {$tradeId}");
            return 1;
        }

        $this->info("Fetching status for trade {$trade->trade_id}...");

        try {
            $status = $this->tradeService->getTradeStatus($trade);

            if ($status['success']) {
                $this->info('Trade status:');
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Trade ID', $trade->trade_id],
                        ['Symbol', $trade->symbol],
                        ['Side', $trade->side],
                        ['Type', $trade->type],
                        ['Amount', $trade->amount],
                        ['Price', $trade->price],
                        ['Status', $status['status']],
                        ['Exchange Order ID', $trade->exchange_order_id],
                    ]
                );

                if ($status['price']) {
                    $this->info("Current Price: {$status['price']}");
                }
                if ($status['cost']) {
                    $this->info("Total Cost: {$status['cost']}");
                }
                if ($status['fee']) {
                    $this->info("Fee: {$status['fee']} {$status['fee_currency']}");
                }
            } else {
                $this->error('Failed to get trade status: ' . $status['error']);
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to get trade status: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Sync all active trades
     */
    private function syncTrades()
    {
        $this->info('Syncing all active trades with exchange...');

        try {
            $trades = Trade::whereIn('status', ['open', 'filled'])->get();
            $synced = 0;

            $this->output->progressStart($trades->count());

            foreach ($trades as $trade) {
                $status = $this->tradeService->getTradeStatus($trade);

                if ($status['success']) {
                    $trade->updateStatus($status['status'], $status['exchange_response']);
                    $trade->price = $status['price'] ?? $trade->price;
                    $trade->cost = $status['cost'] ?? $trade->cost;
                    $trade->fee = $status['fee'] ?? $trade->fee;
                    $trade->fee_currency = $status['fee_currency'] ?? $trade->fee_currency;
                    $trade->save();
                    $synced++;
                }

                $this->output->progressAdvance();
            }

            $this->output->progressFinish();

            $this->info("Synced {$synced} trades with exchange");
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to sync trades: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show command usage examples
     */
    private function showUsage()
    {
        $this->info('Usage examples:');
        $this->line('  php artisan tokocrypto:trade balance');
        $this->line('  php artisan tokocrypto:trade ticker --symbol=BTC/USDT');
        $this->line('  php artisan tokocrypto:trade execute --symbol=BTC/USDT --side=buy --type=market --amount=100');
        $this->line('  php artisan tokocrypto:trade execute --symbol=ETH/USDT --side=buy --type=limit --amount=200 --price=2000');
        $this->line('  php artisan tokocrypto:trade status --trade-id=TRD-1234567890');
        $this->line('  php artisan tokocrypto:trade sync');
    }
}