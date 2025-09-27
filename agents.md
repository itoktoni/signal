# Crypto Analyst AGENTS.md

## General Guidelines
- **Language/Framework:** this is laravel framework 12.
- **Styling:** Use chotta CSS for utility-first styling. Avoid custom CSS files where possible.

## Development Environment
- **this using laravel 12

## Code Style and Formatting
- if we make have if make like
- Function brackets: Place opening brace on the same line as the function declaration.
  Example:
  ```php
  public function index()
  {
      return redirect()->route($this->module('getData'));
  }
  ```

## Component Usage Examples
- **Input Component:** `<x-input type="email" name="email" col="12" required/>`
- **Select Component:** `<x-select name="role" :options="$users" option-key="id" option-value="name" required searchable multiple/>`
- **Textarea Component:** `<x-textarea name="description" rows="4" required/>`
- **Footer Component:** `<x-footer> <x-button type="submit" class="primary">Submit</x-button> </x-footer>`
- **Sort Link Component:** `<x-sort-link column="id" route="user.getData" text="ID" />`
- prefer to make like
<x-input type="email" name="email" col="12" required/>

## JavaScript Guidelines
- Move all page-specific JavaScript to `resources/js/app.js` instead of inline scripts in Blade views.
- Use global functions for reusable logic like confirmations.
- Wrap page-specific code in checks for element existence.

## Feature Analysis
### Form Fields
- **Crypto Symbol:** (dropdown) Static array of options for the dropdown e.g. BTCUSDT, ETHUSDT, etc.
- **Metode Analisis:** (dropdown) Static array of options for the dropdown e.g. Sniper, Support Resistant, etc.

### Code Structure
- **Controller:** `App\Http\Controllers\CoinController`
- **Model:** `App\Models\Coin`
- **Method:** `getUpdate` method in `CoinController`
- **View:** `resources/views/coin/update.blade.php` form using GET with query string parameters for the form.
- **Service:** `App\Analysis\AnalysisService` gets parameters crypto symbol and method analysis, with each service having a single file class for each analysis. E.g. `App\Analysis\SniperService`
- **Callable:** This service or action can be called and used by controller, console jobs, or anything in Laravel.

### Service Results Structure
The service will always and mandatory to return an object containing:

| Property | Description |
|----------|-------------|
| **signal** | Long or short |
| **confidence** | 95% (calculated percentage from analysis functions) |
| **entry_usd** | Entry price in USD |
| **entry_idr** | Entry price in Rupiah |
| **stop_loss_usd** | Stop loss price in USD |
| **stop_loss_idr** | Stop loss price in Rupiah |
| **take_profit_usd** | Take profit price in USD |
| **take_profit_idr** | Take profit price in Rupiah |
| **risk_reward** | Risk:reward ratio e.g. 1:3 |

### Fee Structure (Pluang PRO - Kripto Futures)
Based on the correct fee structure for Kripto Futures on Pluang PRO:

**Maker Fee:**
- Transaction fee: 0.10%
- PPN on transaction fee: 0.011%
- CFX fee: 0.05%
- PPN on CFX fee: 0.0055%
- **Total Maker Fee: 0.1665%**

**Taker Fee:**
- Transaction fee: 0.10%
- PPN on transaction fee: 0.011%
- CFX fee: 0.15%
- PPN on CFX fee: 0.0165%
- **Total Taker Fee: 0.2775%**

**Additional Costs:**
- Slippage: 0.5% (estimated)

### Implementation Flow
1. User selects crypto symbol and analysis method from dropdowns
2. Input amount example we want trade $100 dollar, make it default and we can change it and fee will calculate from this
3. Form submits to `CoinController@getUpdate` via GET with query parameters
4. Controller validates inputs and calls appropriate analysis service
5. Service performs technical analysis using relevant indicators
6. Service returns standardized result object
7. Controller passes result to view for display
8. View renders analysis results in structured format

### Available Analysis Methods
- **Sniper:** High precision entry signals based on volume and price action
- **Support Resistant:** Classic support and resistance level analysis
- **Dynamic RR:** Dynamic risk-reward calculation using ATR, Fibonacci levels, and support/resistance

### Service Class Structure
Each analysis service should extend a base AnalysisService class and implement a consistent interface:
```php
interface AnalysisInterface
{
    public function analyze(string $symbol, float $amount = 1000): object;
    public function getName(): string;
}
```
