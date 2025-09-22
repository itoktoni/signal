<x-layout>
    <div class="card">
        <div class="page-header">
            <h2>Users</h2>
        </div>
        <div class="card-table">
            <div class="form-table-container">
                <form class="form-table-filter" method="GET" action="{{ route('user.getData') }}">
                    <div class="row">
                        <div class="form-group col-4">
                            <label for="username" class="form-label">Username </label><input id="username"
                                name="username" type="text" placeholder="Search by username" class="form-input"
                                value="{{ request('username') }}" />
                        </div>
                        <div class="form-group col-4">
                            <label for="email" class="form-label">Email </label><input id="email" name="email"
                                type="text" placeholder="Search by email" class="form-input"
                                value="{{ request('email') }}" />
                        </div>
                        <div class="form-group col-4">
                            <label for="role" class="form-label">Role </label>
                            <select name="role" class="form-select">
                                <option value="All Roles" {{ request('role', 'All Roles') == 'All Roles' ? 'selected' : '' }}>All Roles</option>
                                <option value="Admin" {{ request('role') == 'Admin' ? 'selected' : '' }}>Admin</option>
                                <option value="User" {{ request('role') == 'User' ? 'selected' : '' }}>User</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-2">
                            <label for="perpage" class="form-label">Per Page </label>
                            <select name="perpage" class="form-select">
                                <option value="10" {{ request('perpage', 10) == 10 ? 'selected' : '' }}>10</option>
                                <option value="20" {{ request('perpage') == 20 ? 'selected' : '' }}>20</option>
                                <option value="50" {{ request('perpage') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('perpage') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label for="filter" class="form-label">Filter </label>
                            <select name="filter" class="form-select">
                                <option value="All Filter" {{ request('filter', 'All Filter') == 'All Filter' ? 'selected' : '' }}>All Filter</option>
                                <option value="Username" {{ request('filter') == 'Username' ? 'selected' : '' }}>Username</option>
                                <option value="Role" {{ request('filter') == 'Role' ? 'selected' : '' }}>Role</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label for="search" class="form-label">Search </label><input id="search"
                                name="search" type="text" placeholder="Enter search term" class="form-input"
                                value="{{ request('search') }}" />
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="button button secondary"
                            onclick="window.location.href='{{ url()->current() }}'">
                            <span class="">Reset</span></button><button type="submit"
                            class="button button primary">
                            <span class="">Search</span>
                        </button>
                    </div>
                </form>
                <div class="form-table-content">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-column"><input type="checkbox" class="checkall" /></th>
                                    <th class="text-center action-table">Actions</th>
                                    <th><a href="{{ sortUrl('id', 'user.getData') }}">ID</a></th>
                                    <th><a href="{{ sortUrl('username', 'user.getData') }}">Username</a></th>
                                    <th>Name</th>
                                    <th><a href="{{ sortUrl('email', 'user.getData') }}">Email</a></th>
                                    <th><a href="{{ sortUrl('role', 'user.getData') }}">Role</a></th>
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
                                        <td class="checkbox-column"><input type="checkbox" class="row-checkbox" /></td>
                                        <td data-label="Actions">
                                            <div class="action-table">
                                                <a href="{{ route('user.getUpdate', $list) }}" class="button primary">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>

                                                <a href="{{ route('user.getDelete', $list) }}" class="button danger">
                                                    <i class="bi bi-trash"></i>
                                                </a>
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
                <div class="form-table-pagination">
                    <nav class="pagination">
                        @if ($data->onFirstPage())
                            <button disabled="" class="button secondary">
                                <i class="bi bi-arrow-left"></i></button>
                        @else
                            <a href="{{ $data->previousPageUrl() }}" class="button secondary">
                                <i class="bi bi-arrow-left"></i></a>
                        @endif
                        <span class="pagination-info"> Page {{ $data->currentPage() }} of
                            {{ $data->lastPage() }}</span>
                        @if ($data->hasMorePages())
                            <a href="{{ $data->nextPageUrl() }}" class="button secondary">
                                <i class="bi bi-arrow-right"></i></a>
                        @else
                            <button disabled="" class="button secondary">
                                <i class="bi bi-arrow-right"></i>
                        @endif
                    </nav>
                </div>
            </div>
        </div>
        <footer class="content-footer safe-area-bottom">
            <div class="form-actions">
                <!-- Delete/Remove type --><button class="button button danger disabled" disabled="">
                    <span class=""><span>Delete</span></span></button><a href="{{ route('user.getCreate') }}"
                    class="button button success">
                    <i class="bi bi-plus"></i><span class="">Create</span>
                </a>
            </div>
        </footer>
    </div>
</x-layout>
