<x-layout>
    <div class="card">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Users</h2>
            <button type="button" id="toggle-filters" class="button secondary">
                <i class="bi bi-filter"></i> <span>Hide</span>
            </button>
        </div>
        <div class="card-table">
            <div class="form-table-container">
                <form id="filter-form" class="form-table-filter" method="GET" action="{{ route('user.getData') }}">
                    <div class="row">
                        <x-input name="username" type="text" placeholder="Search by username" :value="request('username')" col="4"/>
                        <x-input name="email" type="text" placeholder="Search by email" :value="request('email')" col="4"/>
                        <x-select name="role" :options="['All Roles' => 'All Roles', 'Admin' => 'Admin', 'User' => 'User']" :value="request('role', 'All Roles')" col="4"/>
                    </div>
                    <div class="row">
                        <x-select name="perpage" :options="['10' => '10', '20' => '20', '50' => '50', '100' => '100']" :value="request('perpage', 10)" col="2"/>
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
                                        <td class="checkbox-column"><input type="checkbox" class="row-checkbox" value="{{ $list->id }}" /></td>
                                        <td data-label="Actions">
                                            <div class="action-table">
                                                <a href="{{ route('user.getUpdate', $list) }}" class="button primary">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>

                                                <button type="button" class="button danger" onclick="confirmDelete('{{ route('user.getDelete', $list) }}', '{{ $list->name }}')">
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
        <x-footer>
            <button type="button" class="button danger" id="bulk-delete-btn" disabled onclick="confirmBulkDelete()">Delete</button>
            <a href="{{ route('user.getCreate') }}" class="button success">
                <i class="bi bi-plus"></i>Create
            </a>
        </x-footer>

        <form id="bulk-delete-form" method="POST" action="{{ route('user.postBulkDelete') }}" style="display: none;">
            @csrf
            <input type="hidden" name="ids" id="bulk-delete-ids">
        </form>
    </div>

    <script>
        function confirmDelete(url, name) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete user "${name}". This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        function confirmBulkDelete() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkedBoxes.length === 0) {
                Swal.fire('No Selection', 'Please select at least one user to delete.', 'warning');
                return;
            }

            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            const count = ids.length;

            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete ${count} user(s). This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete them!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulk-delete-ids').value = ids.join(',');
                    document.getElementById('bulk-delete-form').submit();
                }
            });
        }

        // Handle checkall functionality
        document.addEventListener('DOMContentLoaded', function() {
            const checkall = document.querySelector('.checkall');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

            checkall.addEventListener('change', function() {
                rowCheckboxes.forEach(cb => cb.checked = this.checked);
                updateBulkDeleteButton();
            });

            rowCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateBulkDeleteButton);
            });

            function updateBulkDeleteButton() {
                const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
                bulkDeleteBtn.disabled = checkedCount === 0;
                bulkDeleteBtn.classList.toggle('disabled', checkedCount === 0);
            }
        });
    </script>
</x-layout>
