<x-layout>

    <div class="row">
        <!-- Crypto Analysis Section -->
        <div class="col-12">
            <x-card title="🔍 {{ $crypto_analysis['analysis_type'] ?? (isset($crypto_analysis['title']) ? $crypto_analysis['title'] : (AnalysisType::{strtoupper($analyst_method)}()->getAnalysisDescription() ?? 'Basic Analysis')) }} - {{ $model->coin_code ?? 'Tidak Diketahui' }}">
                @if(isset($crypto_analysis) && !isset($crypto_analysis['error']))
                    @php
                        // Handle both old and new analysis result structures
                        if (isset($crypto_analysis['analysis'])) {
                            // Old structure (backward compatibility)
                            $signal = $crypto_analysis['analysis']['signal'] ?? 'NEUTRAL';
                            $confidence = $crypto_analysis['analysis']['confidence'] ?? 0;
                            $rrRatio = $crypto_analysis['analysis']['rr_ratio'] ?? 0;
                            $entry = $crypto_analysis['analysis']['entry'] ?? 0;
                            $stopLoss = $crypto_analysis['analysis']['stop_loss'] ?? 0;
                            $takeProfit = $crypto_analysis['analysis']['take_profit'] ?? 0;
                            $title = $crypto_analysis['analysis_type'] ?? 'Analysis';
                        } else {
                            // New structure from analysis services
                            $signal = $crypto_analysis['signal'] ?? 'NEUTRAL';
                            $confidence = $crypto_analysis['confidence'] ?? 0;
                            $rrRatio = $crypto_analysis['risk_reward'] ?? 0;
                            $entry = $crypto_analysis['entry'] ?? ['usd' => 0, 'rupiah' => 0];
                            $stopLoss = $crypto_analysis['stop_loss'] ?? ['usd' => 0, 'rupiah' => 0];
                            $takeProfit = $crypto_analysis['take_profit'] ?? ['usd' => 0, 'rupiah' => 0];
                            $title = $crypto_analysis['title'] ?? 'Analysis';
                        }

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
                                    <p><strong>Current Price:</strong> ${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 8) }}</p>
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
                                                <span class="price-usd">${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}</span>
                                                @if(is_array($entry) && isset($entry['rupiah']))
                                                <span class="price-rupiah">Rp {{ number_format($entry['rupiah'], 0) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <p><strong>Stop Loss:</strong></p>
                                            <div class="price-dual">
                                                <span class="price-usd text-danger">${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }}</span>
                                                @if(is_array($stopLoss) && isset($stopLoss['rupiah']))
                                                <span class="price-rupiah text-danger">Rp {{ number_format($stopLoss['rupiah'], 0) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <p><strong>Take Profit:</strong></p>
                                            <div class="price-dual">
                                                <span class="price-usd text-success">${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }}</span>
                                                @if(is_array($takeProfit) && isset($takeProfit['rupiah']))
                                                <span class="price-rupiah text-success">Rp {{ number_format($takeProfit['rupiah'], 0) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Information Section -->
                        @php
                            $hasFee = false;
                            if (isset($crypto_analysis['fee']) && is_array($crypto_analysis['fee']) &&
                                (isset($crypto_analysis['fee']['usd']) || isset($crypto_analysis['fee']['total']))) {
                                $hasFee = true;
                            }
                        @endphp

                        @if($hasFee)
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>💰 Informasi Biaya</h4>
                                    <div class="row">
                                        @if(isset($crypto_analysis['fee']['usd']) && $crypto_analysis['fee']['usd'] > 0)
                                        <div class="col-6">
                                            <p><strong>Total Fee (USD):</strong></p>
                                            <div class="fee-info">
                                                <span class="price-value">${{ number_format($crypto_analysis['fee']['usd'], 4) }}</span>
                                                @if($amount > 0)
                                                <span class="percentage">
                                                    ({{ number_format(($crypto_analysis['fee']['usd'] / $amount) * 100, 2) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                        @if(isset($crypto_analysis['fee']['rupiah']) && $crypto_analysis['fee']['rupiah'] > 0)
                                        <div class="col-6">
                                            <p><strong>Total Fee (Rupiah):</strong></p>
                                            <div class="fee-info">
                                                <span class="price-value">Rp {{ number_format($crypto_analysis['fee']['rupiah'], 0) }}</span>
                                                @if($amount > 0)
                                                <span class="percentage">
                                                    ({{ number_format(($crypto_analysis['fee']['rupiah'] / ($amount * 16000)) * 100, 2) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                        @if(isset($crypto_analysis['fee']['breakdown']['tax_and_third_party']))
                                        <div class="col-12 mt-3">
                                            <h6>Rincian Biaya:</h6>
                                            <div class="row">
                                                <div class="col-6"><small>Pajak & Biaya Pihak Ketiga (0.0332%): ${{ number_format($crypto_analysis['fee']['breakdown']['tax_and_third_party'], 4) }}</small></div>
                                                <div class="col-6"><small>Biaya Trading (0.1%): ${{ number_format($crypto_analysis['fee']['breakdown']['trading_fee'], 4) }}</small></div>
                                            </div>
                                            @if(isset($crypto_analysis['fee']['description']))
                                            <div class="mt-2">
                                                <small class="text-muted">{{ $crypto_analysis['fee']['description'] }}</small>
                                            </div>
                                            @endif
                                            <div class="mt-2">
                                                <small class="text-info">
                                                    <strong>Contoh:</strong> Kamu melakukan transaksi pembelian BTC senilai ${{ number_format($amount, 0) }}
                                                    menggunakan limit order. Tambahan pajak dan biaya pihak ketiga sebesar 0,0332% juga dikenakan
                                                    dan dihitung dari total jumlah transaksi ( ${{ number_format($amount, 0) }} x 0,0332% = ${{ number_format($amount * 0.000332, 2) }} ).
                                                    Dengan begitu, kamu akan menerima BTC senilai ${{ number_format($amount - ($amount * 0.001332), 2) }},
                                                    dengan total pembayaran sebesar ${{ number_format($amount, 2) }} (termasuk pajak).
                                                </small>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Profit/Loss Information Section -->
                        @php
                            $hasProfitLoss = false;
                            if (isset($crypto_analysis['potential_profit']) || isset($crypto_analysis['potential_loss'])) {
                                $hasProfitLoss = true;
                            }
                        @endphp

                        @if($hasProfitLoss)
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📊 Profit & Loss Potential</h4>
                                    <div class="row">
                                        @if(isset($crypto_analysis['potential_profit']))
                                        <div class="col-6">
                                            <p><strong>Potential Profit:</strong></p>
                                            <div class="profit-loss-info">
                                                @php
                                                    $profitValue = is_array($crypto_analysis['potential_profit']) ? $crypto_analysis['potential_profit']['usd'] : $crypto_analysis['potential_profit'];
                                                    $profitClass = $profitValue >= 0 ? 'text-success' : 'text-danger';
                                                @endphp
                                                <span class="price-value {{ $profitClass }}">${{ number_format($profitValue, 2) }}</span>
                                                @if(is_array($crypto_analysis['potential_profit']) && isset($crypto_analysis['potential_profit']['rupiah']))
                                                <span class="price-rupiah {{ $profitClass }}">Rp {{ number_format($crypto_analysis['potential_profit']['rupiah'], 0) }}</span>
                                                @endif
                                                @if($amount > 0)
                                                <span class="percentage {{ $profitClass }}">
                                                    ({{ number_format(($profitValue / $amount) * 100, 1) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                        @if(isset($crypto_analysis['potential_loss']))
                                        <div class="col-6">
                                            <p><strong>Potential Loss:</strong></p>
                                            <div class="profit-loss-info">
                                                @php
                                                    $lossValue = is_array($crypto_analysis['potential_loss']) ? $crypto_analysis['potential_loss']['usd'] : $crypto_analysis['potential_loss'];
                                                    // Loss should typically be negative, but we'll handle both cases
                                                    $displayLoss = abs($lossValue);
                                                @endphp
                                                <span class="price-value text-danger">${{ number_format($displayLoss, 2) }}</span>
                                                @if(is_array($crypto_analysis['potential_loss']) && isset($crypto_analysis['potential_loss']['rupiah']))
                                                <span class="price-rupiah text-danger">Rp {{ number_format(abs($crypto_analysis['potential_loss']['rupiah']), 0) }}</span>
                                                @endif
                                                @if($amount > 0)
                                                <span class="percentage text-danger">
                                                    ({{ number_format(($displayLoss / $amount) * 100, 1) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @php
                            // Check if indicators exist in the analysis data
                            $hasIndicators = false;
                            if (isset($crypto_analysis['analysis']['indicators']) && is_array($crypto_analysis['analysis']['indicators'])) {
                                $indicators = $crypto_analysis['analysis']['indicators'];
                                $hasIndicators = !empty($indicators);
                            }
                        @endphp

                        @if($hasIndicators)
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📊 Technical Indicators</h4>
                                    <div class="row">
                                        @if($analyst_method === AnalysisType::SNIPER)
                                            <!-- Sniper Entry Indicators -->
                                            @if(isset($indicators['ema9']))
                                            <div class="col-2">
                                                <p><strong>EMA 9:</strong></p>
                                                <span>${{ number_format($indicators['ema9'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['ema21']))
                                            <div class="col-2">
                                                <p><strong>EMA 21:</strong></p>
                                                <span>${{ number_format($indicators['ema21'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['ema50']))
                                            <div class="col-2">
                                                <p><strong>EMA 50:</strong></p>
                                                <span>${{ number_format($indicators['ema50'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['rsi']))
                                            <div class="col-2">
                                                <p><strong>RSI 14:</strong></p>
                                                <span>{{ $indicators['rsi'] }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['support']))
                                            <div class="col-2">
                                                <p><strong>Support:</strong></p>
                                                <span class="text-success">${{ number_format($indicators['support'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['resistance']))
                                            <div class="col-2">
                                                <p><strong>Resistance:</strong></p>
                                                <span class="text-danger">${{ number_format($indicators['resistance'], 3) }}</span>
                                            </div>
                                            @endif
                                        @elseif($analyst_method === AnalysisType::DYNAMIC_RR)
                                            <!-- Dynamic RR Indicators -->
                                            @if(isset($indicators['ema20']))
                                            <div class="col-2">
                                                <p><strong>EMA 20:</strong></p>
                                                <span>${{ number_format($indicators['ema20'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['ema50']))
                                            <div class="col-2">
                                                <p><strong>EMA 50:</strong></p>
                                                <span>${{ number_format($indicators['ema50'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['rsi']))
                                            <div class="col-2">
                                                <p><strong>RSI 14:</strong></p>
                                                <span>{{ $indicators['rsi'] }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['atr']))
                                            <div class="col-2">
                                                <p><strong>ATR:</strong></p>
                                                <span>${{ number_format($indicators['atr'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['support']))
                                            <div class="col-2">
                                                <p><strong>Support:</strong></p>
                                                <span class="text-success">${{ number_format($indicators['support'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['resistance']))
                                            <div class="col-2">
                                                <p><strong>Resistance:</strong></p>
                                                <span class="text-danger">${{ number_format($indicators['resistance'], 3) }}</span>
                                            </div>
                                            @endif
                                        @else
                                            <!-- Basic Analysis Indicators -->
                                            @if(isset($indicators['ema20']))
                                            <div class="col-3">
                                                <p><strong>EMA 20:</strong></p>
                                                <span>${{ number_format($indicators['ema20'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['rsi']))
                                            <div class="col-3">
                                                <p><strong>RSI 14:</strong></p>
                                                <span>{{ $indicators['rsi'] }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['support']))
                                            <div class="col-3">
                                                <p><strong>Support:</strong></p>
                                                <span class="text-success">${{ number_format($indicators['support'], 3) }}</span>
                                            </div>
                                            @endif
                                            @if(isset($indicators['resistance']))
                                            <div class="col-3">
                                                <p><strong>Resistance:</strong></p>
                                                <span class="text-danger">${{ number_format($indicators['resistance'], 3) }}</span>
                                            </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Kesimpulan Section -->
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card kesimpulan-section">
                                    <h4>📋 Kesimpulan</h4>
                                    @if($signal === 'BUY')
                                        <div class="kesimpulan-content">
                                            <p class="kesimpulan-text">
                                                <strong>💚 REKOMENDASI: ENTRY LONG</strong><br>
                                                Berdasarkan analisis teknikal menggunakan strategi
                                                {{ AnalysisType::{strtoupper($analyst_method)}()->getDisplayName() ?? 'Dasar' }},
                                                kondisi pasar saat ini menunjukkan peluang yang baik untuk posisi <strong>BUY/LONG</strong>.
                                                Semua indikator berada dalam formasi bullish yang kuat dengan
                                                level confidence {{ $confidence }}%.
                                            </p>

                                            <div class="kesimpulan-details">
                                                <div class="detail-item">
                                                    <span class="detail-label">🎯 Entry Point:</span>
                                                    <span class="detail-value">${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🛡️ Stop Loss:</span>
                                                    <span class="detail-value text-danger">${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🎯 Take Profit:</span>
                                                    <span class="detail-value text-success">${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">📊 Risk-Reward:</span>
                                                    <span class="detail-value">{{ $rrRatio }}:1</span>
                                                </div>
                                            </div>

                                            <div class="kesimpulan-recommendation">
                                                <br>
                                                <strong>💡 Rekomendasi Trading:</strong><br>
                                                • Entry pada level ${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}<br>
                                                • Set stop loss di ${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }} untuk membatasi risiko<br>
                                                • Target profit di ${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }} dengan potensi {{ $rrRatio }}x lipat dari risiko<br>
                                                • Confidence level {{ $confidence }}% - {{ $confidence >= 80 ? 'SANGAT DIREKOMENDASIKAN' : ($confidence >= 60 ? 'DIREKOMENDASIKAN' : 'PERTIMBANGKAN HATI-HATI') }}
                                            </div>
                                        </div>

                                    @elseif($signal === 'SELL')
                                        <div class="kesimpulan-content">
                                            <p class="kesimpulan-text">
                                                <strong>❤️ REKOMENDASI: ENTRY SHORT</strong><br>
                                                Analisis teknikal dengan strategi
                                                {{ AnalysisType::{strtoupper($analyst_method)}()->getDisplayName() ?? 'Dasar' }}
                                                menunjukkan kondisi pasar yang menguntungkan untuk posisi <strong>SELL/SHORT</strong>.
                                                Formasi indikator menunjukkan tren bearish yang kuat dengan confidence level {{ $confidence }}%.
                                            </p>

                                            <div class="kesimpulan-details">
                                                <div class="detail-item">
                                                    <span class="detail-label">🎯 Entry Point:</span>
                                                    <span class="detail-value">${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🛡️ Stop Loss:</span>
                                                    <span class="detail-value text-danger">${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🎯 Take Profit:</span>
                                                    <span class="detail-value text-success">${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">📊 Risk-Reward:</span>
                                                    <span class="detail-value">{{ $rrRatio }}:1</span>
                                                </div>
                                            </div>

                                            <div class="kesimpulan-recommendation">
                                                <strong>💡 Rekomendasi Trading:</strong><br>
                                                • Entry short pada level ${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}<br>
                                                • Set stop loss di ${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }} untuk membatasi risiko<br>
                                                • Target profit di ${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }} dengan potensi {{ $rrRatio }}x lipat dari risiko<br>
                                                • Confidence level {{ $confidence }}% - {{ $confidence >= 80 ? 'SANGAT DIREKOMENDASIKAN' : ($confidence >= 60 ? 'DIREKOMENDASIKAN' : 'PERTIMBANGKAN HATI-HATI') }}
                                            </div>
                                        </div>

                                    @else
                                        <div class="kesimpulan-content">
                                            <p class="kesimpulan-text">
                                                <strong>💛 KONDISI: NETRAL</strong><br>
                                                Berdasarkan analisis
                                                {{ AnalysisType::{strtoupper($analyst_method)}()->getDisplayName() ?? 'Dasar' }},
                                                kondisi pasar saat ini menunjukkan sinyal <strong>NETRAL</strong>.
                                                Tidak ada formasi yang cukup kuat untuk merekomendasikan posisi BUY atau SELL dengan confidence yang tinggi ({{ $confidence }}%).
                                            </p>

                                            <div class="kesimpulan-recommendation">
                                                <strong>💡 Rekomendasi:</strong><br>
                                                • Tunggu konfirmasi sinyal yang lebih jelas<br>
                                                • Monitor pergerakan harga untuk setup yang lebih baik<br>
                                                • Pertimbangkan untuk tidak entry sampai ada sinyal yang lebih konfirm<br>
                                                • Confidence level {{ $confidence }}% - menunjukkan ketidakpastian pasar
                                            </div>
                                        </div>
                                    @endif
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

                <!-- Analysis Conclusion -->
                @if(isset($crypto_analysis) && !isset($crypto_analysis['error']))
                <div class="analysis-results info-card mt-4">
                    <h4>🔍 Kesimpulan Analisis</h4>
                    @php
                        // Handle both old and new analysis result structures
                        if (isset($crypto_analysis['analysis'])) {
                            // Old structure (backward compatibility)
                            $signal = $crypto_analysis['analysis']['signal'] ?? 'NEUTRAL';
                            $confidence = $crypto_analysis['analysis']['confidence'] ?? 0;
                            $rrRatio = $crypto_analysis['analysis']['rr_ratio'] ?? 0;
                        } else {
                            // New structure from analysis services
                            $signal = $crypto_analysis['signal'] ?? 'NEUTRAL';
                            $confidence = $crypto_analysis['confidence'] ?? 0;
                            $rrRatio = $crypto_analysis['risk_reward'] ?? 0;
                        }
                    @endphp

                    @if($signal === 'BUY')
                        <div class="alert alert-success">
                            <h5>📈 REKOMENDASI PERDAGANGAN LONG</h5>
                            <p><strong>Kekuatan Sinyal:</strong> Tingkat kepercayaan {{ $confidence }}%</p>
                            <p><strong>Rasio Risiko-Hadiah:</strong> {{ $rrRatio }}:1</p>
                            <p>Analisis ini menunjukkan peluang <strong>BUY</strong> dengan titik masuk di dekat harga pasar saat ini. Stop loss ditempatkan di bawah level support kunci, dan target take profit ditetapkan di zona resistance atau level Fibonacci.</p>

                            @if($analyst_method === AnalysisType::DYNAMIC_RR)
                                <p><em>Metode RR Dinamis telah mengidentifikasi level optimal berdasarkan volatilitas pasar saat ini dan struktur teknis.</em></p>
                            @elseif($analyst_method === AnalysisType::SNIPER)
                                <p><em>Metode Sniper telah mengkonfirmasi keselarasan kuat dari beberapa indikator teknis untuk setup perdagangan ini.</em></p>
                            @else
                                <p><em>Metode analisis Dasar menunjukkan kondisi pasar yang menguntungkan untuk arah perdagangan ini.</em></p>
                            @endif
                        </div>
                    @elseif($signal === 'SELL')
                        <div class="alert alert-danger">
                            <h5>📉 REKOMENDASI PERDAGANGAN SHORT</h5>
                            <p><strong>Kekuatan Sinyal:</strong> Tingkat kepercayaan {{ $confidence }}%</p>
                            <p><strong>Rasio Risiko-Hadiah:</strong> {{ $rrRatio }}:1</p>
                            <p>Analisis ini menunjukkan peluang <strong>SELL</strong> dengan titik masuk di dekat harga pasar saat ini. Stop loss ditempatkan di atas level resistance kunci, dan target take profit ditetapkan di zona support atau level Fibonacci.</p>

                            @if($analyst_method === AnalysisType::DYNAMIC_RR)
                                <p><em>Metode RR Dinamis telah mengidentifikasi level optimal berdasarkan volatilitas pasar saat ini dan struktur teknis.</em></p>
                            @elseif($analyst_method === AnalysisType::SNIPER)
                                <p><em>Metode Sniper telah mengkonfirmasi keselarasan kuat dari beberapa indikator teknis untuk setup perdagangan ini.</em></p>
                            @else
                                <p><em>Metode analisis Dasar menunjukkan kondisi pasar yang menguntungkan untuk arah perdagangan ini.</em></p>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <h5>⏸️ KONDISI PASAR NETRAL</h5>
                            <p><strong>Kekuatan Sinyal:</strong> Tingkat kepercayaan {{ $confidence }}%</p>
                            <p>Kondisi pasar saat ini tidak menunjukkan peluang perdagangan yang jelas. Disarankan untuk:</p>
                            <ul>
                                <li>Menunggu konfirmasi teknis yang lebih kuat</li>
                                <li>Memantau level support dan resistance kunci</li>
                                <li>Waspadai pola breakout atau breakdown</li>
                                <li>Pertimbangkan koin atau pasar lain untuk peluang</li>
                            </ul>

                            @if($analyst_method === AnalysisType::DYNAMIC_RR)
                                <p><em>Metode RR Dinamis menyarankan kondisi pasar tidak optimal untuk perdagangan saat ini.</em></p>
                            @elseif($analyst_method === AnalysisType::SNIPER)
                                <p><em>Metode Sniper tidak menemukan konvergensi yang cukup untuk setup dengan probabilitas tinggi.</em></p>
                            @else
                                <p><em>Metode analisis Dasar menunjukkan kondisi pasar netral.</em></p>
                            @endif
                        </div>
                    @endif

                    <!-- Market Context -->
                    <div class="alert alert-info mt-3">
                        <h5>🌐 Konteks Pasar</h5>
                        <p><strong>Harga Saat Ini:</strong> ${{ number_format((is_array($entry) ? ($entry['usd'] ?? 0) : $entry) ?? 0, 8) }}</p>
                        <p><strong>Terakhir Diperbarui:</strong> {{ $crypto_analysis['last_updated'] ?? now()->format('Y-m-d H:i:s') }}</p>
                        <p>Analisis ini berdasarkan indikator teknis dan harus dikombinasikan dengan analisis fundamental dan berita pasar untuk keputusan perdagangan yang optimal.</p>
                    </div>
                </div>
                @endif

                <!-- Analysis Method Description -->
                <div class="analysis-results info-card mt-4">
                    <h4>📋 Deskripsi Metode Analisis</h4>
                    @if($analyst_method === AnalysisType::SNIPER)
                        <div class="mt-3 p-3 mb-5 border rounded">
                            <h5>📚 Analisis Sniper:</h5>
                            <p><strong>Analisis Sniper</strong> menggunakan volume dan price action untuk sinyal entry yang presisi tinggi. Metode ini mencari konvergensi indikator teknis yang optimal untuk mengidentifikasi peluang trading dengan probabilitas tinggi.</p>
                            <ul>
                                <li>Menggunakan volume dan price action untuk sinyal entry presisi tinggi</li>
                                <li>Penyelarasan beberapa indikator teknis untuk konfirmasi tren</li>
                                <li>RSI dalam rentang optimal (45-75 untuk beli, 25-55 untuk jual)</li>
                                <li>Target rasio risiko-hadiah 3:1 untuk entri sniper</li>
                                <li>Penilaian kepercayaan yang ditingkatkan berdasarkan kondisi yang terpenuhi</li>
                            </ul>
                        </div>
                    @elseif($analyst_method === AnalysisType::DYNAMIC_RR)
                        <div class="mt-3 p-3 bg-light border rounded">
                            <h5>📚 Analisis RR Dinamis:</h5>
                            <p><strong>Analisis RR Dinamis</strong> menggunakan perhitungan risiko-hadiah adaptif berdasarkan volatilitas pasar, level Fibonacci, dan zona support/resistance. Metode ini secara dinamis menyesuaikan level take profit dan stop loss berdasarkan kondisi pasar saat ini.</p>
                            <ul>
                                <li>ATR (Average True Range) untuk penempatan stop loss berbasis volatilitas</li>
                                <li>Level retracement Fibonacci (23.6%, 38.2%, 50.0%, 61.8%, 78.6%) untuk target take profit</li>
                                <li>Perhitungan rasio risiko-hadiah dinamis berdasarkan kondisi pasar</li>
                                <li>Integrasi level support dan resistance untuk entri yang optimal</li>
                                <li>Persyaratan rasio risiko-hadiah minimum 1,5:1</li>
                                <li>Penilaian kepercayaan berdasarkan keselarasan indikator teknis</li>
                            </ul>
                            <div class="alert alert-info mt-2">
                                <h6>💡 Keuntungan Utama:</h6>
                                <p>Metode ini beradaptasi dengan kondisi pasar daripada menggunakan rasio tetap, yang membantu dalam:</p>
                                <ul>
                                    <li>Menghindari overtrading dalam kondisi volatilitas rendah</li>
                                    <li>Memaksimalkan keuntungan di pasar yang tren kuat</li>
                                    <li>Mengurangi kerugian dengan menempatkan stop berdasarkan volatilitas aktual</li>
                                    <li>Menggunakan level Fibonacci untuk mengidentifikasi target harga alami</li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="mt-3 p-3 bg-light border rounded">
                            <h5>📚 Analisis Dasar:</h5>
                            <p><strong>Analisis Dasar</strong> menggunakan indikator teknis standar (EMA 20, RSI 14) pada timeframe 1 jam untuk arah pasar umum. Metode ini menyediakan pendekatan yang sederhana namun efektif untuk analisis pasar.</p>
                            <ul>
                                <li>EMA 20 dan RSI 14 pada timeframe 1 jam</li>
                                <li>Sinyal beli/jual sederhana berdasarkan harga vs EMA</li>
                                <li>Identifikasi level support dan resistance</li>
                                <li>Target rasio risiko-hadiah 2:1</li>
                                <li>Penilaian kepercayaan yang mudah</li>
                            </ul>
                        </div>
                    @endif

                    <!-- General Trading Advice -->
                    <div class="mt-3 p-3 bg-warning text-dark border rounded">
                        <h5>⚠️ Catatan Penting Trading:</h5>
                        <ul>
                            <li>Selalu gunakan ukuran posisi yang tepat sesuai toleransi risiko Anda</li>
                            <li>Sinyal ini hanya untuk tujuan informasi, bukan saran keuangan</li>
                            <li>Pertimbangkan berita pasar dan peristiwa yang dapat mempengaruhi pergerakan harga</li>
                            <li>Gunakan metode konfirmasi tambahan sebelum memasuki perdagangan</li>
                            <li>Pantau perdagangan dan sesuaikan stop sesuai kebutuhan berdasarkan kondisi pasar</li>
                        </ul>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Analysis Form Section -->
        <div class="col-12">
            <x-card title="🎯 Konfigurasi Analisis">
                <x-form method="GET" action="{{ route(module('getUpdate'), $model) }}">
                    <x-select searchable name="coin_code" :value="$model->coin_code" :options="$coin" label="Select Coin" required/>
                    <x-select name="analyst" :options="$analyst_methods" label="Metode Analisis" :value="request('analyst', 'sniper')" required/>
                    <x-input type="number" name="amount" :value="request('amount', 1000)" label="Trading Amount (USD)" step="0.01" min="1" required placeholder="Enter amount to trade"/>

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