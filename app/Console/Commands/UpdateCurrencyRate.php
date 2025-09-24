<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CurrencyRateService;
use App\Helpers\CurrencyHelper;

class UpdateCurrencyRate extends Command
{
    protected $signature = 'currency:update {--force : Force update even if cached}';
    protected $description = 'Update USD to IDR exchange rate from API and cache it';

    public function handle()
    {
        $this->info('🔄 Updating USD to IDR exchange rate...');

        $rateService = new CurrencyRateService();

        // Clear cache if force option is used
        if ($this->option('force')) {
            $this->info('🗑️ Clearing existing cache...');
            $rateService->clearCache();
        }

        // Check if rate is already cached
        if (!$this->option('force') && $rateService->isRateCached()) {
            $cachedRate = $rateService->getCachedRate();
            $this->info("✅ Rate already cached: $cachedRate");
            $this->info("📅 Cache expires: " . $rateService->getCacheExpiration()->format('Y-m-d H:i:s'));
            $this->info("💡 Use --force to refresh the rate");
            return self::SUCCESS;
        }

        // Fetch new rate
        $this->info('🌐 Fetching rate from API...');
        $newRate = $rateService->getUSDToIDRRate();

        if ($newRate > 0) {
            // Update CurrencyHelper with new rate
            CurrencyHelper::setExchangeRate($newRate);

            $this->info("✅ Successfully updated exchange rate:");
            $this->info("💰 USD to IDR: $newRate");
            $this->info("📅 Cache expires: " . $rateService->getCacheExpiration()->format('Y-m-d H:i:s'));

            // Show formatted examples
            $this->info("\n📊 Example conversions:");
            $this->info("💵 $100 USD = Rp " . number_format(100 * $newRate, 0, ',', '.'));
            $this->info("💵 $1,000 USD = Rp " . number_format(1000 * $newRate, 0, ',', '.'));
            $this->info("💵 $10,000 USD = Rp " . number_format(10000 * $newRate, 0, ',', '.'));

            return self::SUCCESS;
        }

        $this->error('❌ Failed to fetch exchange rate from API');
        $this->error('💡 The system will use the fallback rate from configuration');
        return self::FAILURE;
    }
}