<x-layout>
    <div id="success-message" data-message="{{ session('success') }}" style="display: none;"></div>
    <x-card>
        <div class="card-table">
            <div class="form-table-container">
                <form id="filter-form" class="form-table-filter" method="GET" action="{{ route(module('getData')) }}">
                    <div class="row">
                        <x-input name="menu_code" type="text" placeholder="Search by code" :value="request('menu_code')" col="4"/>
                        <x-input name="menu_name" type="text" placeholder="Search by name" :value="request('menu_name')" col="4"/>
                    </div>
                    <div class="row">
                        <x-select name="perpage" :options="['10' => '10', '20' => '20', '50' => '50', '100' => '100']" :value="request('perpage', 10)" col="2" id="perpage-select"/>
                        <x-select name="filter" :options="['All Filter' => 'All Filter', 'menu_code' => 'Code', 'name' => 'menu_name']" :value="request('filter', 'All Filter')" col="4"/>
                        <x-input name="search" type="text" placeholder="Enter search term" :value="request('search')" col="6"/>
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
                                    <x-th column="menu_code" text="Menu Code" :model="$data->first()" />
                                    <x-th column="menu_name" text="Menu Name" :model="$data->first()" />
                                    <x-th column="menu_group" text="Group" :model="$data->first()" />
                                    <x-th column="menu_controller" text="Controller" :model="$data->first()" />
                                    <x-th column="menu_action" text="Action" :model="$data->first()" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="hide-lg">
                                    <td data-label="Check All Data" class="checkbox-column">
                                        <input type="checkbox" class="checkall" />
                                    </td>
                                </tr>
                                @forelse($data as $list)
                                    <tr>
                                        <td class="checkbox-column"><input type="checkbox" class="row-checkbox" value="{{ $list->id }}" /></td>
                                        <td data-label="Actions">
                                            <x-action-table :model="$list" />
                                        </td>
                                        <x-td field="menu_code" :model="$list" />
                                        <x-td field="menu_name" :model="$list" />
                                        <x-td field="menu_group" :model="$list" />
                                        <x-td field="menu_controller" :model="$list" />
                                        <x-td field="menu_action" :model="$list" />
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No menus found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <x-pagination :data="$data" />
            </div>
        </div>
        <x-footer type="list" />

        <form id="bulk-delete-form" method="POST" action="{{ route(module('postBulkDelete')) }}" style="display: none;">
            @csrf
            <input type="hidden" name="ids" id="bulk-delete-ids">
        </form>
    </x-card>

</x-layout>
