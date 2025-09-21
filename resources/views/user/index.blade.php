<x-template-layout title="Users">
    <div class="card">
        <div class="page-header">
            <h2>Users</h2>
        </div>
        <div class="card-table">
            <div class="form-table-container">
                <form class="form-table-filter">
                    <div class="row">
                        <div class="form-group col-4">
                            <label for="username" class="form-label">Username </label>
                            <input id="username" name="username" type="text" placeholder="Search by username" class="form-input" value="{{ request('username') }}" />
                        </div>
                        <div class="form-group col-4">
                            <label for="email" class="form-label">Email </label>
                            <input id="email" name="email" type="text" placeholder="Search by email" class="form-input" value="{{ request('email') }}" />
                        </div>
                        <div class="form-group col-4">
                            <label for="role" class="form-label">Role </label>
                            <div class="select-wrapper">
                                <div id="role" class="select-display form-select" tabindex="0">
                                    <span class="select-placeholder">Select role</span>
                                    <i class="select-arrow"></i>
                                </div>
                                <div class="select-dropdown" style="display: none">
                                    <div class="select-options">
                                        <div class="select-option selected">All Roles</div>
                                        <div class="select-option">Admin</div>
                                        <div class="select-option">User</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-2">
                            <label for="perpage" class="form-label">Per Page </label>
                            <div class="select-wrapper">
                                <div id="perpage" class="select-display form-select" tabindex="0">
                                    <span class="select-text">{{ request('perpage', 10) }}</span>
                                    <i class="select-arrow"></i>
                                </div>
                                <div class="select-dropdown" style="display: none">
                                    <div class="select-options">
                                        <div class="select-option {{ request('perpage') == 10 ? 'selected' : '' }}">10</div>
                                        <div class="select-option {{ request('perpage') == 20 ? 'selected' : '' }}">20</div>
                                        <div class="select-option {{ request('perpage') == 50 ? 'selected' : '' }}">50</div>
                                        <div class="select-option {{ request('perpage') == 100 ? 'selected' : '' }}">100</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-4">
                            <label for="filter" class="form-label">Filter </label>
                            <div class="select-wrapper">
                                <div id="filter" class="select-display form-select" tabindex="0">
                                    <span class="select-placeholder">Select filter</span>
                                    <i class="select-arrow"></i>
                                </div>
                                <div class="select-dropdown" style="display: none">
                                    <div class="select-options">
                                        <div class="select-option selected">All Filter</div>
                                        <div class="select-option">Username</div>
                                        <div class="select-option">Role</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-6">
                            <label for="search" class="form-label">Search </label>
                            <input id="search" name="search" type="text" placeholder="Enter search term" class="form-input" value="{{ request('search') }}" />
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="button button secondary">
                            <span class="">Reset</span>
                        </button>
                        <button type="submit" class="button button primary">
                            <span class="">Search</span>
                        </button>
                    </div>
                </form>
                <div class="form-table-content">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" /></th>
                                    <th class="text-center action-table">Actions</th>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data as $user)
                                <tr>
                                    <td><input type="checkbox" /></td>
                                    <td data-label="Actions">
                                        <div class="action-table">
                                            <a href="{{ route('user.getUpdate', $user->id) }}" class="button button primary">
                                                <i class="bi bi-pencil-square"></i>
                                                <span class=""></span>
                                            </a>
                                            <form method="POST" action="{{ route('user.postDelete', $user) }}" style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="button button danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                                    <i class="bi bi-trash"></i>
                                                    <span class=""></span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td data-label="ID">{{ $user->id }}</td>
                                    <td data-label="Username">{{ $user->username }}</td>
                                    <td data-label="Name">{{ $user->name }}</td>
                                    <td data-label="Email">{{ $user->email }}</td>
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
                        @if($data->hasPages())
                            @if($data->onFirstPage())
                                <button disabled="" class="button secondary">
                                    <i class="bi bi-arrow-left"></i>
                                </button>
                            @else
                                <a href="{{ $data->previousPageUrl() }}" class="button secondary">
                                    <i class="bi bi-arrow-left"></i>
                                </a>
                            @endif
                            <span class="pagination-info"> Page {{ $data->currentPage() }} of {{ $data->lastPage() }}</span>
                            @if($data->hasMorePages())
                                <a href="{{ $data->nextPageUrl() }}" class="button secondary">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            @else
                                <button disabled="" class="button secondary">
                                    <i class="bi bi-arrow-right"></i>
                                </button>
                            @endif
                        @endif
                    </nav>
                </div>
            </div>
        </div>
        <footer class="content-footer safe-area-bottom">
            <div class="form-actions">
                <button class="button button danger disabled" disabled="">
                    <span class=""><span>Delete</span></span>
                </button>
                <a href="{{ route('user.getCreate') }}" class="button button success">
                    <i class="bi bi-plus"></i>
                    <span class="">Create</span>
                </a>
            </div>
        </footer>
    </div>
</x-template-layout>