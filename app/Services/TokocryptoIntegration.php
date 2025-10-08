<?php

namespace App\Services;

use ccxt\tokocrypto;

class TokocryptoIntegration
{
    private $exchange;

    public function __construct(string $apiKey = '', string $secret = '', bool $sandbox = true)
    {
        try {
            // Only set credentials if they are provided
            $config = [
                'enableRateLimit' => true,
                'rateLimit' => 1000,
                'timeout' => 30000,
            ];

            // Only set API credentials if they are provided
            if (!empty($apiKey) && !empty($secret)) {
                $config['apiKey'] = $apiKey;
                $config['secret'] = $secret;
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
            $this->exchange->loadMarkets();
            echo "Tokocrypto exchange initialized successfully\n";
            return true;
        } catch (\Exception $e) {
            echo "Failed to initialize Tokocrypto exchange: " . $e->getMessage() . "\n";

            // Try alternative initialization
            try {
                echo "Trying alternative initialization method...\n";
                $this->exchange->fetchMarkets();
                echo "Alternative initialization successful\n";
                return true;
            } catch (\Exception $e2) {
                echo "Alternative initialization also failed: " . $e2->getMessage() . "\n";
                return false;
            }
        }
    }

    public function getBalance(): ?array
    {
        try {
            // Check if we have API credentials
            if (empty($this->exchange->apiKey)) {
                throw new \Exception('API credentials not configured');
            }

            $response = $this->exchange->fetch_balance();

            // Validate that response is an array (CCXT should return array)
            if (!is_array($response)) {
                throw new \Exception('Invalid response format from exchange');
            }

            return $response;
        } catch (\Exception $e) {
            // Handle JSON parsing errors specifically
            if (str_contains($e->getMessage(), 'JSON') || str_contains($e->getMessage(), 'token')) {
                echo "Error fetching balance: Invalid response from exchange (possibly HTML error page)\n";
                echo "This usually means API credentials are not configured or invalid.\n";
                throw new \Exception('Invalid API response - check credentials');
            }

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
        try {
            $markets = $this->exchange->loadMarkets();
            return $this->exchange->symbols ?? array_keys($markets);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getExchangeInfo(): array
    {
        try {
            return [
                'id' => $this->exchange->id ?? 'tokocrypto',
                'name' => $this->exchange->name ?? 'Tokocrypto',
                'countries' => $this->exchange->countries ?? ['ID'],
                'urls' => $this->exchange->urls ?? [],
                'version' => $this->exchange->version ?? '1.0',
                'rateLimit' => $this->exchange->rateLimit ?? 1000,
            ];
        } catch (\Exception $e) {
            return [
                'id' => 'tokocrypto',
                'name' => 'Tokocrypto',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getExchange(): tokocrypto
    {
        return $this->exchange;
    }
}