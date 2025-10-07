<x-layout>
    <div id="success-message" data-message="{{ session('success') }}" style="display: none;"></div>
    <div class="card">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Trade Management</h2>
            <button type="button" id="toggle-filters" class="button secondary">
                <i class="bi bi-filter"></i> <span>Hide</span>
            </button>
        </div>
        <div class="card-table">
            <div class="form-table-container">
                <form id="filter-form" class="form-table-filter" method="GET" action="{{ route(module('getData')) }}">
                    <div class="row">
                        <x-select name="perpage" label="Page" :options="['10' => '10', '20' => '20', '50' => '50', '100' => '100']" :value="request('perpage', 10)" col="2" id="perpage-select"/>
                        <x-select name="status" :options="[
                            null => 'All Status',
                            'pending' => 'Pending',
                            'open' => 'Open',
                            'filled' => 'Filled',
                            'cancelled' => 'Cancelled',
                            'rejected' => 'Rejected'
                        ]" :value="request('status')" col="3"/>
                        <x-select name="side" :options="[null => 'All Sides', 'buy' => 'Buy', 'sell' => 'Sell']" :value="request('side')" col="2"/>
                        <x-select name="type" :options="[null => 'All Types', 'market' => 'Market', 'limit' => 'Limit', 'stop' => 'Stop', 'stop_limit' => 'Stop Limit']" :value="request('type')" col="2"/>
                        <x-input name="search" type="text" placeholder="Search by symbol or trade ID" :value="request('search')" col="3"/>
                    </div>
                    <div class="form-actions">
                        <x-button type="button" class="secondary" :attributes="['onclick' => 'window.location.href=\'' . url()->current() . '\'']">Reset</x-button>
                        <x-button type="submit" class="primary">Search</x-button>
                    </div>
                </form>
                <div class="form-table-content">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-column"><input type="checkbox" class="checkall" /></th>
                                    <th class="text-center actions">Actions</th>
                                    <th>Trade ID</th>
                                    <th>Symbol</th>
                                    <th>Side</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>P&L</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="hide-lg">
                                    <td data-label="Check All Data" class="checkbox-column">
                                        <input type="checkbox" class="checkall" />
                                    </td>
                                </tr>
                                @forelse($data as $item)
                                    <tr>
                                        <td class="checkbox-column">
                                            <input type="checkbox" class="row-checkbox" value="{{ $item->trade_id }}" />
                                        </td>
                                        <td data-label="Actions">
                                            <div class="action-table">
                                                @if(in_array($item->status, ['pending', 'open']))
                                                    <button type="button" class="button success" onclick="executeTrade('{{ $item->trade_id }}')" title="Execute">
                                                        <i class="bi bi-play"></i>
                                                    </button>
                                                    <button type="button" class="button warning" onclick="cancelTrade('{{ $item->trade_id }}')" title="Cancel">
                                                        <i class="bi bi-stop"></i>
                                                    </button>
                                                @endif

                                                <a href="{{ route(module('getShow'), $item->trade_id) }}" class="button primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>

                                                <a href="{{ route(module('getUpdate'), $item->trade_id) }}" class="button info" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <td data-label="Trade ID">
                                            <span class="trade-id">{{ $item->trade_id }}</span>
                                        </td>
                                        <td data-label="Symbol">
                                            <strong>{{ $item->symbol }}</strong>
                                        </td>
                                        <td data-label="Side">
                                            <span class="badge {{ $item->side === 'buy' ? 'success' : 'danger' }}">
                                                {{ ucfirst($item->side) }}
                                            </span>
                                        </td>
                                        <td data-label="Type">{{ ucfirst($item->type) }}</td>
                                        <td data-label="Amount">{{ number_format($item->amount, 8) }}</td>
                                        <td data-label="Price">
                                            @if($item->price)
                                                ${{ number_format($item->price, 4) }}
                                            @else
                                                <em>Market</em>
                                            @endif
                                        </td>
                                        <td data-label="Status">
                                            <span class="badge {{ $item->status_color }}">
                                                {{ $item->status_text }}
                                            </span>
                                        </td>
                                        <td data-label="P&L">
                                            @if($item->pnl !== null)
                                                <span class="{{ $item->pnl >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $item->formatted_pnl }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td data-label="Created">
                                            {{ $item->created_at->format('M d, H:i') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center">No trades found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <x-pagination :data="$data" />
            </div>
        </div>
        <x-footer>
            <button type="button" class="button danger" id="bulk-delete-btn" disabled onclick="confirmBulkDelete()">Delete</button>
            <button type="button" class="button info" onclick="syncTrades()">Sync with Exchange</button>
            <a href="{{ route(module('getCreate')) }}" class="button success">
                <i class="bi bi-plus"></i>Create Trade
            </a>
        </x-footer>

        <form id="bulk-delete-form" method="POST" action="{{ route(module('postBulkDelete')) }}" style="display: none;">
            @csrf
            <input type="hidden" name="ids" id="bulk-delete-ids">
        </form>
    </div>

    <script>


        function syncTrades() {
            if (confirm('This will sync all active trades with the exchange. Continue?')) {
                window.location.href = '{{ route(module("getSync")) }}';
            }
        }

        function confirmBulkDelete() {
            const selectedTrades = document.querySelectorAll('.row-checkbox:checked');
            if (selectedTrades.length === 0) {
                alert('Please select trades to delete.');
                return;
            }

            const tradeIds = Array.from(selectedTrades).map(cb => cb.value);
            if (confirm(`Are you sure you want to delete ${tradeIds.length} selected trades?`)) {
                document.getElementById('bulk-delete-ids').value = tradeIds.join(',');
                document.getElementById('bulk-delete-form').submit();
            }
        }

        // Enable/disable bulk delete button
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    bulkDeleteBtn.disabled = checkedBoxes.length === 0;
                });
            });
        });
    </script>
</x-layout>