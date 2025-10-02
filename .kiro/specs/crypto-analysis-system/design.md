# Dokumen Desain - Sistem Analisa Crypto Advanced Pattern Analysis

## Gambaran Umum

Desain ini fokus pada pembuatan class analisis baru yang meng-extend `AbstractAnalysis` untuk mengimplementasikan strategi advanced pattern recognition. Implementasi akan berupa single PHP class file yang ditempatkan di direktori `app/Analysis/`, mengikuti pola yang sama seperti `HybridTrendRangeStrategy.php`.

## Arsitektur

### Struktur Class
```
AbstractAnalysis (existing)
    ↓ extends
AdvancedCryptoAnalysisStrategy (new)
```

Class baru akan:
- Meng-extend `AbstractAnalysis` 
- Mengimplementasikan semua abstract methods yang required
- Menggunakan inherited `MarketDataInterface` provider untuk akses data
- Mengembalikan standardized analysis objects

### Lokasi File
- **Path**: `app/Analysis/AdvancedCryptoAnalysisStrategy.php`
- **Namespace**: Mengikuti struktur Laravel app yang ada
- **Class Name**: `AdvancedCryptoAnalysisStrategy`

## Komponen dan Interface

### Implementasi Abstract Method yang Required

#### 1. `getCode(): string`
- Return unique identifier: `'advanced_crypto_analysis_v1'`
- Digunakan untuk identifikasi strategy dan logging

#### 2. `getName(): string`  
- Return human-readable name: `'Advanced Crypto Analysis V1: Multi-Pattern & Technical Indicators'`
- Digunakan untuk display dan reporting

#### 3. `analyze(): object`
- Main analysis method yang mengorkestrasikan semua pattern detection
- Menggunakan inherited methods dari AbstractAnalysis:
  - `$this->getHistoricalData()` - Get market data
  - `$this->getPrice()` - Get current price
- Mengembalikan standardized analysis object dengan semua field yang required

### Komponen Analisis Inti

#### Engine Deteksi Pattern
```php
// Deteksi Candlestick Pattern
private function detectBullishCandlestickPatterns(): ?string
private function detectBearishCandlestickPatterns(): ?string  
private function detectIndecisionPatterns(): ?string

// Deteksi Chart Pattern  
private function detectDoubleTopBottom(): ?array
private function detectTrianglePatterns(): ?array
private function detectFlagPennantPatterns(): ?array
private function detectWedgePatterns(): ?array

// Deteksi Support/Resistance
private function identifyHorizontalSupportResistance(): array
private function identifyDynamicSupportResistance(): array
private function calculateFibonacciLevels(): array
```

#### Kalkulasi Technical Indicator
```php
private function calculateEMA(array $prices, int $period): array
private function calculateRSI(array $prices, int $period = 14): array
private function calculateMACD(array $prices): array
private function calculateBollingerBands(array $prices, int $period = 20): array
private function calculateATR(array $highs, array $lows, array $closes, int $period = 14): array
private function calculateVWAP(array $highs, array $lows, array $closes, array $volumes): array
private function calculateVolumeProfile(array $prices, array $volumes): array
```

#### Engine Analisis Market
```php
private function detectMarketPhase(): string
private function identifyTrendDirection(): string
private function calculateTrendStrength(): float
private function detectMarketStructure(): array
private function assessVolatility(): float
```

#### Kalkulator Risk Management
```php
private function calculateDynamicStopLoss(float $entry, float $atr, string $trendDirection): float
private function calculateVolatilityAdjustedTargets(float $entry, float $atr, array $resistanceLevels): array
private function calculateRiskRewardRatio(float $entry, float $stopLoss, float $takeProfit): string
private function determinePositionSizing(float $accountBalance, float $riskPercentage, float $stopLoss): float
```

#### System Scoring dan Confidence
```php
private function calculatePatternConfluence(array $detectedPatterns): float
private function calculateIndicatorConfluence(array $indicators): float
private function calculateOverallConfidence(float $patternScore, float $indicatorScore, float $volumeConfirmation): float
private function generateTradingRecommendation(float $confidence, string $marketPhase): array
```

## Model Data

### Struktur Input Data
Class akan menggunakan data dari inherited methods `AbstractAnalysis`:

```php
// Dari $this->getHistoricalData()
$historical = [
    ['open' => float, 'high' => float, 'low' => float, 'close' => float, 'volume' => float],
    // ... more candles
];

// Dari $this->getPrice()  
$currentPrice = float;
```

### Analysis Output Object
```php
return (object)[
    'title' => string,              // Judul analisis
    'description' => array,         // Step-by-step explanation dari analysis flow
    'signal' => string,             // 'BUY' | 'SELL' | 'NEUTRAL'
    'confidence' => float,          // 0-100 confidence score
    'score' => int,                 // 
    'price' => float,              // Current market price
    'entry' => float,              // Suggested entry price
    'stop_loss' => float,          // Dynamic stop loss level dengan ATR
    'take_profit' => float,        // Volatility-adjusted target
    'risk_reward' => string,       // Risk-reward ratio (e.g., '1:2')
    'indicators' => array,         // Semua calculated indicators
    'notes' => array,             // Analysis insights dan reasoning
    'patterns' => array,          // Detected patterns dengan details
    'market_phase' => string,     // Current market condition
    'volatility_factor' => float, // ATR-based volatility measure
    'support_levels' => array,    // Identified support levels
    'resistance_levels' => array, // Identified resistance levels
    'trend_direction' => string,  // Bullish/Bearish/Sideways
    'trend_strength' => float,    // Kekuatan trend (0-100)
];
```

## Strategi Implementasi Berdasarkan Market Condition

### 1. Bullish Trend Strategy
```php
private function analyzeBullishTrend(array $data): array
{
    // Implementasi logic untuk:
    // - Buy in support level detection
    // - Breakout resistance analysis
    // - EMA 50 & 200 validation
    // - Higher High & Higher Low confirmation
    // - RSI > 50 validation
    // - Fibonacci retracement analysis
}
```

### 2. Bearish Trend Strategy
```php
private function analyzeBearishTrend(array $data): array
{
    // Implementasi logic untuk:
    // - Strong support/demand zone identification
    // - Reversal confirmation patterns
    // - RSI < 30 oversold analysis
    // - Volume confirmation
    // - Multi-timeframe analysis
}
```

### 3. Sideways Market Strategy
```php
private function analyzeSidewaysMarket(array $data): array
{
    // Implementasi logic untuk:
    // - Range identification
    // - Support/resistance horizontal levels
    // - Mean reversion opportunities
    // - Volume analysis untuk breakout potential
}
```

## Error Handling

### Validasi Data
```php
// Minimum data requirements
if (count($historical) < 50) {
    throw new \Exception("Data tidak mencukupi untuk advanced analysis (minimum 50 candles diperlukan).");
}

// Data quality checks
private function validateDataQuality(array $historical): bool
private function handleMissingData(array $historical): array
private function sanitizeVolumeData(array $volumes): array
```

### Graceful Degradation
- Jika advanced patterns tidak dapat dideteksi, fallback ke basic technical analysis
- Jika data volume tidak mencukupi, skip volume-based confirmations
- Jika calculation errors terjadi, provide basic signal dengan confidence yang lebih rendah

## Strategi Testing

### Pendekatan Unit Testing
1. **Pattern Detection Tests**
   - Test setiap pattern detection method dengan known market data
   - Verify pattern identification accuracy
   - Test edge cases dan false positives

2. **Technical Indicator Tests**
   - Validate calculation accuracy terhadap known values
   - Test dengan different data sizes dan market conditions
   - Verify indicator convergence dan divergence detection

3. **Integration Tests**
   - Test dengan real market data dari different market phases
   - Verify complete analysis flow dari data input ke signal output
   - Test error handling dengan invalid atau insufficient data

4. **Risk Management Tests**
   - Verify stop loss dan take profit calculations
   - Test volatility adjustments under different market conditions
   - Validate risk-reward ratio calculations

### Test Data Requirements
- Historical data sets yang merepresentasikan different market conditions:
  - Strong trending markets (bull/bear)
  - Range-bound/sideways markets  
  - High volatility periods
  - Low volatility periods
  - Pattern formation periods

## Implementation Flow

### Sequence Eksekusi Analisis
1. **Data Acquisition**
   - Get historical data via `$this->getHistoricalData()`
   - Get current price via `$this->getPrice()`
   - Validate data quality dan sufficiency

2. **Market Context Analysis**
   - Detect current market phase (trending/ranging)
   - Calculate volatility measures (ATR)
   - Identify key support/resistance levels

3. **Pattern Recognition**
   - Scan untuk candlestick patterns (15+ types)
   - Detect chart patterns (Double Top/Bottom, Triangles, dll.)
   - Assess pattern completion dan reliability

4. **Technical Analysis**
   - Calculate multi-timeframe indicators
   - Detect indicator convergence/divergence
   - Assess momentum dan trend strength

5. **Signal Generation**
   - Combine pattern dan indicator signals
   - Apply market phase-specific logic
   - Calculate confidence scores berdasarkan confluence

6. **Risk Management**
   - Calculate dynamic stop loss menggunakan ATR
   - Set volatility-adjusted take profit targets
   - Determine optimal position sizing

7. **Output Generation**
   - Compile comprehensive analysis object
   - Generate step-by-step explanations
   - Provide actionable recommendations

Desain ini memastikan implementasi yang focused, single-file yang memanfaatkan existing abstract class infrastructure sambil menyediakan advanced pattern recognition capabilities yang sesuai dengan requirements crypto analysis system.