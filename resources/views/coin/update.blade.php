<x-layout>

    <!-- Analysis Form Section -->
    <div class="row">
        <div class="col-12">
            <x-card title="🎯 Konfigurasi Analisis">
                <x-form :model="$model" method="GET" action="{{ route(module('getUpdate'), $model) }}">
                    <x-select col="4" searchable name="coin_code" :value="request('coin_code', $model->coin_code)" :options="$coin" label="Select Coin"
                        required />

                    <x-select name="analyst_method" :value="request('analyst_method', 'ma_rsi_volume_atr_macd')" :options="\App\Analysis\AnalysisServiceFactory::getAvailableMethods()" label="Select Analysis Method" required />
                    <x-input col="2" type="number" name="amount" :value="request('amount', 100)" label="Trading Amount (USD)"
                        step="0.01" min="1" placeholder="Enter amount to trade" />

                    <x-footer>
                        <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                        <x-button type="submit" class="primary">🔍 Analyze</x-button>
                    </x-footer>
                </x-form>
            </x-card>
        </div>
    </div>

    <div class="crypto-dashboard">
        <!-- Crypto Analysis Header -->
        <div class="crypto-header">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <h1 class="mb-2">
                        <i class="bi bi-graph-up"></i>
                        @php
                            $analysisMethods = \App\Analysis\AnalysisServiceFactory::getAvailableMethods();
                        @endphp
                        {{ $crypto_analysis['analysis_type'] ?? ($analysisMethods[$analyst_method] ?? 'Basic Analysis') }}
                    </h1>
                    <p class="mb-0">
                        <i class="bi bi-currency-bitcoin"></i>
                        {{ $model->coin_code ?? 'Tidak Diketahui' }}
                        <span class="mx-2">•</span>
                        Last Updated: {{ $crypto_analysis['last_updated'] ?? now()->format('Y-m-d H:i:s') }}
                    </p>
                </div>
                <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                    @if (isset($crypto_analysis) && !isset($crypto_analysis['error']))
                        @php
                            $signal = $crypto_analysis['signal'] ?? 'NEUTRAL';
                            $signalClass = $signal === 'BUY' ? 'buy' : ($signal === 'SELL' ? 'sell' : 'neutral');
                            $signalText =
                                $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');
                        @endphp
                        <span class="signal-badge {{ $signalClass }}">
                            {{ $signalText }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Crypto Analysis Content -->
        <div class="row">
            <div class="col-12">
                <x-card title="📊 Crypto Analysis Results">
                    @if (isset($crypto_analysis) && !isset($crypto_analysis['error']))
                        @php
                            // Standardized analysis result structure from all analysis services
                            $signal = $crypto_analysis['signal'] ?? 'NEUTRAL';
                            $confidence = $crypto_analysis['confidence'] ?? 0;
                            $rrRatio = $crypto_analysis['risk_reward'] ?? 0;
                            // Updated to match the new standardized format with unified fields
                            $entry = $crypto_analysis['entry'] ?? 0;
                            $stopLoss = $crypto_analysis['stop_loss'] ?? 0;
                            $takeProfit = $crypto_analysis['take_profit'] ?? 0;
                            $fee = $crypto_analysis['fee'] ?? 0;
                            $potentialProfit = $crypto_analysis['potential_profit'] ?? 0;
                            $potentialLoss = $crypto_analysis['potential_loss'] ?? 0;
                            $title = $crypto_analysis['title'] ?? 'Analysis';

                            // Calculate IDR values
                            $exchangeRate = 16000; // Default exchange rate
                            $entryUsd = $entry;
                            $entryIdr = $entry * $exchangeRate;
                            $stopLossUsd = $stopLoss;
                            $stopLossIdr = $stopLoss * $exchangeRate;
                            $takeProfitUsd = $takeProfit;
                            $takeProfitIdr = $takeProfit * $exchangeRate;
                            $feeUsd = $fee;
                            $feeIdr = $fee * $exchangeRate;
                            $potentialProfitUsd = $potentialProfit;
                            $potentialProfitIdr = $potentialProfit * $exchangeRate;
                            $potentialLossUsd = abs($potentialLoss);
                            $potentialLossIdr = abs($potentialLoss) * $exchangeRate;

                            $signalClass = $signal === 'BUY' ? 'buy' : ($signal === 'SELL' ? 'sell' : 'neutral');
                            $signalText = $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');
                        @endphp

                        <!-- Key Metrics Overview -->
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-speedometer2"></i> Key Metrics Overview
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                <div class="crypto-grid crypto-grid-cols-3">
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Confidence Level</div>
                                        <div class="crypto-data-value" style="font-size: 3rem">{{ $confidence }}%</div>
                                    </div>

                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Risk:Reward Ratio</div>
                                        <div class="crypto-data-value" style="font-size: 3rem">{{ $rrRatio }}</div>
                                    </div>

                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Current Price</div>
                                        <div class="crypto-data-value crypto-price-usd">
                                            ${{ number_format($entryUsd, 3) }}
                                        </div>
                                        <div class="crypto-price-idr">Rp {{ number_format($entryIdr, 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trading Levels -->
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-bar-chart-steps"></i> Trading Levels
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                <div class="crypto-data-grid">
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Entry Point</div>
                                        <div class="crypto-data-value crypto-price-usd">
                                            ${{ number_format($entryUsd, 3) }}</div>
                                        <div class="crypto-price-idr">Rp {{ number_format($entryIdr, 0) }}</div>
                                    </div>
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Stop Loss</div>
                                        <div class="crypto-data-value text-error">
                                            ${{ number_format($stopLossUsd, 3) }}</div>
                                        <div class="crypto-price-idr text-error">Rp
                                            {{ number_format($stopLossIdr, 0) }}</div>
                                    </div>
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Take Profit</div>
                                        <div class="crypto-data-value text-success">
                                            ${{ number_format($takeProfitUsd, 3) }}</div>
                                        <div class="crypto-price-idr text-success">Rp
                                            {{ number_format($takeProfitIdr, 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Information
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-currency-dollar"></i> Fee Information
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                <div class="crypto-data-grid">
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Total Fee (USD)</div>
                                        <div class="crypto-data-value">${{ number_format($feeUsd, 4) }}</div>
                                        @if ($amount > 0)
                                            <div class="crypto-price-idr">
                                                ({{ number_format(($feeUsd / $amount) * 100, 2) }}% of
                                                trade)</div>
                                        @endif
                                    </div>
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Total Fee (IDR)</div>
                                        <div class="crypto-data-value">Rp {{ number_format($feeIdr, 0) }}</div>
                                        @if ($amount > 0)
                                            <div class="crypto-price-idr">
                                                ({{ number_format(($feeIdr / ($amount * 16000)) * 100, 2) }}% of trade)
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @if (isset($crypto_analysis['fee']) &&
                                        is_array($crypto_analysis['fee']) &&
                                        isset($crypto_analysis['fee']['description']))
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <small class="text-muted">{{ $crypto_analysis['fee']['description'] }}</small>
                                    </div>
                                @endif
                            </div>
                        </div>

                        -->

                        <!-- Profit/Loss Potential
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-graph-up-arrow"></i> Profit & Loss Potential
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                <div class="crypto-data-grid">
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Potential Profit</div>
                                        <div class="crypto-data-value crypto-profit-positive">
                                            ${{ number_format($potentialProfitUsd, 2) }}</div>
                                        <div class="crypto-price-idr crypto-profit-positive">Rp
                                            {{ number_format($potentialProfitIdr, 0) }}</div>
                                        @if ($amount > 0)
                                            <div class="crypto-price-idr">
                                                ({{ number_format(($potentialProfitUsd / $amount) * 100, 1) }}%)</div>
                                        @endif
                                    </div>
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Potential Loss</div>
                                        <div class="crypto-data-value crypto-profit-negative">
                                            ${{ number_format($potentialLossUsd, 2) }}</div>
                                        <div class="crypto-price-idr crypto-profit-negative">Rp
                                            {{ number_format($potentialLossIdr, 0) }}</div>
                                        @if ($amount > 0)
                                            <div class="crypto-price-idr">
                                                ({{ number_format((abs($potentialLossUsd) / $amount) * 100, 1) }}%)</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        -->

                        <!-- Technical Indicators -->
                        @php
                            $hasIndicators = false;
                            if (isset($crypto_analysis['indicators']) && is_array($crypto_analysis['indicators'])) {
                                $indicators = $crypto_analysis['indicators'];
                                $hasIndicators = !empty($indicators);
                            }
                        @endphp

                        <!-- Analysis Description -->
                        @if (isset($crypto_analysis['description']) && !empty($crypto_analysis['description']))
                            <div class="crypto-card">
                                <div class="crypto-card-header">
                                    <h3 class="crypto-card-title">
                                        <i class="bi bi-card-text"></i> Analysis Description
                                    </h3>
                                </div>
                                <div class="crypto-card-body">
                                    <div class="crypto-alert">
                                        <div class="d-flex align-items-start">
                                            <p class="mb-0" style="font-size: 1.5rem; line-height: 1.6;">
                                                {{ $crypto_analysis['description'] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                         @if ($hasIndicators)
                            <div class="crypto-card">
                                <div class="crypto-card-header">
                                    <h3 class="crypto-card-title">
                                        <i class="bi bi-activity"></i> Technical Indicators
                                    </h3>
                                </div>
                                <div class="crypto-card-body">
                                    <!-- Indicators Table -->
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Indicator</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($indicators as $key => $value)
                                                    <tr>
                                                        <td>
                                                            <strong>{{ ucwords(str_replace('_', ' ', $key)) }}</strong>
                                                        </td>
                                                        <td>
                                                            <span class="crypto-indicator-value">
                                                                @if (is_numeric($value))
                                                                    @if (str_contains(strtolower($key), 'price') || str_contains(strtolower($key), 'usd'))
                                                                        ${{ number_format($value, 3) }}
                                                                    @elseif(str_contains(strtolower($key), 'percentage') || str_contains(strtolower($key), 'percent'))
                                                                        {{ number_format($value, 2) }}%
                                                                    @else
                                                                        {{ number_format($value, 4) }}
                                                                    @endif
                                                                @else
                                                                    @if (is_array($value))
                                                                        {{ json_encode($value) }}
                                                                    @else
                                                                        {{ $value }}
                                                                    @endif
                                                                @endif
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Chart for Support Resistance Analysis -->
                        @if ($analyst_method === 'support_resistance' && !empty($historical_data))
                            <div class="crypto-card">
                                <div class="crypto-card-header">
                                    <h3 class="crypto-card-title">
                                        <i class="bi bi-graph-up"></i> Price Chart with Support & Resistance
                                    </h3>
                                </div>
                                <div class="crypto-card-body">
                                    <div id="tradingview-chart" style="width: 100%; height: 400px;"></div>
                                </div>
                            </div>
                        @endif

                        <!-- Analysis Notes -->
                        @if (isset($crypto_analysis['notes']) && !empty($crypto_analysis['notes']))
                            <div class="crypto-card">
                                <div class="crypto-card-header">
                                    <h3 class="crypto-card-title">
                                        <i class="bi bi-sticky"></i> Analysis Notes
                                    </h3>
                                </div>
                                <div class="crypto-card-body">
                                    <div class="crypto-alert">
                                        <div class="d-flex align-items-start">
                                            <div>
                                               {{ $crypto_analysis['notes'] }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif


                    @else
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Analysis Error:</strong>
                            {{ $crypto_analysis['error'] ?? 'Unable to perform analysis' }}
                        </div>
                    @endif
                </x-card>
            </div>
        </div>

    </div>

     <style>
        :root {
            --crypto-primary: #2563eb;
            --crypto-secondary: #7c3aed;
            --crypto-success: #00b963;
            --crypto-danger: #ef4444;
            --crypto-warning: #f59e0b;
            --crypto-dark: #1e293b;
            --crypto-light: #f8fafc;
            --crypto-card-bg: #ffffff;
            --crypto-border: #e2e8f0;
            --crypto-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .crypto-header {
            background: linear-gradient(135deg, var(--crypto-primary) 0%, var(--crypto-secondary) 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--crypto-shadow);
        }

        .description {
            font-size: 2rem;
        }

        .crypto-card {
            background: var(--crypto-card-bg);
            border-radius: 16px;
            box-shadow: var(--crypto-shadow);
            border: 1px solid var(--crypto-border);
            margin: 2rem;
        }

        .crypto-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .crypto-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--crypto-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .crypto-card-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--crypto-dark);
            margin: 0;
        }

        .crypto-card-body {
            padding: 24px;
        }

        .signal-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1.7rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .signal-badge.buy {
            background-color: #dcfce7;
            color: var(--crypto-success);
        }

        .signal-badge.sell {
            background-color: #fee2e2;
            color: var(--crypto-danger);
        }

        .signal-badge.neutral {
            background-color: #fef3c7;
            color: var(--crypto-warning);
        }

        .crypto-metric {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .crypto-metric-label {
            font-size: 1.7rem;
            color: #64748b;
            font-weight: 500;
        }

        .crypto-metric-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--crypto-dark);
        }

        .crypto-price {
            font-size: 1.7rem;
            font-weight: 700;
        }

        .crypto-grid {
            display: grid;
            gap: 24px;
        }

        .crypto-grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .crypto-grid-cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .crypto-section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--crypto-dark);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--crypto-primary);
            display: inline-block;
        }

        .crypto-data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .crypto-data-item {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .crypto-data-label {
            font-size: 1.5rem;
            color: #64748b;
            margin-bottom: 4px;
        }

        .crypto-data-value {
            font-size: 1.525rem;
            font-weight: 600;
            color: var(--crypto-dark);
        }

        .crypto-price-usd {
            color: var(--crypto-dark);
            font-weight: 700;
        }

        .crypto-price-idr {
            color: #64748b;
            font-size: 1.5rem;
        }

        .crypto-indicator-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
        }

        .crypto-indicator-card {
            background: #f8fafc;
            border: 1px solid var(--crypto-border);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .crypto-indicator-label {
            font-size: 1.5rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .crypto-indicator-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--crypto-dark);
        }

        .crypto-profit-positive {
            color: var(--crypto-success);
        }

        .crypto-profit-negative {
            color: var(--crypto-danger);
        }


        .crypto-alert {
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .crypto-alert-success {
            background-color: #dcfce7;
            border-left: 4px solid var(--crypto-success);
        }

        .crypto-alert-warning {
            background-color: #fef3c7;
            border-left: 4px solid var(--crypto-warning);
        }

        .crypto-alert-danger {
            background-color: #fee2e2;
            border-left: 4px solid var(--crypto-danger);
        }

        .crypto-alert-info {
            background-color: #dbeafe;
            border-left: 4px solid var(--crypto-primary);
        }

        /* Table Styling */
        .table {
            margin-bottom: 0;
        }

        .table-hover tbody tr:hover {
            background-color: #f8fafc;
        }

        .table thead th {
            border-top: none;
            font-weight: 600;
            color: var(--crypto-dark);
            background-color: #f8fafc;
            padding: 1rem;
        }

        .table td {
            vertical-align: middle;
            border-color: var(--crypto-border);
            padding: 1rem;
        }

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        /* List Group Styling */
        .list-group-item {
            background: transparent;
            border: none;
            padding-left: 0;
        }

        .list-group-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        /* Badge Enhancements */
        .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        /* Status Indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-dot.success {
            background-color: var(--crypto-success);
        }

        .status-dot.danger {
            background-color: var(--crypto-danger);
        }

        .status-dot.warning {
            background-color: var(--crypto-warning);
        }

        .status-dot.neutral {
            background-color: #6b7280;
        }

        .card{
            padding-bottom: 1rem !important;
        }


        @media (max-width: 768px) {

            .crypto-grid-cols-2,
            .crypto-grid-cols-3 {
                grid-template-columns: 1fr;
            }

            .crypto-header {
                padding: 16px;
            }

            .crypto-card-body {
                padding: 16px;
            }
        }
    </style>

    @if ($analyst_method === 'support_resistance' && !empty($historical_data))
    <script type="module">
        import { createChart, ColorType } from 'https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.mjs';

        // Historical data from PHP
        const historicalData = @json($historical_data);
        const indicators = @json($crypto_analysis['indicators'] ?? []);

        // Convert historical data to chart format
        const candlestickData = historicalData.map(item => ({
            time: Math.floor(item[0] / 1000), // Convert milliseconds to seconds
            open: parseFloat(item[1]),
            high: parseFloat(item[2]),
            low: parseFloat(item[3]),
            close: parseFloat(item[4])
        }));

        // Get support and resistance levels
        const supportLevel = indicators.Support ? parseFloat(indicators.Support) : null;
        const resistanceLevel = indicators.Resistance ? parseFloat(indicators.Resistance) : null;

        // Get entry and take profit levels from analysis result
        const entryLevel = @json($crypto_analysis['entry'] ?? null);
        const takeProfitLevel = @json($crypto_analysis['take_profit'] ?? null);

        // Create chart
        const chart = createChart(document.getElementById('tradingview-chart'), {
            layout: {
                background: { type: ColorType.Solid, color: 'white' },
                textColor: '#333',
            },
            width: document.getElementById('tradingview-chart').clientWidth,
            height: 400,
            grid: {
                vertLines: { color: '#e1e1e1' },
                horzLines: { color: '#e1e1e1' },
            },
            crosshair: {
                mode: 1,
            },
            rightPriceScale: {
                borderColor: '#cccccc',
            },
            timeScale: {
                borderColor: '#cccccc',
                timeVisible: true,
                secondsVisible: false,
            },
        });

        // Add candlestick series
        const candlestickSeries = chart.addCandlestickSeries({
            upColor: '#00C853',
            downColor: '#FF1744',
            borderVisible: false,
            wickUpColor: '#00C853',
            wickDownColor: '#FF1744',
        });

        candlestickSeries.setData(candlestickData);

        // Add support line
        if (supportLevel) {
            const supportLine = {
                price: supportLevel,
                color: '#2196F3',
                lineWidth: 2,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: 'Support',
            };
            candlestickSeries.createPriceLine(supportLine);
        }

        // Add resistance line
        if (resistanceLevel) {
            const resistanceLine = {
                price: resistanceLevel,
                color: '#FF9800',
                lineWidth: 2,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: 'Resistance',
            };
            candlestickSeries.createPriceLine(resistanceLine);
        }

        // Add entry line
        if (entryLevel) {
            const entryLine = {
                price: parseFloat(entryLevel),
                color: '#4CAF50',
                lineWidth: 2,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: 'Entry',
            };
            candlestickSeries.createPriceLine(entryLine);
        }

        // Add take profit line
        if (takeProfitLevel) {
            const takeProfitLine = {
                price: parseFloat(takeProfitLevel),
                color: '#2196F3',
                lineWidth: 2,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: 'Take Profit',
            };
            candlestickSeries.createPriceLine(takeProfitLine);
        }

        // Fit content
        chart.timeScale().fitContent();

        // Handle resize
        window.addEventListener('resize', () => {
            chart.applyOptions({
                width: document.getElementById('tradingview-chart').clientWidth
            });
        });
    </script>
    @endif

</x-layout>
