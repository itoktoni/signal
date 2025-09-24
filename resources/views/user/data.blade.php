<x-layout>
    <div id="success-message" data-message="{{ session('success') }}" style="display: none;"></div>
    <div class="card">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Users</h2>
            <button type="button" id="toggle-filters" class="button secondary">
                <i class="bi bi-filter"></i> <span>Hide</span>
            </button>
        </div>
        <div class="card-table">
            <div class="form-table-container">
                <form id="filter-form" class="form-table-filter" method="GET" action="{{ route(module('getData')) }}">
                    <div class="row">
                        <x-input name="username" type="text" placeholder="Search by username" :value="request('username')" col="4"/>
                        <x-input name="email" type="text" placeholder="Search by email" :value="request('email')" col="4"/>
                        <x-select name="role" :options="['All Roles' => 'All Roles', 'Admin' => 'Admin', 'User' => 'User']" :value="request('role', 'All Roles')" col="4"/>
                    </div>
                    <div class="row">
                        <x-select name="perpage" :options="['10' => '10', '20' => '20', '50' => '50', '100' => '100']" :value="request('perpage', 10)" col="2" id="perpage-select"/>
                        <x-select name="filter" :options="['All Filter' => 'All Filter', 'Username' => 'Username', 'Role' => 'Role']" :value="request('filter', 'All Filter')" col="4"/>
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
                                    <th><x-sort-link column="id" route="{{ module('getData') }}" text="ID" /></th>
                                    <th><x-sort-link column="username" route="{{ module('getData') }}" text="Username" /></th>
                                    <th>Name</th>
                                    <th><x-sort-link column="email" route="{{ module('getData') }}" text="Email" /></th>
                                    <th><x-sort-link column="role" route="{{ module('getData') }}" text="Role" /></th>
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
                                            <div class="action-table">
                                                <a href="{{ route(module('getUpdate'), $list) }}" class="button primary">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>

                                                <button type="button" class="button danger" onclick="confirmDelete('{{ route(module('getDelete'), $list) }}', '{{ $list->name }}')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td data-label="ID">{{ $list->id }}</td>
                                        <td data-label="Username">{{ $list->name }}</td>
                                        <td data-label="Name">{{ $list->name }}</td>
                                        <td data-label="Email">{{ $list->email }}</td>
                                        <td data-label="Role Name">Admin</td>
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
