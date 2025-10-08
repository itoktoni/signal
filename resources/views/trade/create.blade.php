<x-layout>
    <div class="card">
        <div class="page-header">
            <h2><i class="bi bi-currency-bitcoin"></i> Tokocrypto Trading Platform</h2>
        </div>

        <!-- Market Overview Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Market Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row" id="marketOverview">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h6 class="text-muted">Exchange Status</h6>
                                    <span class="badge bg-success fs-6">
                                        <i class="bi bi-circle-fill"></i> Connected
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h6 class="text-muted">Trading Mode</h6>
                                    <span class="badge bg-{{ config('services.tokocrypto.sandbox', false) ? 'warning' : 'success' }} fs-6">
                                        {{ config('services.tokocrypto.sandbox', false) ? 'Sandbox' : 'Live Trading' }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h6 class="text-muted">Available Pairs</h6>
                                    <span class="fs-5 fw-bold" id="availablePairs">-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h6 class="text-muted">Rate Limit</h6>
                                    <span class="fs-5 fw-bold" id="rateLimit">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Market Data Section -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Market Data</h5>
                    </div>
                    <div class="card-body">
                        <!-- Coin Selection -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <x-select name="coin_symbol" id="coinSelect" label="Select Coin"
                                    :options="$coins"
                                    :value="request('coin_symbol')" col="12"
                                    onchange="updateTradingPairs()"/>
                            </div>
                            <div class="col-md-6">
                                <x-select name="symbol" id="symbolSelect" label="Trading Pair"
                                    :options="$popular_pairs"
                                    :value="request('symbol', 'BTC/USDT')" col="12"/>
                            </div>
                        </div>

                        <!-- Available Trading Pairs for Selected Coin -->
                        <div class="row mb-3" id="tradingPairsSection" style="display: none;">
                            <div class="col-12">
                                <label class="form-label">Available Trading Pairs</label>
                                <div id="tradingPairsContainer" class="d-flex flex-wrap gap-2">
                                    <!-- Trading pairs will be loaded here -->
                                </div>
                            </div>
                        </div>

                        <!-- Symbol Selection (Original) -->
                            <div class="col-md-6">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <x-button type="button" class="primary" onclick="refreshTicker()">
                                        <i class="bi bi-arrow-repeat"></i> Refresh
                                    </x-button>
                                    <x-button type="button" class="secondary" onclick="refreshOrderBook()">
                                        <i class="bi bi-book"></i> Order Book
                                    </x-button>
                                </div>
                            </div>
                        </div>

                        <!-- Ticker Display -->
                        <div id="tickerSection" class="mb-4" style="display: none;">
                            <h6>Price Information</h6>
                            <div class="row" id="tickerData">
                                <!-- Ticker data will be loaded here -->
                            </div>
                        </div>

                        <!-- Order Book -->
                        <div id="orderBookSection" style="display: none;">
                            <h6>Order Book</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-success">Bids</h6>
                                    <div id="bidsList" class="small">
                                        <!-- Bids will be loaded here -->
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-danger">Asks</h6>
                                    <div id="asksList" class="small">
                                        <!-- Asks will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trading Panel -->
            <div class="col-lg-4">
                <!-- Account Balance -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-wallet"></i> Account Balance</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $credentialsConfigured = !empty(config('services.tokocrypto.api_key')) && !empty(config('services.tokocrypto.secret'));
                        @endphp

                        @if (!$credentialsConfigured)
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                API credentials not configured. Set TOKOCRYPTO_API_KEY and TOKOCRYPTO_API_SECRET to view balance.
                            </div>
                        @else
                            <div id="balanceSection">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <small class="text-muted">Loading balance...</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Trading Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Place Order</h5>
                    </div>
                    <div class="card-body">
                        <form id="orderForm">
                            <!-- Side Selection -->
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="side" id="buyRadio" value="buy" checked>
                                        <label class="form-check-label text-success fw-bold" for="buyRadio">
                                            <i class="bi bi-arrow-up-circle"></i> Buy
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="side" id="sellRadio" value="sell">
                                        <label class="form-check-label text-danger fw-bold" for="sellRadio">
                                            <i class="bi bi-arrow-down-circle"></i> Sell
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Type -->
                            <div class="mb-3">
                                <label class="form-label">Order Type</label>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" id="marketRadio" value="market" checked>
                                            <label class="form-check-label" for="marketRadio">Market</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" id="limitRadio" value="limit">
                                            <label class="form-check-label" for="limitRadio">Limit</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Symbol -->
                            <x-input name="symbol" id="symbolInput" label="Symbol" value="BTC/USDT" required col="12"/>

                            <!-- Trading Mode Toggle -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Trading Mode</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="amount_mode" id="baseAmountMode" value="base">
                                                <label class="form-check-label" for="baseAmountMode">
                                                    <i class="bi bi-currency-bitcoin"></i> Base Currency Amount
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="amount_mode" id="usdAmountMode" value="usd" checked>
                                                <label class="form-check-label" for="usdAmountMode">
                                                    <i class="bi bi-currency-dollar"></i> USDT Amount
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Current Price Display -->
                            <div class="row mb-3" id="priceInfoSection" style="display: none;">
                                <div class="col-12">
                                    <div class="card bg-light">
                                        <div class="card-body py-2">
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <small class="text-muted">Current Price</small>
                                                    <div class="fw-bold" id="currentPrice">-</div>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">24h Change</small>
                                                    <div class="fw-bold" id="priceChange">-</div>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted">Base/USDT</small>
                                                    <div class="fw-bold" id="basePerUsdt">-</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Base Currency Amount -->
                            <div id="baseAmountSection">
                                <x-input name="amount" id="amountInput" type="number" label="Amount" step="0.00000001" min="0.00000001" required col="12">
                                    <small class="form-text text-muted">Amount in base currency (e.g., BTC, ETH)</small>
                                </x-input>
                            </div>

                            <!-- USDT Amount -->
                            <div id="usdAmountSection" style="display: none;">
                                <x-input name="usd_amount" id="usdAmountInput" type="number" label="USDT Amount" step="0.01" min="0.01" col="12">
                                    <small class="form-text text-muted">Amount in USDT (will be converted to base currency)</small>
                                </x-input>
                            </div>

                            <!-- Price (Limit Orders) -->
                            <div class="mb-3" id="priceSection" style="display: none;">
                                <x-input name="price" id="priceInput" type="number" label="Price (USDT)" step="0.01" min="0.01" col="12">
                                    <small class="form-text text-muted">Price per unit for limit orders</small>
                                </x-input>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <x-button type="submit" class="primary" id="submitBtn">
                                    <i class="bi bi-lightning"></i> <span id="submitText">Place Market Buy Order</span>
                                </x-button>
                            </div>

                            <!-- Result Message -->
                            <div id="orderResult" class="mt-3" style="display: none;"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentSymbol = 'BTC/USDT';

        // Update symbol when selection changes
        document.getElementById('symbolSelect').addEventListener('change', function() {
            currentSymbol = this.value;
            document.getElementById('symbolInput').value = currentSymbol;
            refreshTicker();
        });

        // Update trading pairs when coin selection changes
        async function updateTradingPairs() {
            const coinSelect = document.getElementById('coinSelect');
            const selectedCoin = coinSelect.value;

            if (!selectedCoin) {
                document.getElementById('tradingPairsSection').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`{{ route('trade.tradingAjax') }}?action=get_trading_pairs&coin=${encodeURIComponent(selectedCoin)}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (data.success) {
                    displayTradingPairs(data.pairs);
                } else {
                    console.error('Failed to fetch trading pairs:', data.error);
                }
            } catch (error) {
                console.error('Error fetching trading pairs:', error);
            }
        }

        // Display trading pairs for selected coin
        function displayTradingPairs(pairs) {
            const container = document.getElementById('tradingPairsContainer');
            const section = document.getElementById('tradingPairsSection');

            if (Object.keys(pairs).length === 0) {
                section.style.display = 'none';
                return;
            }

            let html = '';
            Object.entries(pairs).forEach(([symbol, name]) => {
                html += `
                    <button type="button" class="btn btn-outline-primary btn-sm"
                            onclick="selectTradingPair('${symbol}')">
                        ${name}
                    </button>
                `;
            });

            container.innerHTML = html;
            section.style.display = 'block';
        }

        // Select a trading pair from the available options
        function selectTradingPair(symbol) {
            document.getElementById('symbolSelect').value = symbol;
            document.getElementById('symbolInput').value = symbol;
            currentSymbol = symbol;
            refreshTicker();
        }

        // Toggle between base currency and USDT amount modes
        function toggleAmountMode(mode) {
            const baseAmountSection = document.getElementById('baseAmountSection');
            const usdAmountSection = document.getElementById('usdAmountSection');
            const priceInfoSection = document.getElementById('priceInfoSection');

            if (mode === 'usd') {
                baseAmountSection.style.display = 'none';
                usdAmountSection.style.display = 'block';
                priceInfoSection.style.display = 'block';
                document.getElementById('amountInput').removeAttribute('required');
                document.getElementById('usdAmountInput').setAttribute('required', 'required');
            } else {
                baseAmountSection.style.display = 'block';
                usdAmountSection.style.display = 'none';
                priceInfoSection.style.display = 'block';
                document.getElementById('usdAmountInput').removeAttribute('required');
                document.getElementById('amountInput').setAttribute('required', 'required');
            }

            // Clear the other amount field
            if (mode === 'usd') {
                document.getElementById('amountInput').value = '';
            } else {
                document.getElementById('usdAmountInput').value = '';
            }
        }

        // Convert USDT amount to base currency amount
        function convertUsdToBase() {
            const usdAmount = parseFloat(document.getElementById('usdAmountInput').value);
            const currentPrice = parseFloat(document.getElementById('currentPrice').textContent.replace(/,/g, ''));

            if (usdAmount > 0 && currentPrice > 0) {
                const baseAmount = usdAmount / currentPrice;
                document.getElementById('amountInput').value = baseAmount.toFixed(8);
            }
        }

        // Convert base currency amount to USDT amount
        function convertBaseToUsd() {
            const baseAmount = parseFloat(document.getElementById('amountInput').value);
            const currentPrice = parseFloat(document.getElementById('currentPrice').textContent.replace(/,/g, ''));

            if (baseAmount > 0 && currentPrice > 0) {
                const usdAmount = baseAmount * currentPrice;
                document.getElementById('usdAmountInput').value = usdAmount.toFixed(2);
            }
        }

        // Update form when symbol input changes
        document.getElementById('symbolInput').addEventListener('input', function() {
            currentSymbol = this.value;
            document.getElementById('symbolSelect').value = currentSymbol;
        });

        // Show/hide price field based on order type
        document.querySelectorAll('input[name="type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const priceSection = document.getElementById('priceSection');
                const submitText = document.getElementById('submitText');

                if (this.value === 'limit') {
                    priceSection.style.display = 'block';
                    document.getElementById('priceInput').required = true;
                } else {
                    priceSection.style.display = 'none';
                    document.getElementById('priceInput').required = false;
                }

                updateSubmitButton();
            });
        });

        // Update submit button text
        document.querySelectorAll('input[name="side"], input[name="type"]').forEach(input => {
            input.addEventListener('change', updateSubmitButton);
        });

        // Handle trading mode toggle
        document.querySelectorAll('input[name="amount_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                toggleAmountMode(this.value);
            });
        });

        // Handle USDT amount input changes
        document.getElementById('usdAmountInput')?.addEventListener('input', function() {
            convertUsdToBase();
        });

        // Handle base amount input changes
        document.getElementById('amountInput')?.addEventListener('input', function() {
            convertBaseToUsd();
        });

        function updateSubmitButton() {
            const side = document.querySelector('input[name="side"]:checked').value;
            const type = document.querySelector('input[name="type"]:checked').value;
            const submitText = document.getElementById('submitText');

            submitText.textContent = `Place ${type.charAt(0).toUpperCase() + type.slice(1)} ${side.charAt(0).toUpperCase() + side.slice(1)} Order`;
        }

        // Refresh ticker data
        async function refreshTicker() {
            try {
                const response = await fetch(`{{ route('trade.tradingAjax') }}?action=get_ticker&symbol=${encodeURIComponent(currentSymbol)}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (data.success) {
                    displayTicker(data.data);
                } else {
                    showError('Failed to fetch ticker: ' + data.error);
                }
            } catch (error) {
                showError('Error fetching ticker: ' + error.message);
            }
        }

        // Display ticker data
        function displayTicker(ticker) {
            const section = document.getElementById('tickerSection');
            const container = document.getElementById('tickerData');

            // Update price info section
            document.getElementById('currentPrice').textContent = formatPrice(ticker.last);

            const changePercent = ticker.percentage ? parseFloat(ticker.percentage) : 0;
            const changeClass = changePercent >= 0 ? 'text-success' : 'text-danger';
            const changeIcon = changePercent >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';

            document.getElementById('priceChange').innerHTML = `
                <span class="${changeClass}">
                    <i class="bi ${changeIcon}"></i>
                    ${formatPrice(Math.abs(changePercent))}%
                </span>
            `;

            if (ticker.last && ticker.last > 0) {
                const basePerUsdt = 1 / ticker.last;
                document.getElementById('basePerUsdt').textContent = formatNumber(basePerUsdt);
            }

            // Update main ticker display
            container.innerHTML = `
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <small class="text-muted">Last Price</small>
                            <h5 class="mb-0">${formatPrice(ticker.last)}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <small class="text-muted">24h High</small>
                            <h5 class="mb-0 text-success">${formatPrice(ticker.high)}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <small class="text-muted">24h Low</small>
                            <h5 class="mb-0 text-danger">${formatPrice(ticker.low)}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <small class="text-muted">24h Volume</small>
                            <h5 class="mb-0">${formatNumber(ticker.baseVolume)}</h5>
                        </div>
                    </div>
                </div>
            `;

            section.style.display = 'block';
        }

        // Refresh order book
        async function refreshOrderBook() {
            try {
                const response = await fetch(`{{ route('trade.tradingAjax') }}?action=get_order_book&symbol=${encodeURIComponent(currentSymbol)}&limit=10`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (data.success) {
                    displayOrderBook(data.data);
                } else {
                    showError('Failed to fetch order book: ' + data.error);
                }
            } catch (error) {
                showError('Error fetching order book: ' + error.message);
            }
        }

        // Display order book
        function displayOrderBook(orderBook) {
            const bidsList = document.getElementById('bidsList');
            const asksList = document.getElementById('asksList');

            bidsList.innerHTML = orderBook.bids.slice(0, 5).map(bid =>
                `<div class="d-flex justify-content-between">
                    <span class="text-success">${formatPrice(bid[0])}</span>
                    <span>${formatNumber(bid[1])}</span>
                </div>`
            ).join('');

            asksList.innerHTML = orderBook.asks.slice(0, 5).map(ask =>
                `<div class="d-flex justify-content-between">
                    <span class="text-danger">${formatPrice(ask[0])}</span>
                    <span>${formatNumber(ask[1])}</span>
                </div>`
            ).join('');

            document.getElementById('orderBookSection').style.display = 'block';
        }

        // Handle form submission
        document.getElementById('orderForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const resultDiv = document.getElementById('orderResult');

            // Disable button during submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

            try {
                const amountMode = document.querySelector('input[name="amount_mode"]:checked').value;
                const formData = new FormData();
                formData.append('side', document.querySelector('input[name="side"]:checked').value);
                formData.append('type', document.querySelector('input[name="type"]:checked').value);
                formData.append('symbol', document.getElementById('symbolInput').value);
                formData.append('amount_mode', amountMode);

                // Handle amount based on mode
                if (amountMode === 'usd') {
                    const usdAmount = document.getElementById('usdAmountInput').value;
                    formData.append('usd_amount', usdAmount);
                    // Server will convert USDT amount to base amount
                } else {
                    formData.append('amount', document.getElementById('amountInput').value);
                }

                if (document.querySelector('input[name="type"]:checked').value === 'limit') {
                    formData.append('price', document.getElementById('priceInput').value);
                }

                // Add CSRF token to the form data
                formData.append('_token', '{{ csrf_token() }}');

                const response = await fetch('{{ route('trade.tradingAjax') }}?action=place_order', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    resultDiv.className = 'alert alert-success';
                    resultDiv.innerHTML = `<i class="bi bi-check-circle"></i> Order placed successfully! Order ID: ${data.data.id}`;
                    document.getElementById('orderForm').reset();
                    updateSubmitButton();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Error: ${error.message}`;
            }

            resultDiv.style.display = 'block';

            // Re-enable button
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-lightning"></i> <span id="submitText">Place Order</span>';
        });

        // Utility functions
        function formatPrice(price) {
            return parseFloat(price).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 8
            });
        }

        function formatNumber(num) {
            return parseFloat(num).toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 8
            });
        }

        function showError(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.card').prepend(alertDiv);

            setTimeout(() => alertDiv.remove(), 5000);
        }

        // Load balance if API credentials are configured
        @if (!empty(config('services.tokocrypto.api_key')) && !empty(config('services.tokocrypto.secret')))
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const response = await fetch('{{ route('trade.tradingAjax') }}?action=get_balance', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                // Check if response is ok before parsing JSON
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success) {
                    displayBalance(data.data);
                } else {
                    document.getElementById('balanceSection').innerHTML =
                        '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ' + (data.error || 'Unable to load balance') + '</div>';
                }
            } catch (error) {
                console.error('Balance loading error:', error);

                // Handle JSON parsing errors specifically
                if (error.message.includes('JSON') || error.message.includes('Unexpected token')) {
                    document.getElementById('balanceSection').innerHTML =
                        '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> API credentials may not be configured properly</div>';
                } else {
                    document.getElementById('balanceSection').innerHTML =
                        '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Error loading balance: ' + error.message + '</div>';
                }
            }
        });
        @endif

        function displayBalance(balance) {
            const section = document.getElementById('balanceSection');
            let html = '<div class="row g-2">';

            if (balance.total) {
                Object.entries(balance.total).forEach(([currency, amount]) => {
                    if (parseFloat(amount) > 0) {
                        html += `
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                    <span class="fw-bold">${currency}</span>
                                    <span>${formatNumber(amount)}</span>
                                </div>
                            </div>
                        `;
                    }
                });
            }

            html += '</div>';
            section.innerHTML = html || '<div class="text-muted">No balance to display</div>';
        }

        // Auto-refresh ticker every 30 seconds
        setInterval(refreshTicker, 30000);

        // Initial load
        document.addEventListener('DOMContentLoaded', function() {
            refreshTicker();

            // If a coin is pre-selected, load its trading pairs
            const coinSelect = document.getElementById('coinSelect');
            if (coinSelect.value) {
                updateTradingPairs();
            }

            // Initialize with USDT mode as default
            toggleAmountMode('usd');
        });
    </script>
</x-layout>