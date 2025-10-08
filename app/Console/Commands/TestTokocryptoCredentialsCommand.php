<?php

namespace App\Console\Commands;

use App\Services\TokocryptoIntegration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestTokocryptoCredentialsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokocrypto:test-credentials
                            {--detailed : Show detailed connection information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Tokocrypto API credentials and connection';

    protected $tokocryptoIntegration;

    public function __construct()
    {
        parent::__construct();
        // We'll create the integration service with credentials in the handle method
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔐 Testing Tokocrypto API Credentials');
        $this->line('');

        // Step 1: Check if credentials are configured
        $this->checkCredentialsConfiguration();

        // Step 2: Test exchange connection
        $this->testExchangeConnection();

        // Step 3: Test API functionality
        $this->testApiFunctionality();

        return 0;
    }

    /**
     * Check if API credentials are configured
     */
    private function checkCredentialsConfiguration()
    {
        $this->line('📋 Step 1: Checking credential configuration...');

        $apiKey = config('services.tokocrypto.api_key', env('TOKOCRYPTO_API_KEY'));
        $secret = config('services.tokocrypto.secret', env('TOKOCRYPTO_API_SECRET'));
        $sandbox = config('services.tokocrypto.sandbox', env('TOKOCRYPTO_SANDBOX', true));

        if (empty($apiKey) || empty($secret)) {
            $this->error('❌ API credentials are not configured');
            $this->line('');
            $this->warn('To configure your credentials, add the following to your .env file:');
            $this->line('TOKOCRYPTO_API_KEY=your_api_key_here');
            $this->line('TOKOCRYPTO_SECRET=your_secret_here');
            $this->line('TOKOCRYPTO_SANDBOX=false');
            $this->line('');
            $this->warn('Note: Market data features work without credentials, but trading requires valid API keys.');
            return false;
        }

        $this->info('✅ API credentials are configured');
        $this->line("   API Key: " . substr($apiKey, 0, 8) . '...' . substr($apiKey, -4));
        $this->line("   Secret: " . substr($secret, 0, 8) . '...' . substr($secret, -4));
        $this->line("   Sandbox Mode: " . ($sandbox ? 'Yes' : 'No (Live Trading)'));

        return true;
    }

    /**
     * Test basic exchange connection
     */
    private function testExchangeConnection()
    {
        $this->line('');
        $this->line('🌐 Step 2: Testing exchange connection...');

        try {
            // Create integration service with configured credentials
            $apiKey = config('services.tokocrypto.api_key', env('TOKOCRYPTO_API_KEY'));
            $secret = config('services.tokocrypto.secret', env('TOKOCRYPTO_API_SECRET'));
            $sandbox = config('services.tokocrypto.sandbox', env('TOKOCRYPTO_SANDBOX', true));

            $tokocryptoIntegration = new TokocryptoIntegration($apiKey, $secret, $sandbox);

            // Test basic connectivity by fetching exchange info
            $exchangeInfo = $tokocryptoIntegration->getExchangeInfo();

            if (isset($exchangeInfo['error'])) {
                $this->error('❌ Exchange connection failed: ' . $exchangeInfo['error']);
                return false;
            }

            $this->info('✅ Exchange connection successful');
            $this->line("   Exchange: " . ($exchangeInfo['name'] ?? 'Tokocrypto'));
            $this->line("   ID: " . ($exchangeInfo['id'] ?? 'tokocrypto'));

            if ($this->option('detailed')) {
                $this->line("   Countries: " . implode(', ', $exchangeInfo['countries'] ?? ['Indonesia']));
                $this->line("   Rate Limit: " . ($exchangeInfo['rateLimit'] ?? '1000') . " ms");
            }

            return true;
        } catch (\Exception $e) {
            $this->error('❌ Exchange connection failed: ' . $e->getMessage());

            if (str_contains($e->getMessage(), 'credentials') || str_contains($e->getMessage(), 'auth')) {
                $this->warn('This suggests your API credentials may be invalid or expired.');
                $this->warn('Please check your API key and secret in the Tokocrypto dashboard.');
            }

            return false;
        }
    }

    /**
     * Test API functionality with actual calls
     */
    private function testApiFunctionality()
    {
        $this->line('');
        $this->line('⚡ Step 3: Testing API functionality...');

        // Test 1: Public market data (should always work)
        $this->testPublicData();

        // Test 2: Private account data (requires valid credentials)
        $this->testPrivateData();
    }

    /**
     * Test public market data access
     */
    private function testPublicData()
    {
        $this->line('');
        $this->line('📊 Testing public market data...');

        try {
            // Create integration service (without credentials for public data)
            $tokocryptoIntegration = new TokocryptoIntegration('', '', false);
            $ticker = $tokocryptoIntegration->getTicker('BTC/USDT');

            if (!$ticker) {
                $this->warn('⚠️ Could not fetch BTC/USDT ticker data');
                return;
            }

            $this->info('✅ Public market data access working');
            $this->line("   BTC/USDT Price: $" . number_format($ticker['last'] ?? 0, 2));
            $this->line("   24h Volume: " . number_format($ticker['baseVolume'] ?? 0, 2) . " BTC");
            $this->line("   24h Change: " . number_format(($ticker['percentage'] ?? 0), 2) . "%");

        } catch (\Exception $e) {
            $this->warn('⚠️ Public market data test failed: ' . $e->getMessage());
            $this->warn('This might indicate network issues or exchange downtime.');
        }
    }

    /**
     * Test private account data access
     */
    private function testPrivateData()
    {
        $this->line('');
        $this->line('🔐 Testing private account data...');

        try {
            // Create integration service with configured credentials
            $apiKey = config('services.tokocrypto.api_key', env('TOKOCRYPTO_API_KEY'));
            $secret = config('services.tokocrypto.secret', env('TOKOCRYPTO_API_SECRET'));
            $sandbox = config('services.tokocrypto.sandbox', env('TOKOCRYPTO_SANDBOX', true));

            $tokocryptoIntegration = new TokocryptoIntegration($apiKey, $secret, $sandbox);
            $balance = $tokocryptoIntegration->getBalance();

            if (!$balance) {
                $this->error('❌ Could not fetch account balance');
                $this->warn('This usually means your API credentials are invalid or expired.');
                $this->warn('Please verify your credentials in the Tokocrypto dashboard.');
                return;
            }

            $this->info('✅ Private account data access working');
            $this->line('Account balance retrieved successfully!');

            // Show non-zero balances
            $nonZeroBalances = [];
            if (isset($balance['total']) && is_array($balance['total'])) {
                foreach ($balance['total'] as $currency => $amount) {
                    if (floatval($amount) > 0) {
                        $nonZeroBalances[] = $currency . ': ' . number_format($amount, 8);
                    }
                }
            }

            if (!empty($nonZeroBalances)) {
                $this->line('Non-zero balances:');
                foreach ($nonZeroBalances as $balanceInfo) {
                    $this->line('   ' . $balanceInfo);
                }
            } else {
                $this->warn('No balances found (account may be empty)');
            }

            $this->info('🎉 All tests passed! Your API credentials are valid and working.');

        } catch (\Exception $e) {
            $this->error('❌ Private account data test failed: ' . $e->getMessage());

            if (str_contains($e->getMessage(), 'credentials') || str_contains($e->getMessage(), 'auth')) {
                $this->line('');
                $this->warn('🔧 To fix credential issues:');
                $this->line('1. Log into your Tokocrypto account');
                $this->line('2. Go to API Management section');
                $this->line('3. Generate new API credentials');
                $this->line('4. Update your .env file with the new credentials');
                $this->line('5. Run this test again');
            }
        }
    }

    /**
     * Show final summary
     */
    private function showSummary($allTestsPassed)
    {
        $this->line('');
        $this->line('📋 SUMMARY');
        $this->line('==========');

        if ($allTestsPassed) {
            $this->info('✅ All tests passed! Your Tokocrypto integration is working perfectly.');
            $this->line('');
            $this->info('🚀 You can now:');
            $this->line('   • View real-time account balance');
            $this->line('   • Execute trades with USDT amounts');
            $this->line('   • Monitor trade status and history');
            $this->line('   • Use all trading features');
        } else {
            $this->warn('⚠️ Some tests failed. Please check your configuration.');
            $this->line('');
            $this->info('🔧 Next steps:');
            $this->line('   • Verify your API credentials');
            $this->line('   • Check network connectivity');
            $this->line('   • Run this test again after fixes');
        }

        $this->line('');
        $this->line('💡 Tip: Use --detailed flag for more technical information');
    }
}