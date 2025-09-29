<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSymbolMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:generate-mapping {--source=} {--output=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate symbol mapping from JSON source to various API providers';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $source = $this->option('source') ?? database_path('response_1759028389538.json');
        $output = $this->option('output') ?? config_path('crypto_symbol_mapping.php');

        if (!File::exists($source)) {
            $this->error("Source file not found: {$source}");
            return 1;
        }

        $jsonData = json_decode(File::get($source), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON in source file: " . json_last_error_msg());
            return 1;
        }

        $symbols = $jsonData['result'] ?? [];

        if (empty($symbols)) {
            $this->error("No symbols found in JSON data");
            return 1;
        }

        // Generate mappings for different providers
        $mappings = [
            'freecryptoapi' => [],
            'binance' => [],
            'coingecko' => [],
            'coincappro' => [],
            'coinlore' => [],
            'coinpaprika' => [],
        ];

        foreach ($symbols as $symbolData) {
            $symbol = $symbolData['symbol'] ?? '';
            $sourceExchange = $symbolData['source'] ?? '';

            if (empty($symbol)) {
                continue;
            }

            // FreeCryptoAPI mapping (main format)
            // Convert any symbol ending with USDT to SYMBOL-USDT format
            // For symbols not ending with USDT, keep as is
            if (substr($symbol, -4) === 'USDT') {
                $freecryptoSymbol = substr($symbol, 0, -4) . '-USDT';
            } else {
                $freecryptoSymbol = $symbol;
            }
            $mappings['freecryptoapi'][$symbol] = $freecryptoSymbol;

            // Binance mapping (keep as is for USDT pairs, skip non-USDT pairs)
            if (substr($symbol, -4) === 'USDT') {
                $mappings['binance'][$symbol] = $symbol;
            }

            // CoinGecko mapping (needs coin IDs)
            $coingeckoId = $this->mapToCoinGeckoId($symbol);
            if ($coingeckoId) {
                $mappings['coingecko'][$symbol] = $coingeckoId;
            }

            // CoinCap mapping (needs coin IDs)
            $coincapId = $this->mapToCoinCapId($symbol);
            if ($coincapId) {
                $mappings['coincappro'][$symbol] = $coincapId;
            }

            // CoinLore mapping (needs coin IDs)
            $coinloreId = $this->mapToCoinLoreId($symbol);
            if ($coinloreId) {
                $mappings['coinlore'][$symbol] = $coinloreId;
            }

            // Coinpaprika mapping (needs coin IDs)
            $coinpaprikaId = $this->mapToCoinpaprikaId($symbol);
            if ($coinpaprikaId) {
                $mappings['coinpaprika'][$symbol] = $coinpaprikaId;
            }
        }

        // Add common USDT pairs that might be missing
        $commonPairs = [
            'BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'ADAUSDT', 'XRPUSDT', 'SOLUSDT',
            'DOTUSDT', 'DOGEUSDT', 'AVAXUSDT', 'LTCUSDT', 'LINKUSDT', 'MATICUSDT',
            'ALGOUSDT', 'UNIUSDT', 'ATOMUSDT', 'VETUSDT', 'ICPUSDT', 'FILUSDT',
            'TRXUSDT', 'ETCUSDT'
        ];

        foreach ($commonPairs as $pair) {
            // Ensure FreeCryptoAPI mapping exists
            if (!isset($mappings['freecryptoapi'][$pair])) {
                $freecryptoSymbol = substr($pair, 0, -4) . '-USDT';
                $mappings['freecryptoapi'][$pair] = $freecryptoSymbol;
            }

            // Ensure Binance mapping exists
            if (!isset($mappings['binance'][$pair])) {
                $mappings['binance'][$pair] = $pair;
            }

            // Ensure CoinGecko mapping exists
            if (!isset($mappings['coingecko'][$pair])) {
                $coingeckoId = $this->mapToCoinGeckoId($pair);
                if ($coingeckoId) {
                    $mappings['coingecko'][$pair] = $coingeckoId;
                }
            }

            // Ensure CoinCap mapping exists
            if (!isset($mappings['coincappro'][$pair])) {
                $coincapId = $this->mapToCoinCapId($pair);
                if ($coincapId) {
                    $mappings['coincappro'][$pair] = $coincapId;
                }
            }

            // Ensure CoinLore mapping exists
            if (!isset($mappings['coinlore'][$pair])) {
                $coinloreId = $this->mapToCoinLoreId($pair);
                if ($coinloreId) {
                    $mappings['coinlore'][$pair] = $coinloreId;
                }
            }

            // Ensure Coinpaprika mapping exists
            if (!isset($mappings['coinpaprika'][$pair])) {
                $coinpaprikaId = $this->mapToCoinpaprikaId($pair);
                if ($coinpaprikaId) {
                    $mappings['coinpaprika'][$pair] = $coinpaprikaId;
                }
            }
        }

        // Generate PHP config file
        $configContent = "<?php\n\n// Auto-generated symbol mapping\nreturn [\n";

        foreach ($mappings as $provider => $mapping) {
            $configContent .= "    '{$provider}' => [\n";
            foreach ($mapping as $original => $mapped) {
                $configContent .= "        '{$original}' => '{$mapped}',\n";
            }
            $configContent .= "    ],\n";
        }

        $configContent .= "];\n";

        File::put($output, $configContent);

        $this->info("Symbol mapping generated successfully!");
        $this->info("Output file: {$output}");

        return 0;
    }

    /**
     * Map symbol to CoinGecko ID
     */
    private function mapToCoinGeckoId(string $symbol): ?string
    {
        $mapping = [
            'BTCUSDT' => 'bitcoin',
            'ETHUSDT' => 'ethereum',
            'BNBUSDT' => 'binancecoin',
            'ADAUSDT' => 'cardano',
            'XRPUSDT' => 'ripple',
            'SOLUSDT' => 'solana',
            'DOTUSDT' => 'polkadot',
            'DOGEUSDT' => 'dogecoin',
            'AVAXUSDT' => 'avalanche-2',
            'LTCUSDT' => 'litecoin',
            'LINKUSDT' => 'chainlink',
            'MATICUSDT' => 'matic-network',
            'ALGOUSDT' => 'algorand',
            'UNIUSDT' => 'uniswap',
            'ATOMUSDT' => 'cosmos',
            'VETUSDT' => 'vechain',
            'ICPUSDT' => 'internet-computer',
            'FILUSDT' => 'filecoin',
            'TRXUSDT' => 'tron',
            'ETCUSDT' => 'ethereum-classic',
        ];

        return $mapping[$symbol] ?? null;
    }

    /**
     * Map symbol to CoinCap ID
     */
    private function mapToCoinCapId(string $symbol): ?string
    {
        $mapping = [
            'BTCUSDT' => 'bitcoin',
            'ETHUSDT' => 'ethereum',
            'BNBUSDT' => 'binance-coin',
            'ADAUSDT' => 'cardano',
            'XRPUSDT' => 'xrp',
            'SOLUSDT' => 'solana',
            'DOTUSDT' => 'polkadot',
            'DOGEUSDT' => 'dogecoin',
            'AVAXUSDT' => 'avalanche',
            'LTCUSDT' => 'litecoin',
            'LINKUSDT' => 'chainlink',
            'MATICUSDT' => 'polygon',
            'ALGOUSDT' => 'algorand',
            'UNIUSDT' => 'uniswap',
            'ATOMUSDT' => 'cosmos',
            'VETUSDT' => 'vechain',
            'ICPUSDT' => 'internet-computer',
            'FILUSDT' => 'filecoin',
            'TRXUSDT' => 'tron',
            'ETCUSDT' => 'ethereum-classic',
        ];

        return $mapping[$symbol] ?? null;
    }

    /**
      * Map symbol to CoinLore ID
      */
     private function mapToCoinLoreId(string $symbol): ?string
     {
         $mapping = [
             'BTCUSDT' => '90',
             'ETHUSDT' => '80',
             'BNBUSDT' => '2710',
             'ADAUSDT' => '257',
             'XRPUSDT' => '58',
             'SOLUSDT' => '48543',
             'DOTUSDT' => '11815',
             'DOGEUSDT' => '2',
             'AVAXUSDT' => '23210',
             'LTCUSDT' => '1',
         ];

         return $mapping[$symbol] ?? null;
     }

     /**
      * Map symbol to Coinpaprika ID
      */
     private function mapToCoinpaprikaId(string $symbol): ?string
     {
         $mapping = [
             'BTCUSDT' => 'btc-bitcoin',
             'ETHUSDT' => 'eth-ethereum',
             'BNBUSDT' => 'bnb-binance-coin',
             'ADAUSDT' => 'ada-cardano',
             'XRPUSDT' => 'xrp-xrp',
             'SOLUSDT' => 'sol-solana',
             'DOTUSDT' => 'dot-polkadot',
             'DOGEUSDT' => 'doge-dogecoin',
             'AVAXUSDT' => 'avax-avalanche',
             'LTCUSDT' => 'ltc-litecoin',
             'LINKUSDT' => 'link-chainlink',
             'MATICUSDT' => 'matic-polygon',
             'ALGOUSDT' => 'algo-algorand',
             'UNIUSDT' => 'uni-uniswap',
             'ATOMUSDT' => 'atom-cosmos',
             'VETUSDT' => 'vet-vechain',
             'ICPUSDT' => 'icp-internet-computer',
             'FILUSDT' => 'fil-filecoin',
             'TRXUSDT' => 'trx-tron',
             'ETCUSDT' => 'etc-ethereum-classic',
         ];

         return $mapping[$symbol] ?? null;
     }
 }