# Tokocrypto Trading Services

This document explains how to use the Tokocrypto trading services in your Laravel application.

## Available Services

### 1. TradeService (`App\Services\TradeService`)
Core service for executing trades, managing orders, and syncing with the exchange.

### 2. TokocryptoIntegration (`App\Services\TokocryptoIntegration`)
Low-level integration service that wraps CCXT library for Tokocrypto exchange operations.

### 3. TokocryptoTrader (`App\Services\TokocryptoTrader`)
High-level service that provides a simplified interface for common trading operations.

## Usage Examples

### In Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Services\TokocryptoTrader;
use App\Models\Trade;

class TradingController extends Controller
{
    public function buyBitcoin(TokocryptoTrader $trader)
    {
        // Trade $100 worth of BTC
        $result = $trader->usdMarketBuy('BTC/USDT', 100.0);

        if ($result['success']) {
            return response()->json(['message' => 'Order executed successfully']);
        } else {
            return response()->json(['error' => $result['error']], 500);
        }
    }

    public function sellWithBaseAmount(TokocryptoTrader $trader)
    {
        // Sell 0.001 BTC
        $result = $trader->marketSell('BTC/USDT', 0.001);

        if ($result['success']) {
            return response()->json(['message' => 'Order executed successfully']);
        } else {
            return response()->json(['error' => $result['error']], 500);
        }
    }

    public function sellWithLimit(TokocryptoTrader $trader)
    {
        $result = $trader->limitSell('ETH/USDT', 1.0, 2000.0);

        if ($result['success']) {
            return response()->json(['message' => 'Limit order placed successfully']);
        } else {
            return response()->json(['error' => $result['error']], 500);
        }
    }

    public function tradeWithUsdAmount(TokocryptoTrader $trader)
    {
        // Trade exactly $100 worth of BTC
        $result = $trader->usdMarketBuy('BTC/USDT', 100.0);

        if ($result['success']) {
            $baseAmount = $result['base_amount'];
            $conversionPrice = $result['conversion_price'];
            return response()->json([
                'message' => 'Order executed successfully',
                'usd_amount' => $result['usd_amount'],
                'base_amount' => $baseAmount,
                'conversion_price' => $conversionPrice
            ]);
        } else {
            return response()->json(['error' => $result['error']], 500);
        }
    }

    public function executeTradeFromModel(TokocryptoTrader $trader, Trade $trade)
    {
        $result = $trader->executeTrade($trade);

        if ($result['success']) {
            return response()->json(['message' => 'Trade executed successfully']);
        } else {
            return response()->json(['error' => $result['error']], 500);
        }
    }
}
```

### In Console Commands

```php
<?php

namespace App\Console\Commands;

use App\Services\TokocryptoTrader;
use Illuminate\Console\Command;

class AutoTradeCommand extends Command
{
    protected $signature = 'trade:auto {symbol} {amount}';
    protected $description = 'Execute automated trades';

    public function handle(TokocryptoTrader $trader)
    {
        $symbol = $this->argument('symbol');
        $amount = $this->argument('amount');

        $result = $trader->marketBuy($symbol, $amount);

        if ($result['success']) {
            $this->info('Trade executed successfully');
        } else {
            $this->error('Trade failed: ' . $result['error']);
        }
    }
}
```

### In Queued Jobs

```php
<?php

namespace App\Jobs;

use App\Models\Trade;
use App\Services\TokocryptoTrader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessTradeJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $trade;

    public function __construct(Trade $trade)
    {
        $this->trade = $trade;
    }

    public function handle(TokocryptoTrader $trader)
    {
        $result = $trader->executeTrade($this->trade);

        if (!$result['success']) {
            logger('Trade execution failed: ' . $result['error']);
        }
    }
}
```

## Available Methods

### TokocryptoTrader Methods

#### Trading Operations
- `marketBuy(string $symbol, float $amount): array` - Market buy with base currency amount
- `marketSell(string $symbol, float $amount): array` - Market sell with base currency amount
- `limitBuy(string $symbol, float $amount, float $price): array` - Limit buy with base currency amount
- `limitSell(string $symbol, float $amount, float $price): array` - Limit sell with base currency amount

#### USDT-Based Trading Operations
- `usdMarketBuy(string $symbol, float $usdAmount): array` - Market buy with USDT amount
- `usdMarketSell(string $symbol, float $usdAmount): array` - Market sell with USDT amount
- `usdLimitBuy(string $symbol, float $usdAmount, float $price): array` - Limit buy with USDT amount
- `usdLimitSell(string $symbol, float $usdAmount, float $price): array` - Limit sell with USDT amount

#### Trade Management
- `executeTrade(Trade $trade): array`
- `cancelTrade(Trade $trade): array`
- `getTradeStatus(Trade $trade): array`

#### Market Data
- `getBalance(string $currency = null): array`
- `getTicker(string $symbol): array`
- `getAvailableSymbols(): array`

#### Utilities
- `testConnection(): array`
- `syncActiveTrades(): array`

### TradeService Methods

#### Core Trading
- `executeTrade(Trade $trade): array`
- `cancelTrade(Trade $trade): array`
- `getTradeStatus(Trade $trade): array`

#### Market Data
- `getAvailableTradingPairs(): array`
- `getBalance(string $currency = null): array|float`
- `getTicker(string $symbol): array`
- `getRecentTrades(string $symbol, int $limit = 100): array`

#### Utilities
- `testConnection(): array`
- `getExchange(): Exchange` - Returns the CCXT exchange instance

### TokocryptoIntegration Methods

#### Trading Operations
- `createMarketBuyOrder(string $symbol, float $amount): array`
- `createMarketSellOrder(string $symbol, float $amount): array`
- `createLimitBuyOrder(string $symbol, float $amount, float $price): array`
- `createLimitSellOrder(string $symbol, float $amount, float $price): array`

#### Order Management
- `getOpenOrders(string $symbol = null): array`
- `cancelOrder(string $orderId, string $symbol = null): array`
- `getOrderStatus(string $orderId, string $symbol = null): array`

#### Market Data
- `getBalance(): array`
- `getTicker(string $symbol = 'BTC/USDT'): array`
- `getOrderBook(string $symbol = 'BTC/USDT', int $limit = 100): array`

#### Utilities
- `initialize(): bool`
- `getAvailableSymbols(): array`
- `getExchangeInfo(): array`
- `getExchange(): tokocrypto` - Returns the CCXT exchange instance

## Configuration

Add your Tokocrypto API credentials to your `.env` file:

```env
TOKOCRYPTO_API_KEY=your_api_key_here
TOKOCRYPTO_SECRET=your_secret_here
TOKOCRYPTO_SANDBOX=false
```

And in `config/services.php`:

```php
'tokocrypto' => [
    'api_key' => env('TOKOCRYPTO_API_KEY'),
    'secret' => env('TOKOCRYPTO_SECRET'),
    'sandbox' => env('TOKOCRYPTO_SANDBOX', false),
],
```

## Error Handling

All services return arrays with a `success` key indicating the operation status:

```php
$result = $trader->marketBuy('BTC/USDT', 0.001);

if ($result['success']) {
    // Operation successful
    $order = $result['order'];
} else {
    // Operation failed
    $error = $result['error'];
}
```

## Logging

All trading operations are automatically logged with relevant details for debugging and monitoring.

## Best Practices

1. **Use Dependency Injection**: Always inject the services rather than instantiating them directly
2. **Handle Errors**: Always check the `success` key in the response
3. **Log Operations**: Use the built-in logging for production debugging
4. **Validate Inputs**: Validate symbols, amounts, and prices before calling service methods
5. **Use Queues for Heavy Operations**: Consider using queued jobs for bulk operations or complex trading strategies

## Console Commands

### API Credential Testing
Test your Tokocrypto API credentials before trading:

```bash
# Test if your API credentials are valid and working
php artisan tokocrypto:test-credentials

# Get detailed technical information
php artisan tokocrypto:test-credentials --detailed
```

**Example Output:**
```
🔐 Testing Tokocrypto API Credentials

📋 Step 1: Checking credential configuration...
❌ API credentials are not configured

To configure your credentials, add the following to your .env file:
TOKOCRYPTO_API_KEY=your_api_key_here
TOKOCRYPTO_SECRET=your_secret_here
TOKOCRYPTO_SANDBOX=false

🌐 Step 2: Testing exchange connection...
✅ Exchange connection successful
   Exchange: Tokocrypto
   ID: tokocrypto

⚡ Step 3: Testing API functionality...
📊 Testing public market data...
✅ Public market data access working
   BTC/USDT Price: $122101.99

🔐 Testing private account data...
❌ Could not fetch account balance
This usually means your API credentials are invalid or expired.
```

### Trading Operations
The `TokocryptoTradeCommand` provides a complete CLI interface for trading operations:

```bash
# Get account balance
php artisan tokocrypto:trade balance

# Get ticker information
php artisan tokocrypto:trade ticker --symbol=BTC/USDT

# Execute a market buy order (trades exactly $100 worth of BTC - USDT is the only mode!)
php artisan tokocrypto:trade execute --symbol=BTC/USDT --side=buy --type=market --amount=100

# Execute a limit sell order (sells $200 worth of ETH at $2000 - immediate execution)
php artisan tokocrypto:trade execute --symbol=ETH/USDT --side=sell --type=limit --amount=200 --price=2000

# All amounts are in USDT by default - no need for special flags!

# Check trade status
php artisan tokocrypto:trade status --trade-id=TRD-1234567890

# Sync all active trades
php artisan tokocrypto:trade sync
```

## Integration with Existing Code

The services are already integrated with the existing `TradeController` and can be used alongside the current implementation. The `Trade` model works seamlessly with all the services for database operations.