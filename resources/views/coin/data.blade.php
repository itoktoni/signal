<x-layout>
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
                        <x-select name="coin_watch" :options="[null => 'Select Watch', 1 => 'Watch']" :value="request('coin_watch', null)" col="3"/>
                        <x-select name="filter" :options="[null => 'All Filter', 'coin_code' => 'Code', 'coin_name' => 'Name']" :value="request('filter', 'All Filter')" col="3"/>
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
                                    <th>Symbol</th>
                                    <th>Name</th>
                                    <th>Price USD</th>
                                    <th>Price IDR</th>
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
                                        <td data-label="Symbol">{{ $item->coin_symbol }}</td>
                                        <td data-label="Name">{{ $item->coin_name }}</td>
                                        <td data-label="Price USD">{{ $item->coin_price_usd }}</td>
                                        <td data-label="Price IDR">{{ number_format($item->coin_price_idr) }}</td>
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
