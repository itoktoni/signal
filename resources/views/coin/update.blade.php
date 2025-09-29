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
                        {{ $result->title ?? '' }}
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
                    </p>
                </div>
                <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                    <span class="signal-badge">
                        {{ $result->signal ?? '-' }}
                    </span>
            </div>
            </div>
        </div>

        <!-- Crypto Analysis Content -->
        <div class="row">
            <div class="col-12">
                <x-card title="📊 Crypto Analysis Results">

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
                                    <div class="crypto-data-value" style="font-size: 3rem">{{ $result->confidence ?? 0 }}%</div>
                                </div>

                                <div class="crypto-data-item">
                                    <div class="crypto-data-label">Risk:Reward Ratio</div>
                                    <div class="crypto-data-value" style="font-size: 3rem">{{ $result->risk_reward ?? '' }}</div>
                                </div>

                                <div class="crypto-data-item">
                                    <div class="crypto-data-label">Current Price</div>
                                    <div class="crypto-data-value crypto-price-usd">
                                        ${{ numberFormat($result->price, 3) }}
                                    </div>
                                    <div class="crypto-price-idr">Rp {{ numberFormat(usdToIdr($result->price), 0) }}</div>
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
                                        ${{ numberFormat($result->entry, 3) }}</div>
                                    <div class="crypto-price-idr">Rp {{ numberFormat(usdToIdr($result->entry), 0) }}</div>
                                </div>
                                <div class="crypto-data-item">
                                    <div class="crypto-data-label">Stop Loss</div>
                                    <div class="crypto-data-value text-error">
                                        ${{ numberFormat($result->stop_loss, 3) }}</div>
                                    <div class="crypto-price-idr text-error">Rp
                                        {{ numberFormat(usdToIdr($result->stop_loss), 0) }}</div>
                                </div>
                                <div class="crypto-data-item">
                                    <div class="crypto-data-label">Take Profit</div>
                                    <div class="crypto-data-value text-success">
                                        ${{ numberFormat($result->take_profit, 3) }}</div>
                                    <div class="crypto-price-idr text-success">Rp
                                        {{ numberFormat(usdToIdr($result->take_profit), 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Technical Indicators -->

                     @if (!empty($result->description))
                         <div class="crypto-card">
                             <div class="crypto-card-header">
                                 <h3 class="crypto-card-title">
                                     <i class="bi bi-info-circle"></i> Analysis Description
                                 </h3>
                             </div>
                             <div class="crypto-card-body">
                                 @if(is_array($result->description))
                                     <!-- Handle key-value array format -->
                                     <div class="analysis-description">
                                         <div class="table-responsive">
                                             <table class="table table-hover">
                                                 <thead class="table-light">
                                                     <tr>
                                                         <th>Aspect</th>
                                                         <th>Description</th>
                                                     </tr>
                                                 </thead>
                                                 <tbody>
                                                     @foreach ($result->description as $key => $value)
                                                         <tr>
                                                             <td>
                                                                 <strong>{{ ucwords(str_replace('_', ' ', $key)) }}</strong>
                                                             </td>
                                                             <td>
                                                                 <span class="crypto-indicator-value">
                                                                     {{ $value }}
                                                                 </span>
                                                             </td>
                                                         </tr>
                                                     @endforeach
                                                 </tbody>
                                             </table>
                                         </div>
                                     </div>
                                 @else
                                     <!-- Fallback for string format -->
                                     <p>{{ $result->description }}</p>
                                 @endif
                             </div>
                         </div>
                     @endif

                    @if (!empty($result->indicators))
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
                                            @foreach ($result->indicators as $key => $value)
                                                <tr>
                                                    <td>
                                                        <strong>{{ ucwords(str_replace('_', ' ', $key)) }}</strong>
                                                    </td>
                                                    <td>
                                                        <span class="crypto-indicator-value">
                                                            @if (is_numeric($value))
                                                                {{ numberFormat($value, 4) }}
                                                            @else
                                                                {{ $value }}
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

                    @if (!empty($result->notes))
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-sticky"></i> Analysis Notes
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                @if(is_array($result->notes))
                                    <!-- Handle key-value array format -->
                                    <div class="analysis-notes">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Aspect</th>
                                                        <th>Details</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($result->notes as $key => $value)
                                                        <tr>
                                                            <td>
                                                                <strong>{{ ucwords(str_replace('_', ' ', $key)) }}</strong>
                                                            </td>
                                                            <td>
                                                                <span class="crypto-indicator-value">
                                                                    {{ $value }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @else
                                    <!-- Fallback for string format -->
                                    <div class="alert alert-info">
                                        <p>{{ $result->notes }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Chart for Analysis -->
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

                    <!-- Analysis Notes (Duplicate section removed - already handled above) -->

                </x-card>
            </div>
        </div>

    </div>


    @if (!empty($historical_data))
    <script type="module">
        import { createChart, ColorType } from 'https://unpkg.com/lightweight-charts@4.1.1/dist/lightweight-charts.standalone.production.mjs';

        // Historical data from PHP
        const historicalData = @json($historical_data);

        // Convert historical data to chart format
        const candlestickData = historicalData.map(item => ({
            time: Math.floor(item[0] / 1000), // Convert milliseconds to seconds
            open: parseFloat(item[1]),
            high: parseFloat(item[2]),
            low: parseFloat(item[3]),
            close: parseFloat(item[4])
        }));

        // Get entry and take profit levels from analysis result
        const entryLevel = @json($result->entry ?? null);
        const stopLossLevel = @json($result->stop_loss ?? null);
        const takeProfitLevel = @json($result->take_profit ?? null);
        const currentPriceLevel = @json($result->price ?? null); // Current market price

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
