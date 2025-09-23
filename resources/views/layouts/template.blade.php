<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>{{ $title ?? 'Users' }} - Obsesiman Report - Laravel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body>
    <div class="app-container is-full-screen">
        <!-- Sidebar Container for both columns -->
        <div id="sidebar-container" class="sidebar-container">
            <!-- Column 1: Main Sidebar -->
            <aside class="main-sidebar safe-area-drawer">
                <ul class="main-nav">
                    <li>
                        <a href="{{ route('dashboard') }}" class="nav-item" data-menu="home">
                            <i class="bi bi-house"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-item" data-menu="aplikasi">
                            <i class="bi bi-rocket"></i>
                            <span>Apps</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-item" data-menu="laporan">
                            <i class="bi bi-printer"></i>
                            <span>Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-item active" data-menu="system">
                            <i class="bi bi-wrench-adjustable-circle"></i>
                            <span>System</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-item" data-menu="test">
                            <i class="bi bi-bug"></i>
                            <span>Test</span>
                        </a>
                    </li>
                </ul>
            </aside>

            <!-- Column 2: Sub Menu -->
            <div id="sub-menu-container" class="sub-menu-container">
                <!-- Logo -->
                <a href="/" class="logo">
                    <h1>
                        <img src="{{ asset('images/logo.png') }}" alt="logo" style="max-height: 40px" />
                    </h1>
                </a>

                <!-- Home Sub Menu -->
                <div class="sub-menu" id="home-submenu" style="display: none">
                    <div class="sub-menu-list">
                        <a href="{{ route('dashboard') }}">
                            <span>Dashboard</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/analytics">
                            <span>Analytics</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/reports">
                            <span>Reports</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Apps Sub Menu -->
                <div class="sub-menu" id="aplikasi-submenu" style="display: none">
                    <div class="sub-menu-list">
                        <a href="/apps">
                            <span>App List</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/apps/new">
                            <span>New App</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/apps/settings">
                            <span>Settings</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Report Sub Menu -->
                <div class="sub-menu" id="laporan-submenu" style="display: none">
                    <div class="sub-menu-list">
                        <a href="/reports/sales">
                            <span>Sales Report</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/reports/users">
                            <span>User Report</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/reports/financials">
                            <span>Financials</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- System Sub Menu -->
                <div class="sub-menu" id="system-submenu" style="display: block">
                    <div class="sub-menu-list">
                        <a href="/system/groups">
                            <span>Group</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/roles">
                            <span>Roles</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/system/menu">
                            <span>Menu</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/system/links">
                            <span>Link</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/system/permissions">
                            <span>Permission</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/system/settings">
                            <span>Setting Website</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="{{ route('user.index') }}" class="active">
                            <span>User</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Test Sub Menu -->
                <div class="sub-menu" id="test-submenu" style="display: none">
                    <div class="sub-menu-list">
                        <a href="/test-safe-area">
                            <span>Safe Area Test</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/test-safe-area-page">
                            <span>Safe Area Page Test</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/test-form-select">
                            <span>FormSelect Test</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/test/capacitor-plugins">
                            <span>All Capacitor Plugins</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/test/plugin-tests">
                            <span>Plugins by Navigation</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/test/plugins-by-package">
                            <span>Plugins by Package</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/test/plugins/uhf-rfid-scanner">
                            <span>UHF RFID Scanner</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <main class="main-content">
            <header class="safe-area-header main-header">
                @section('header')
                    <div class="header-title-wrapper is-vertical-align">
                        <button id="mobile-menu-button" class="mobile-menu-button safe-area-left">
                            <i class="bi bi-sliders"></i>
                        </button>
                        <h1 class="header-title">{{ $title ?? 'ASSET MANAGEMENT' }}</h1>
                    </div>
                    <div class="user-profile is-vertical-align safe-area-right">
                        <div class="notification-icon" id="notification-icon">
                            <i class="bi bi-bell"></i><span class="notification-badge">2</span>
                        </div>
                        <div class="profile-icon" id="profile-icon">
                            <i class="bi bi-person-badge"></i>
                            <div class="profile-dropdown" id="profile-dropdown">
                                <a href="{{ route('profile') }}" class="dropdown-item">Profile</a>
                                <a href="{{ route('logout') }}" class="dropdown-item">Logout</a>
                            </div>
                        </div>
                    </div>
                @show
            </header>
            <div class="content-body">
                {{ $slot }}
            </div>
            <!-- Network Monitor - Only visible on mobile -->
        </main>
    </div>

    <!-- Notification Drawer -->
    <div class="notification-drawer" id="notification-drawer">
        <div class="notification-header safe-area-header">
            <h2 class="notification-title">Notifications</h2>
            <button class="notification-close">×</button>
        </div>
        <div class="notification-list is-paddingless">
            <div class="notification-item unread">
                <div class="notification-icon-small">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="notification-content">
                    <p class="notification-message">
                        A new user 'john_doe' has registered to the system.
                    </p>
                    <div class="notification-time">10 minutes ago</div>
                    <div class="notification-actions">
                        <button class="notification-action">Mark as read</button>
                        <button class="notification-action">Dismiss</button>
                    </div>
                </div>
            </div>
            <div class="notification-item unread">
                <div class="notification-icon-small">
                    <i class="bi bi-bell"></i>
                </div>
                <div class="notification-content">
                    <p class="notification-message">
                        Your monthly sales report has been successfully generated.
                    </p>
                    <div class="notification-time">1 hour ago</div>
                    <div class="notification-actions">
                        <button class="notification-action">Mark as read</button>
                        <button class="notification-action">Dismiss</button>
                    </div>
                </div>
            </div>
            <div class="notification-item">
                <div class="notification-icon-small">
                    <i class="bi bi-bell"></i>
                </div>
                <div class="notification-content">
                    <p class="notification-message">
                        A new system update is available. Please update at your
                        convenience.
                    </p>
                    <div class="notification-time">2 hours ago</div>
                    <div class="notification-actions">
                        <button class="notification-action">Mark as read</button>
                        <button class="notification-action">Dismiss</button>
                    </div>
                </div>
            </div>
            <div class="notification-item">
                <div class="notification-icon-small">
                    <i class="bi bi-bell"></i>
                </div>
                <div class="notification-content">
                    <p class="notification-message">
                        A payment of $250.00 has been received from Customer Inc.
                    </p>
                    <div class="notification-time">5 hours ago</div>
                    <div class="notification-actions">
                        <button class="notification-action">Mark as read</button>
                        <button class="notification-action">Dismiss</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="notification-footer safe-area-bottom">
            <button class="mark-all-read">Mark all as read</button>
        </div>
    </div>

    <div class="copyright">
        <p>&copy; Alphara</p>
    </div>

    <div id="overlay" class="overlay"></div>

    @if(session('success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            timer: false,
            showConfirmButton: true
        });
    </script>
    @endif

    @if(session('error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session('error') }}',
            timer: false,
            showConfirmButton: true
        });
    </script>
    @endif

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

        // Handle checkall functionality and custom selects
        document.addEventListener('DOMContentLoaded', function() {
            const checkall = document.querySelector('.checkall');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

            if (checkall) {
                checkall.addEventListener('change', function() {
                    rowCheckboxes.forEach(cb => cb.checked = this.checked);
                    updateBulkDeleteButton();
                });
            }

            rowCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateBulkDeleteButton);
            });

            function updateBulkDeleteButton() {
                if (bulkDeleteBtn) {
                    const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
                    bulkDeleteBtn.disabled = checkedCount === 0;
                    bulkDeleteBtn.classList.toggle('disabled', checkedCount === 0);
                }
            }

            // Custom Select Functionality
            document.querySelectorAll('.custom-select-wrapper').forEach(function(wrapper) {
                const input = wrapper.querySelector('.custom-select-input');
                const dropdown = wrapper.querySelector('.custom-select-dropdown');
                const searchInput = wrapper.querySelector('.custom-select-search');
                const options = wrapper.querySelectorAll('.custom-select-option');
                const hiddenContainer = wrapper.querySelector('.custom-select-hidden-inputs');
                const hiddenInput = wrapper.querySelector('input[type="hidden"]');
                const placeholder = wrapper.querySelector('.custom-select-placeholder');
                const isMultiple = wrapper.dataset.multiple === 'true';

                let selectedValues = [];
                let selectedTexts = [];

                // Initialize selected values
                options.forEach(option => {
                    if (option.dataset.selected === 'true') {
                        const value = option.dataset.value;
                        const text = option.textContent.trim();
                        selectedValues.push(value);
                        selectedTexts.push(text);
                        option.classList.add('selected');
                    }
                });

                updateDisplay();

                // Toggle dropdown
                input.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const isOpen = dropdown.style.display === 'block';
                    closeAllDropdowns();
                    if (!isOpen) {
                        dropdown.style.display = 'block';
                        input.classList.add('open');
                        if (searchInput) {
                            searchInput.value = '';
                            searchInput.focus();
                        }
                        // Show all options
                        options.forEach(option => option.style.display = 'block');
                    }
                });

                // Search functionality
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        options.forEach(option => {
                            const text = option.textContent.toLowerCase();
                            option.style.display = text.includes(searchTerm) ? 'block' : 'none';
                        });
                    });
                }

                // Option selection
                options.forEach(option => {
                    option.addEventListener('click', function() {
                        const value = this.dataset.value;
                        const text = this.textContent.trim();
                        if (value === '') return; // Don't select placeholder option

                        if (isMultiple) {
                            const index = selectedValues.indexOf(value);
                            if (index > -1) {
                                // Remove
                                selectedValues.splice(index, 1);
                                selectedTexts.splice(index, 1);
                                this.classList.remove('selected');
                            } else {
                                // Add
                                selectedValues.push(value);
                                selectedTexts.push(text);
                                this.classList.add('selected');
                            }
                        } else {
                            // Single select
                            selectedValues = [value];
                            selectedTexts = [text];
                            options.forEach(opt => opt.classList.remove('selected'));
                            this.classList.add('selected');
                            closeAllDropdowns();
                        }

                        updateDisplay();
                    });
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!wrapper.contains(e.target)) {
                        closeAllDropdowns();
                    }
                });

                function updateDisplay() {
                    const selectedItemsContainer = wrapper.querySelector('.custom-select-selected-items');
                    if (isMultiple) {
                        if (selectedTexts.length > 0) {
                            placeholder.textContent = `${selectedTexts.length} selected`;
                            placeholder.classList.remove('empty');
                            if (selectedItemsContainer) {
                                selectedItemsContainer.innerHTML = selectedTexts.map(text => `<span class="custom-select-tag">${text} <span class="custom-select-tag-remove" data-value="${selectedValues[selectedTexts.indexOf(text)]}">×</span></span>`).join('');
                            }
                        } else {
                            placeholder.textContent = wrapper.querySelector('.custom-select-placeholder').dataset.placeholder || 'Select options';
                            placeholder.classList.add('empty');
                            if (selectedItemsContainer) {
                                selectedItemsContainer.innerHTML = '';
                            }
                        }
                    } else {
                        placeholder.textContent = selectedTexts.length > 0 ? selectedTexts[0] : (wrapper.querySelector('.custom-select-placeholder').dataset.placeholder || 'Select an option');
                        placeholder.classList.toggle('empty', selectedTexts.length === 0);
                    }

                    if (isMultiple) {
                        if (hiddenContainer) {
                            hiddenContainer.innerHTML = '';
                            selectedValues.forEach(value => {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = hiddenContainer.dataset.name;
                                input.value = value;
                                hiddenContainer.appendChild(input);
                            });
                        }
                    } else {
                        if (hiddenInput) {
                            hiddenInput.value = selectedValues[0] || '';
                        }
                    }
                }

                // Handle tag removal for multiple select
                if (isMultiple) {
                    const selectedItemsContainer = wrapper.querySelector('.custom-select-selected-items');
                    if (selectedItemsContainer) {
                        selectedItemsContainer.addEventListener('click', function(e) {
                            if (e.target.classList.contains('custom-select-tag-remove')) {
                                e.stopPropagation();
                                const value = e.target.dataset.value;
                                const index = selectedValues.indexOf(value);
                                if (index > -1) {
                                    selectedValues.splice(index, 1);
                                    selectedTexts.splice(index, 1);
                                    wrapper.querySelector(`[data-value="${value}"]`).classList.remove('selected');
                                    updateDisplay();
                                }
                            }
                        });
                    }
                }
            });

            function closeAllDropdowns() {
                document.querySelectorAll('.custom-select-dropdown').forEach(d => {
                    d.style.display = 'none';
                    d.closest('.custom-select-wrapper').querySelector('.custom-select-input').classList.remove('open');
                });
            }
        });
    </script>

    @livewireScripts
</body>

</html>