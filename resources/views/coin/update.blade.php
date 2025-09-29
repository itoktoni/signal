<x-layout>

    <!-- Analysis Form Section -->
    <div class="row">
        <div class="col-12">
            <x-card title="🎯 Konfigurasi Analisis">
                <x-form :model="$model" method="GET" action="{{ route(module('getUpdate'), $model) }}">
                    <x-select col="3" searchable name="coin_code" :value="request('coin_code', $model->coin_code)" :options="$coin" label="Select Coin"
                        required />

                    <x-select col="5" name="analyst_method" :value="request('analyst_method', 'simple_ma')" :options="\App\Analysis\AnalysisServiceFactory::getAvailableMethods()" label="Select Analysis Method" required />
                    <x-select col="2" name="timeframe" :value="request('timeframe', '4h')" :options="[
                        '1h' => '1 Hour',
                        '4h' => '4 Hours',
                        '1d' => '1 Day',
                        '1w' => '1 Week'
                    ]" label="Timeframe" required />
                    <x-input col="2" type="number" name="amount" :value="request('amount', 100)" label="Trade Amount"
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
                        {{ $crypto_analysis['analysis_type'] ?? ($analysis_methods[$analyst_method] ?? 'Basic Analysis') }}
                        <br>
                        @if($current_provider)
                            <small class="text-muted d-block mt-1">
                                <i class="bi bi-cloud-arrow-down"></i>
                                Using {{ $current_provider->getName() }} API
                            </small>
                        @endif
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
                        <span class="signal-badge">
                            {{ $signal }}
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
                                            ${{ number_format($crypto_analysis['price'], 3) }}
                                        </div>
                                        <div class="crypto-price-idr">Rp {{ number_format($crypto_analysis['price'] * 16000, 0) }}</div>
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
                                        <div class="crypto-data-label">Suggested Entry</div>
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

                        <!-- Technical Indicators -->

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

                        <!-- Chart for Analysis -->
                        @if (($analyst_method === 'support_resistance' || $analyst_method === 'simple_ma') && !empty($historical_data))
                            <div class="crypto-card">
                                <div class="crypto-card-header">
                                    <h3 class="crypto-card-title">
                                        <i class="bi bi-graph-up"></i>
                                        Price Chart & Trading Levels
                                        @if($current_provider)
                                            <small class="text-muted d-block mt-1">
                                                <i class="bi bi-cloud-arrow-down"></i>
                                                Powered by {{ $current_provider->getName() }}
                                            </small>
                                        @endif
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


    @if (($analyst_method === 'support_resistance' || $analyst_method === 'simple_ma') && !empty($historical_data))
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

        // Get entry and take profit levels from analysis result
        const entryLevel = @json($crypto_analysis['entry'] ?? null);
        const stopLossLevel = @json($crypto_analysis['stop_loss'] ?? null);
        const takeProfitLevel = @json($crypto_analysis['take_profit'] ?? null);
        const currentPriceLevel = @json($crypto_analysis['price'] ?? null); // Current market price

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

        // Add entry line (BLACK)
        if (entryLevel) {
            const entryLine = {
                price: parseFloat(entryLevel),
                color: '#000000', // Black
                lineWidth: 3,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: 'Entry',
            };
            candlestickSeries.createPriceLine(entryLine);
        }

        // Add stop loss line (RED)
        if (stopLossLevel) {
            const stopLossLine = {
                price: parseFloat(stopLossLevel),
                color: '#FF0000', // Red
                lineWidth: 3,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: 'Stop Loss',
            };
            candlestickSeries.createPriceLine(stopLossLine);
        }

        // Add take profit line (GREEN)
        if (takeProfitLevel) {
            const takeProfitLine = {
                price: parseFloat(takeProfitLevel),
                color: '#00FF00', // Green
                lineWidth: 3,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: 'Take Profit',
            };
            candlestickSeries.createPriceLine(takeProfitLine);
        }

        // Add current price line (BLUE)
        if (currentPriceLevel) {
            const currentPriceLine = {
                price: parseFloat(currentPriceLevel),
                color: '#0000FF', // Blue
                lineWidth: 2,
                lineStyle: 0, // Solid
                axisLabelVisible: true,
                title: 'Current Price',
            };
            candlestickSeries.createPriceLine(currentPriceLine);
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
