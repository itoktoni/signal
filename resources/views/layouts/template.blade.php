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
        @include('layouts.sidebar')

        <main class="main-content">
            @include('layouts.header')
            <div class="content-body">
                {{ $slot }}
            </div>
            <!-- Network Monitor - Only visible on mobile -->
        </main>
    </div>

   @include('layouts/notification')

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