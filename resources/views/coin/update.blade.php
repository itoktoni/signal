<x-layout>

    <div class="row">
        <!-- Crypto Analysis Section -->
        <div class="col-12">
            <x-card title="🔍 {{ $crypto_analysis['analysis_type'] ?? (isset($crypto_analysis['title']) ? $crypto_analysis['title'] : (AnalysisType::{strtoupper($analyst_method)}()->getAnalysisDescription() ?? 'Basic Analysis')) }} - {{ $model->coin_code ?? 'Tidak Diketahui' }}">
                @if(isset($crypto_analysis) && !isset($crypto_analysis['error']))
                    @php
                        // Standardized analysis result structure from all analysis services
                        $signal = safeValue($crypto_analysis, 'signal', 'NEUTRAL');
                        $confidence = safeNumericValue($crypto_analysis, 'confidence');
                        $rrRatio = safeNumericValue($crypto_analysis, 'risk_reward');
                        // Updated to match the new standardized format with separate _usd and _idr fields
                        $entryUsd = safeNumericValue($crypto_analysis, 'entry_usd');
                        $entryIdr = safeNumericValue($crypto_analysis, 'entry_idr');
                        $stopLossUsd = safeNumericValue($crypto_analysis, 'stop_loss_usd');
                        $stopLossIdr = safeNumericValue($crypto_analysis, 'stop_loss_idr');
                        $takeProfitUsd = safeNumericValue($crypto_analysis, 'take_profit_usd');
                        $takeProfitIdr = safeNumericValue($crypto_analysis, 'take_profit_idr');
                        $title = safeValue($crypto_analysis, 'title', 'Analysis');
                        $feeUsd = safeNumericValue($crypto_analysis, 'fee_usd');
                        $feeIdr = safeNumericValue($crypto_analysis, 'fee_idr');
                        $potentialProfitUsd = safeNumericValue($crypto_analysis, 'potential_profit_usd');
                        $potentialProfitIdr = safeNumericValue($crypto_analysis, 'potential_profit_idr');
                        $potentialLossUsd = safeNumericValue($crypto_analysis, 'potential_loss_usd');
                        $potentialLossIdr = safeNumericValue($crypto_analysis, 'potential_loss_idr');

                        $direction = $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');
                        $directionIcon = $signal === 'BUY' ? '🚀' : ($signal === 'SELL' ? '⚠️' : '⏳');
                        $signalClass = $signal === 'BUY' ? 'success' : ($signal === 'SELL' ? 'danger' : 'warning');
                    @endphp

                    <!-- Signal Summary Banner -->
                    <div class="signal-banner {{ $signalClass }}">
                        <div class="signal-content">
                            <h2 class="signal-title">
                                {{ $directionIcon }} {{ $direction }} SIGNAL
                            </h2>
                            <div class="signal-details">
                                <span class="signal-confidence">Confidence: {{ $confidence }}%</span>
                                <span class="signal-rr">Risk-Reward: {{ $rrRatio }}:1</span>
                            </div>
                        </div>
                    </div>
                    <div class="analysis-results">
                        <div class="row">
                            <div class="col-6">
                                <div class="info-card">
                                    <h4>📊 Market Data</h4>
                                    <p><strong>Symbol:</strong> {{ $crypto_analysis['symbol'] ?? $model->coin_code ?? 'Unknown' }}</p>
                                    <p><strong>Current Price:</strong> ${{ number_format($entryUsd, 8) }}</p>
                                    <p><strong>Trading Amount:</strong> ${{ number_format($amount, 2) }}</p>
                                    <p><strong>Last Updated:</strong> {{ $crypto_analysis['last_updated'] ?? now()->format('Y-m-d H:i:s') }}</p>
                                    <p><strong>Tipe Analisis:</strong>
                                        <span class="badge {{ $analyst_method === AnalysisType::SNIPER ? 'badge-success' : ($analyst_method === AnalysisType::DYNAMIC_RR ? 'badge-primary' : 'badge-warning') }}">
                                            {{ AnalysisType::{strtoupper($analyst_method)}()->getDisplayName() ?? 'Dasar' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-card">
                                    <h4>🎯 Trading Signal</h4>
                                    @php
                                        $signalClass = $signal === 'BUY' ? 'success' : ($signal === 'SELL' ? 'danger' : 'warning');
                                        $direction = $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');
                                        $directionIcon = $signal === 'BUY' ? '🚀' : ($signal === 'SELL' ? '⚠️' : '⏳');
                                    @endphp
                                    <p><strong>Direction:</strong>
                                        <span class="badge badge-{{ $signalClass }} signal-large">
                                            {{ $directionIcon }} {{ $direction }}
                                        </span>
                                    </p>
                                    <p><strong>Signal:</strong>
                                        <span class="badge badge-{{ $signalClass }}">
                                            {{ $signal }}
                                        </span>
                                    </p>
                                    <p><strong>Confidence:</strong>
                                        <span class="badge {{ $confidence >= 70 ? 'badge-success' : ($confidence >= 50 ? 'badge-warning' : 'badge-danger') }}">
                                            {{ $confidence }}%
                                        </span>
                                    </p>
                                    <p><strong>Risk-Reward Ratio:</strong>
                                        <span class="badge {{ $rrRatio >= 2 ? 'badge-success' : 'badge-warning' }}">
                                            {{ $rrRatio }}:1
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📈 Trading Levels</h4>
                                    <div class="row">
                                        <div class="col-4">
                                            <p><strong>Entry Point:</strong></p>
                                            <div class="price-dual">
                                                <span class="price-usd">${{ number_format($entryUsd, 3) }}</span>
                                                <span class="price-rupiah">Rp {{ number_format($entryIdr, 0) }}</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <p><strong>Stop Loss:</strong></p>
                                            <div class="price-dual">
                                                <span class="price-usd text-danger">${{ number_format($stopLossUsd, 3) }}</span>
                                                <span class="price-rupiah text-danger">Rp {{ number_format($stopLossIdr, 0) }}</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <p><strong>Take Profit:</strong></p>
                                            <div class="price-dual">
                                                <span class="price-usd text-success">${{ number_format($takeProfitUsd, 3) }}</span>
                                                <span class="price-rupiah text-success">Rp {{ number_format($takeProfitIdr, 0) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Information Section -->
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>💰 Informasi Biaya</h4>
                                    <div class="row">
                                        <div class="col-6">
                                            <p><strong>Total Fee (USD):</strong></p>
                                            <div class="fee-info">
                                                <span class="price-value">${{ number_format($feeUsd, 4) }}</span>
                                                @if($amount > 0)
                                                <span class="percentage">
                                                    ({{ number_format(($feeUsd / $amount) * 100, 2) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <p><strong>Total Fee (Rupiah):</strong></p>
                                            <div class="fee-info">
                                                <span class="price-value">Rp {{ number_format($feeIdr, 0) }}</span>
                                                @if($amount > 0)
                                                <span class="percentage">
                                                    ({{ number_format(($feeIdr / ($amount * 16000)) * 100, 2) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        @if(isset($crypto_analysis['fee']) && is_array($crypto_analysis['fee']) && isset($crypto_analysis['fee']['description']))
                                        <div class="col-12 mt-3">
                                            <p><strong>Deskripsi Biaya:</strong> {{ $crypto_analysis['fee']['description'] }}</p>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Profit/Loss Information Section -->
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📊 Profit & Loss Potential</h4>
                                    <div class="row">
                                        <div class="col-6">
                                            <p><strong>Potential Profit:</strong></p>
                                            <div class="profit-loss-info">
                                                <span class="price-value text-success">${{ number_format($potentialProfitUsd, 2) }}</span>
                                                <span class="price-rupiah text-success">Rp {{ number_format($potentialProfitIdr, 0) }}</span>
                                                @if($amount > 0)
                                                <span class="percentage text-success">
                                                    ({{ number_format(($potentialProfitUsd / $amount) * 100, 1) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <p><strong>Potential Loss:</strong></p>
                                            <div class="profit-loss-info">
                                                @php
                                                    // Loss should typically be negative, but we'll handle both cases
                                                    $displayLossUsd = abs($potentialLossUsd);
                                                    $displayLossIdr = abs($potentialLossIdr);
                                                @endphp
                                                <span class="price-value text-danger">${{ number_format($displayLossUsd, 2) }}</span>
                                                <span class="price-rupiah text-danger">Rp {{ number_format($displayLossIdr, 0) }}</span>
                                                @if($amount > 0)
                                                <span class="percentage text-danger">
                                                    ({{ number_format(($displayLossUsd / $amount) * 100, 1) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>





                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Analysis Error:</strong> {{ $crypto_analysis['error'] ?? 'Unable to perform analysis' }}
                    </div>
                @endif

            </x-card>
        </div>

        <!-- Analysis Form Section -->
        <div class="col-12">
            <x-card title="🎯 Konfigurasi Analisis">
                <x-form method="GET" action="{{ route(module('getUpdate'), $model) }}">
                    <x-select searchable name="coin_code" :value="$model->coin_code" :options="$coin" label="Select Coin" required/>
                    <x-select name="analyst" :options="$analyst_methods" label="Metode Analisis" :value="request('analyst', 'sniper')" required/>
                    <x-input type="number" name="amount" :value="request('amount', 100)" label="Trading Amount (USD)" step="0.01" min="1" placeholder="Enter amount to trade"/>

                    <x-footer>
                        <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                        <x-button type="submit" class="primary">🔍 Analyze</x-button>
                    </x-footer>

                </x-form>
            </x-card>
        </div>
    </div>

    <style>
    .analysis-results {
        margin: 0px 2rem;

    }
    .info-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .info-card h4 {
        margin-top: 0;
        color: #495057;
        border-bottom: 2px solid #007bff;
        padding-bottom: 8px;
    }
    .price-value {
        font-size: 16px;
        font-weight: bold;
        display: block;
        margin-top: 5px;
    }
    .price-dual {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .price-usd {
        font-size: 14px;
        font-weight: bold;
    }
    .price-rupiah {
        font-size: 12px;
        opacity: 0.8;
    }
    .profit-loss-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .percentage {
        font-size: 12px;
        font-weight: bold;
        margin-top: 2px;
    }
    .fee-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
    }
    .badge-success {
        background-color: #28a745;
        color: white;
    }
    .badge-danger {
        background-color: #dc3545;
        color: white;
    }
    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }
    .badge-info {
        background-color: #17a2b8;
        color: white;
    }
    .badge-primary {
        background-color: #007bff;
        color: white;
    }
    .alert {
        padding: 15px;
        border: 1px solid transparent;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    .alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeaa7;
    }
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }
    .alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    /* Enhanced Signal Display */
    .signal-large {
        font-size: 16px !important;
        padding: 8px 16px !important;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .badge-success.signal-large {
        background-color: #28a745 !important;
        color: white !important;
        animation: pulse-green 2s infinite;
    }

    .badge-danger.signal-large {
        background-color: #dc3545 !important;
        color: white !important;
        animation: pulse-red 2s infinite;
    }

    .badge-warning.signal-large {
        background-color: #ffc107 !important;
        color: #212529 !important;
        animation: pulse-yellow 2s infinite;
    }

    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
    }

    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }

    @keyframes pulse-yellow {
        0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
    }

    /* Signal Banner Styles */
    .signal-banner {
        border-radius: 12px;
        padding: 20px;
        margin: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .signal-banner::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
        animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }

    .signal-banner.success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .signal-banner.danger {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
        color: white;
    }

    .signal-banner.warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: #212529;
    }

    /* Analysis Description Styles */
    .info-card ul {
        padding-left: 20px;
    }

    .info-card li {
        margin-bottom: 8px;
    }

    .alert h5 {
        margin-top: 0;
    }

    .alert ul {
        padding-left: 20px;
        margin-bottom: 0;
    }
    </style>
</x-layout>