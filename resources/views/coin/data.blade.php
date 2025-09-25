<x-layout>
<style>
.pnl-details {
    text-align: left;
    line-height: 1.3;
}
.pnl-line {
    font-size: 1.2rem;
    font-weight: bold;
    margin: 0;
}
.pnl-amount {
    font-size: 1.5rem;
    font-weight: bold;
    margin-top: 0.5rem;
}
</style>
    <div id="success-message" data-message="{{ session('success') }}" style="display: none;"></div>
    <div class="card">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Coin Crypto</h2>
            <button type="button" id="toggle-filters" class="button secondary">
                <i class="bi bi-filter"></i> <span>Hide</span>
            </button>
        </div>
        <div class="card-table">
            <div class="form-table-container">
                <form id="filter-form" class="form-table-filter" method="GET" action="{{ route(module('getData')) }}">
                    <div class="row">
                        <x-select name="perpage" label="Page" :options="['10' => '10', '20' => '20', '50' => '50', '100' => '100']" :value="request('perpage', 10)" col="1" id="perpage-select"/>
                        <x-select name="coin_watch" :options="[null => 'Select Watch', 1 => 'Watch']" :value="request('coin_watch', null)" col="2"/>
                        <x-select name="coin_plan" :options="[null => 'Select Plan', 'long' => 'Long', 'short' => 'Short']" :value="request('coin_watch', null)" col="2"/>
                        <x-select name="filter" :options="[null => 'All Filter', 'coin_code' => 'Code', 'coin_base' => 'Name']" :value="request('filter', 'All Filter')" col="3"/>
                        <x-input name="search" type="text" placeholder="Enter search term" :value="request('search')" col="4"/>
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
                                    <th>Code</th>
                                    <th>P&L</th>
                                    <th>Exchange</th>
                                    <th>Price USD</th>
                                    <th>Price IDR</th>
                                    <th><x-sort-link column="coin_plan" route="{{ module('getData') }}" text="Plan" /></th>
                                    <th>Entry USD</th>
                                    <th>Entry IDR</th>
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
                                        <td class="checkbox-column"><input type="checkbox" class="row-checkbox" value="{{ $item->id }}" /></td>
                                        <td data-label="Actions">
                                            <div class="action-table">
                                                <a href="{{ route(module('getUpdate'), $item) }}" class="button primary">
                                                    <i class="bi bi-magic"></i>
                                                </a>

                                                 <a href="{{ route(module('getWatch'), $item) }}" class="button success">
                                                    <i class="bi bi-search"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <td data-label="Code"><span class="{{ $item->coin_watch ? 'text-watch' : '' }}">{{ $item->coin_code }}</span></td>
                                        <td data-label="P&L">
                                            @php
                                                $entryUsd = $item->coin_entry_usd ?? 0;
                                                $priceUsd = $item->coin_price_usd ?? 0;
                                                $entryIdr = $item->coin_entry_idr ?? 0;
                                                $priceIdr = $item->coin_price_idr ?? 0;
                                            @endphp

                                            @if ($item->coin_plan == 'long' && $priceUsd > $entryUsd && $entryUsd > 0)
                                                @php
                                                    $usdProfit = $priceUsd - $entryUsd;
                                                    $usdPercentage = (($usdProfit) / $entryUsd) * 100;
                                                @endphp
                                                <div class="pnl-details">
                                                    <div class="pnl-line text-success">
                                                        <i class="bi bi-arrow-up-square-fill"></i>
                                                        Profit {{ number_format($usdPercentage, 2) }}% USD
                                                    </div>
                                                    <div class="pnl-amount">${{ number_format($usdProfit, 2) }}</div>
                                                </div>
                                            @elseif ($item->coin_plan == 'short' && $priceUsd < $entryUsd && $entryUsd > 0)
                                                @php
                                                    $usdProfit = $entryUsd - $priceUsd;
                                                    $usdPercentage = ($usdProfit / $entryUsd) * 100;
                                                @endphp
                                                <div class="pnl-details">
                                                    <div class="pnl-line text-success">
                                                        <i class="bi bi-arrow-up-square-fill"></i>
                                                        Profit {{ number_format($usdPercentage, 2) }}% USD
                                                    </div>
                                                    <div class="pnl-amount">${{ number_format($usdProfit, 2) }}</div>
                                                </div>
                                            @elseif ($item->coin_plan == 'long' && $entryUsd > 0)
                                                @php
                                                    $usdLoss = $entryUsd - $priceUsd;
                                                    $usdPercentage = ($usdLoss / $entryUsd) * 100;
                                                @endphp
                                                <div class="pnl-details">
                                                    <div class="pnl-line text-error">
                                                        <i class="bi bi-arrow-down-square-fill"></i>
                                                        Loss {{ number_format($usdPercentage, 2) }}% USD
                                                    </div>
                                                    <div class="pnl-amount">${{ number_format($usdLoss, 2) }}</div>
                                                </div>
                                            @elseif ($item->coin_plan == 'short' && $entryUsd > 0)
                                                @php
                                                    $usdLoss = $priceUsd - $entryUsd;
                                                    $usdPercentage = ($usdLoss / $entryUsd) * 100;
                                                @endphp
                                                <div class="pnl-details">
                                                    <div class="pnl-line text-error">
                                                        <i class="bi bi-arrow-down-square-fill"></i>
                                                        Loss {{ number_format($usdPercentage, 2) }}% USD
                                                    </div>
                                                    <div class="pnl-amount">${{ number_format($usdLoss, 2) }}</div>
                                                </div>
                                            @elseif ($item->coin_plan == 'long' && $priceIdr > $entryIdr && $entryIdr > 0)
                                                @php
                                                    $idrProfit = $priceIdr - $entryIdr;
                                                    $idrPercentage = ($idrProfit / $entryIdr) * 100;
                                                @endphp
                                                <div class="pnl-details">
                                                    <div class="pnl-line text-success">
                                                        <i class="bi bi-arrow-up-square-fill"></i>
                                                        Profit {{ number_format($idrPercentage, 2) }}% IDR
                                                    </div>
                                                    <div class="pnl-amount">Rp{{ number_format($idrProfit, 0) }}</div>
                                                </div>
                                            @elseif ($item->coin_plan == 'short' && $priceIdr < $entryIdr && $entryIdr > 0)
                                                @php
                                                    $idrProfit = $entryIdr - $priceIdr;
                                                    $idrPercentage = ($idrProfit / $entryIdr) * 100;
                                                @endphp
                                                <div class="pnl-details">
                                                    <div class="pnl-line text-success">
                                                        <i class="bi bi-arrow-up-square-fill"></i>
                                                        Profit {{ number_format($idrPercentage, 2) }}% IDR
                                                    </div>
                                                    <div class="pnl-amount">Rp{{ number_format($idrProfit, 0) }}</div>
                                                </div>
                                            @elseif ($item->coin_plan == 'long' && $entryIdr > 0)
                                                @php
                                                    $idrLoss = $entryIdr - $priceIdr;
                                                    $idrPercentage = ($idrLoss / $entryIdr) * 100;
                                                @endphp
                                                <div class="pnl-details">
                                                    <div class="pnl-line text-error">
                                                        <i class="bi bi-arrow-down-square-fill"></i>
                                                        Loss {{ number_format($idrPercentage, 2) }}% IDR
                                                    </div>
                                                    <div class="pnl-amount">Rp{{ number_format($idrLoss, 0) }}</div>
                                                </div>
                                            @elseif ($item->coin_plan == 'short' && $entryIdr > 0)
                                                @php
                                                    $idrLoss = $priceIdr - $entryIdr;
                                                    $idrPercentage = ($idrLoss / $entryIdr) * 100;
                                                @endphp
                                                <div class="pnl-details">
                                                    <div class="pnl-line text-error">
                                                        <i class="bi bi-arrow-down-square-fill"></i>
                                                        Loss {{ number_format($idrPercentage, 2) }}% IDR
                                                    </div>
                                                    <div class="pnl-amount">Rp{{ number_format($idrLoss, 0) }}</div>
                                                </div>
                                            @else
                                                <span class="text-light">-</span>
                                            @endif
                                        </td>
                                        <td data-label="Exchange">{{ $item->coin_exchange }}</td>
                                        <td data-label="Price USD">{{ $item->coin_price_usd }}</td>
                                        <td data-label="Price IDR">{{ number_format($item->coin_price_idr) }}</td>
                                        <td data-label="Plan">
                                            @if (!empty($item->coin_plan))
                                                @if ($item->coin_plan == 'long')
                                                    <span class="{{ $item->coin_plan == 'long' ? 'text-success' : '' }}">
                                                    <i class="bi bi-arrow-up-square-fill"></i>
                                                    <b>{{ $item->coin_plan }}</b>
                                                    </span>
                                                @else
                                                     <span class="{{ $item->coin_plan == 'long' ? 'text-error' : '' }}">
                                                    <i class="bi bi-arrow-down-square-fill"></i>
                                                    <b>{{ $item->coin_plan }}</b>
                                                @endif

                                            @endif
                                        </td>
                                        <td data-label="Entry USD">{{ $item->coin_entry_usd }}</td>
                                        <td data-label="Entry IDR">{{ number_format($item->coin_entry_idr) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No users found</td>
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
            <a href="{{ route(module('getCreate')) }}" class="button success">
                <i class="bi bi-plus"></i>Create
            </a>
        </x-footer>

        <form id="bulk-delete-form" method="POST" action="{{ route(module('postBulkDelete')) }}" style="display: none;">
            @csrf
            <input type="hidden" name="ids" id="bulk-delete-ids">
        </form>
    </div>

</x-layout>
