# Dokumen Kebutuhan Sistem Analisa Crypto - Advanced Pattern Analysis

## Pengantar

Fitur ini mengimplementasikan sistem analisa cryptocurrency yang komprehensif untuk memberikan signal trading yang cerdas. Sistem menganalisis kondisi pasar dan menghasilkan signal buy, sell, dan sideways dengan strategi entry dan exit yang spesifik untuk berbagai skenario trend. Implementasi menggunakan advanced pattern recognition yang memperluas class AbstractAnalysis dengan deteksi pola candlestick dan chart pattern yang lebih canggih.

## Kebutuhan

### Kebutuhan 1

**User Story:** Sebagai trader crypto, saya ingin menerima signal buy pada berbagai kondisi pasar dengan deteksi pola yang lebih akurat, sehingga saya dapat mengidentifikasi titik entry optimal untuk posisi long.

#### Kriteria Penerimaan

1. KETIKA trend pasar bullish MAKA sistem HARUS mengidentifikasi titik entry dengan mempertimbangkan indikator berikut: 

##### Buy in support level
a. Identifikasi Trend Bullish

- Gunakan EMA 50 & EMA 200 → kalau harga di atas keduanya dan EMA 50 > EMA 200 = valid bullish trend.
- Pastikan harga membuat Higher High (HH) & Higher Low (HL) di price action.
- Gunakan RSI dengan harga diatas 50
- Gunakan timeframe Daily untuk arah trend, entry di 4H atau lebih kecil.

b. Tentukan Area Support

- Cari support horizontal (area harga sering mantul sebelumnya).
- Tambahkan support dinamis EMA 20 / EMA 50 (untuk pullback sehat).
- lihat volume VWAP (intraday).
- Tambahkan Fibonacci retracement dari swing terakhir → area 0.382 – 0.618 sering jadi titik pullback sehat.

c. Konfirmasi di Area Support
- Tunggu harga menyentuh area support/pullback
- Cari sinyal konfirmasi:
  - Candlestick reversal (hammer, bullish engulfing, pin bar, dragonfly doji, bullish harami, three white soldiers)
  - Volume meningkat saat candle bullish terbentuk
  - RSI mantul dari area 50–60 (bullish momentum tetap terjaga)

d. Entry & Risk Management
- Entry Buy / signal entry saat candle konfirmasi close di area support
- Stop Loss (SL) → taruh di bawah swing low / support terdekat menggunakan ATR-based calculation
- Take Profit (TP) → di resistance berikutnya, atau gunakan risk reward 1:2 / 1:3


##### Breakout Resistant

a. Identifikasi Trend Bullish

- Lihat struktur harga → harga bikin Higher High (HH) dan Higher Low (HL).
- Konfirmasi dengan indikator: harga berada di atas EMA 50 / 200 atau RSI > 50.

b. Cari Resistance Kunci
- Lihat area harga yang sering ditolak sebelumnya (double top / cluster rejection)
- Deteksi pola Double Top/Bottom, Triangle patterns, Flag dan Pennant patterns
- Semakin sering area itu disentuh dan gagal ditembus, semakin kuat resistancenya

c. Tunggu Breakout

- Harga menembus resistance dengan candle bullish solid (body panjang, bukan hanya wick).
- Volume harus meningkat → ini kunci validasi breakout.
- Kalau breakout tapi volume kecil → hati-hati false breakout.

d. Cari Entry Setelah Breakout

Ada 2 cara aman:

🔹 (A) Breakout & Close di Atas Resistance (Konfirmasi)

Tunggu candle close di atas resistance.

Entry di candle berikutnya (bisa lebih cepat tapi risiko false breakout lebih tinggi).

🔹 (B) Breakout – Retest – Rejection (Paling Aman)

Setelah breakout, harga biasanya retest kembali ke area resistance (yang sekarang jadi support).
Tunggu candle rejection bullish (hammer, engulfing) di area itu.
Entry di rejection → risiko kecil, konfirmasi kuat.

e. Risk Management
- Stop Loss (SL): taruh di bawah resistance lama (sekarang jadi support) dengan ATR adjustment
- Take Profit (TP): resistance minor berikutnya, kombinasikan dengan Fibonacci extension (1.618)


2. KETIKA trend pasar bearish MAKA sistem HARUS menerapkan strategi khusus:
- Jangan melawan trend besar
- Cari Level Support Kuat / Demand Zone
- Tunggu Konfirmasi Reversal dengan candlestick patterns (hammer, bullish engulfing, morning star)
- Volume meningkat di area support
- RSI < 30 (oversold) lalu naik lagi → tanda potensi rebound

3. KETIKA trend pasar sideways MAKA sistem HARUS:
- Identifikasi range dengan support dan resistance horizontal
- Deteksi pola continuation seperti Rising/Falling Three Methods
- Entry buy di support dengan konfirmasi candlestick reversal
- Risk management: SL 1-2% di bawah support, TP di resistance atas


### Kebutuhan 2

**User Story:** Sebagai trader crypto, saya ingin menerima signal sell dengan strategi exit yang tepat dan deteksi pola bearish yang akurat, sehingga saya dapat memaksimalkan profit dan meminimalkan kerugian.

#### Kriteria Penerimaan

1. KETIKA pola bearish terdeteksi MAKA sistem HARUS mengidentifikasi:
- Hanging Man, Shooting Star, Bearish Engulfing, Dark Cloud Cover, Evening Star
- Gravestone Doji, Bearish Harami, Three Black Crows patterns
- Pattern validation dengan volume dan context confirmation

2. KETIKA signal sell dihasilkan MAKA sistem HARUS menyediakan:
- Entry level yang tepat berdasarkan resistance atau pattern completion
- Stop loss dinamis menggunakan ATR calculation
- Take profit dengan multiple levels berdasarkan support levels

### Kebutuhan 3

**User Story:** Sebagai trader crypto, saya ingin menerima signal pasar sideways dengan deteksi pola indecision yang akurat, sehingga saya dapat menyesuaikan strategi trading untuk pasar range-bound.

#### Kriteria Penerimaan

1. KETIKA pasar dalam trend sideways MAKA sistem HARUS:
- Menghasilkan signal sideways/neutral
- Deteksi pola indecision: Doji variations, Spinning Tops, Inside Bars
- Identifikasi level support (bottom) dan resistance (top) secara dinamis

2. KETIKA kondisi sideways terdeteksi MAKA sistem HARUS:
- Menyediakan peluang range trading
- Implementasi mean reversion strategy
- Adjust position sizing untuk reduced risk

### Kebutuhan 4

**User Story:** Sebagai developer, saya ingin sistem mengextend AbstractAnalysis class dengan implementasi yang clean dan maintainable, sehingga dapat diintegrasikan dengan mudah ke dalam framework yang ada.

#### Kriteria Penerimaan

1. KETIKA class diimplementasikan MAKA sistem HARUS:
- Extend AbstractAnalysis class
- Implement semua abstract methods: getCode(), getName(), analyze()
- Menggunakan MarketDataInterface provider untuk data access
- Return standardized analysis object dengan semua field yang required

2. KETIKA data diakses MAKA sistem HARUS:
- Menggunakan $this->getHistoricalData() untuk market data
- Menggunakan $this->getPrice() untuk current price
- Handle data validation dan minimum requirements (min 50 candles)

3. KETIKA error handling dilakukan MAKA sistem HARUS:
- Graceful degradation jika advanced patterns tidak dapat dideteksi
- Fallback ke basic technical analysis
- Meaningful error messages untuk insufficient data

4. KETIKA output dihasilkan MAKA sistem HARUS return object dengan:
- title, description, signal, confidence, price, entry, stop_loss, take_profit
- risk_reward, indicators, notes, patterns, market_phase, volatility_factor