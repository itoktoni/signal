<?php

namespace App\Services;

use ccxt\tokocrypto;

class TokocryptoIntegration
{
    private $exchange;

    public function __construct(string $apiKey = '', string $secret = '', bool $sandbox = true)
    {
        try {
            // Ensure CCXT autoloader is loaded - try multiple approaches
            $ccxtLoaded = false;

            // Method 1: Check if already loaded
            if (class_exists('\ccxt\Exchange')) {
                $ccxtLoaded = true;
            }

            // Method 2: Try to load manually if not already loaded
            if (!$ccxtLoaded) {
                $autoloadFile = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($autoloadFile)) {
                    require_once $autoloadFile;
                    $ccxtLoaded = class_exists('\ccxt\Exchange');
                }
            }

            // Method 3: Direct include if autoloader fails
            if (!$ccxtLoaded) {
                $ccxtFile = __DIR__ . '/../vendor/ccxt/ccxt/php/tokocrypto.php';
                if (file_exists($ccxtFile)) {
                    require_once $ccxtFile;
                    $ccxtLoaded = class_exists('\ccxt\tokocrypto');
                }
            }

            if (!$ccxtLoaded) {
                throw new \Exception('CCXT library not found. Please ensure CCXT is properly installed via Composer.');
            }

            $config = [
                'enableRateLimit' => true,
                'rateLimit' => 1000,
                'timeout' => 30000,
            ];

            // Only set API credentials and sandbox for private operations
            // Public market data should use live exchange without credentials
            if (!empty($apiKey) && !empty($secret)) {
                $config['apiKey'] = $apiKey;
                $config['secret'] = $secret;
                // Only use sandbox if explicitly requested for trading operations
                if ($sandbox) {
                    $config['sandbox'] = true;
                }
            }

            $this->exchange = new \ccxt\tokocrypto($config);
        } catch (\Exception $e) {
            throw new \Exception('Failed to initialize tokocrypto exchange: ' . $e->getMessage());
        }
    }

    public function initialize(): bool
    {
        try {
            $this->exchange->load_markets();
            echo "Tokocrypto exchange initialized successfully\n";
            echo "Available symbols: " . count($this->exchange->symbols) . "\n";
            return true;
        } catch (\Exception $e) {
            echo "Failed to initialize Tokocrypto exchange: " . $e->getMessage() . "\n";
            return false;
        }
    }

    public function getBalance(): ?array
    {
        try {
            return $this->exchange->fetch_balance();
        } catch (\Exception $e) {
            echo "Error fetching balance: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function getTicker(string $symbol = 'BTC/USDT'): ?array
    {
        try {
            return $this->exchange->fetch_ticker($symbol);
        } catch (\Exception $e) {
            echo "Error fetching ticker for {$symbol}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function getOrderBook(string $symbol = 'BTC/USDT', int $limit = 100): ?array
    {
        try {
            return $this->exchange->fetch_order_book($symbol, $limit);
        } catch (\Exception $e) {
            echo "Error fetching order book for {$symbol}: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function createLimitBuyOrder(string $symbol, float $amount, float $price): ?array
    {
        try {
            $order = $this->exchange->create_limit_buy_order($symbol, $amount, $price);
            echo "Limit buy order created: " . json_encode($order) . "\n";
            return $order;
        } catch (\Exception $e) {
            echo "Error creating limit buy order: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function createLimitSellOrder(string $symbol, float $amount, float $price): ?array
    {
        try {
            $order = $this->exchange->create_limit_sell_order($symbol, $amount, $price);
            echo "Limit sell order created: " . json_encode($order) . "\n";
            return $order;
        } catch (\Exception $e) {
            echo "Error creating limit sell order: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function createMarketBuyOrder(string $symbol, float $amount): ?array
    {
        try {
            $order = $this->exchange->create_market_buy_order($symbol, $amount);
            echo "Market buy order created: " . json_encode($order) . "\n";
            return $order;
        } catch (\Exception $e) {
            echo "Error creating market buy order: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function createMarketSellOrder(string $symbol, float $amount): ?array
    {
        try {
            $order = $this->exchange->create_market_sell_order($symbol, $amount);
            echo "Market sell order created: " . json_encode($order) . "\n";
            return $order;
        } catch (\Exception $e) {
            echo "Error creating market sell order: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function getOpenOrders(string $symbol = null): ?array
    {
        try {
            return $this->exchange->fetch_open_orders($symbol);
        } catch (\Exception $e) {
            echo "Error fetching open orders: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function cancelOrder(string $orderId, string $symbol = null): ?array
    {
        try {
            $result = $this->exchange->cancel_order($orderId, $symbol);
            echo "Order cancelled: " . json_encode($result) . "\n";
            return $result;
        } catch (\Exception $e) {
            echo "Error cancelling order: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function getOrderStatus(string $orderId, string $symbol = null): ?array
    {
        try {
            return $this->exchange->fetch_order($orderId, $symbol);
        } catch (\Exception $e) {
            echo "Error fetching order status: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function getAvailableSymbols(): array
    {
        return array_keys($this->exchange->symbols);
    }

    public function getExchangeInfo(): array
    {
        return [
            'id' => $this->exchange->id,
            'name' => $this->exchange->name,
            'countries' => $this->exchange->countries,
            'urls' => $this->exchange->urls,
            'version' => $this->exchange->version,
            'rateLimit' => $this->exchange->rateLimit,
        ];
    }

    public function getExchange(): tokocrypto
    {
        return $this->exchange;
    }
}