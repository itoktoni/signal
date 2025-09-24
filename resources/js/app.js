import './bootstrap';
import Swal from 'sweetalert2';

// Global functions for user data page
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

// Make functions global
window.confirmDelete = confirmDelete;
window.confirmBulkDelete = confirmBulkDelete;

document.addEventListener('DOMContentLoaded', function() {
    // Show success message if exists
    const successElement = document.getElementById('success-message');
    if (successElement && successElement.dataset.message) {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: successElement.dataset.message,
            timer: 2000,
            showConfirmButton: false
        });
    }

    // Checkbox functionality
    const checkAlls = document.querySelectorAll('.checkall');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    checkAlls.forEach(checkAll => {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkDeleteButton();
        });
    });

    // Update check all state based on individual checkboxes
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            const someChecked = Array.from(checkboxes).some(c => c.checked);
            checkAlls.forEach(checkAll => {
                checkAll.checked = allChecked;
                checkAll.indeterminate = someChecked && !allChecked;
            });
            updateBulkDeleteButton();
        });
    });

    function updateBulkDeleteButton() {
        if (bulkDeleteBtn) {
            const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
            bulkDeleteBtn.disabled = checkedCount === 0;
            bulkDeleteBtn.classList.toggle('disabled', checkedCount === 0);
        }
    }

    // Sidebar and menu functionality
    let sidebarOpen = false;
    let selectedMenu = "system";

    // Menu navigation
    document.querySelectorAll(".nav-item").forEach((item) => {
        item.addEventListener("click", function(e) {
            e.preventDefault();
            const menuId = this.getAttribute("data-menu");
            selectedMenu = menuId;

            // Update active class
            document
                .querySelectorAll(".nav-item")
                .forEach((nav) => nav.classList.remove("active"));
            this.classList.add("active");

            // Hide all sub-menus
            document
                .querySelectorAll(".sub-menu")
                .forEach((sub) => (sub.style.display = "none"));

            // Show selected sub-menu
            const subMenu = document.getElementById(menuId + "-submenu");
            if (subMenu) {
                subMenu.style.display = "block";
            }
        });
    });

    // Mobile menu toggle
    document
        .getElementById("mobile-menu-button")
        .addEventListener("click", function() {
            sidebarOpen = !sidebarOpen;
            const sidebar = document.getElementById("sidebar-container");
            sidebar.classList.toggle("open", sidebarOpen);
            document
                .getElementById("overlay")
                .classList.toggle("active", sidebarOpen);
        });

    // Overlay click to close
    document.getElementById("overlay").addEventListener("click", function() {
        sidebarOpen = false;
        document.getElementById("sidebar-container").classList.remove("open");
        this.classList.remove("active");
    });

    // Profile dropdown toggle
    document
        .getElementById("profile-icon")
        .addEventListener("click", function() {
            const dropdown = document.getElementById("profile-dropdown");
            dropdown.classList.toggle("active");
            document
                .getElementById("notification-drawer")
                .classList.remove("open");
        });

    // Notification toggle
    document
        .getElementById("notification-icon")
        .addEventListener("click", function() {
            const drawer = document.getElementById("notification-drawer");
            drawer.classList.toggle("open");
            document
                .getElementById("profile-dropdown")
                .classList.remove("active");
        });

    // Close notification drawer
    document
        .querySelector(".notification-close")
        .addEventListener("click", function() {
            document
                .getElementById("notification-drawer")
                .classList.remove("open");
        });

    // Close on outside click
    document.addEventListener("click", function(e) {
        if (!e.target.closest(".profile-icon")) {
            document
                .getElementById("profile-dropdown")
                .classList.remove("active");
        }
        if (
            !e.target.closest(".notification-icon") &&
            !e.target.closest(".notification-drawer")
        ) {
            document
                .getElementById("notification-drawer")
                .classList.remove("open");
        }
    });

    // Initialize active menu
    document
        .querySelector(`[data-menu="${selectedMenu}"]`)
        .classList.add("active");
    document.getElementById(selectedMenu + "-submenu").style.display =
        "block";

    // Filter toggle functionality
    let filtersVisible = true;
    const toggleButton = document.getElementById('toggle-filters');
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            filtersVisible = !filtersVisible;
            const form = document.getElementById('filter-form');
            const buttonText = this.querySelector('span');
            if (filtersVisible) {
                form.style.display = 'block';
                buttonText.textContent = 'Hide';
            } else {
                form.style.display = 'none';
                buttonText.textContent = 'Show';
            }
        });
    }

    // Handle perpage change
    const perpageSelect = document.getElementById('perpage-select');
    if (perpageSelect) {
        perpageSelect.addEventListener('change', function() {
            const url = new URL(window.location);
            url.searchParams.set('perpage', this.value);
            window.location.href = url.toString();
        });
    }

    // Modern Select Functionality
    document.querySelectorAll('.modern-select-wrapper').forEach(function(wrapper) {
        const container = wrapper.querySelector('.modern-select-container');
        const display = wrapper.querySelector('.modern-select-display');
        const dropdown = wrapper.querySelector('.modern-select-dropdown');
        const searchInput = wrapper.querySelector('.modern-select-search');
        const options = wrapper.querySelectorAll('.modern-select-option');
        const hiddenContainer = wrapper.querySelector('.modern-select-hidden-inputs');
        const hiddenInput = wrapper.querySelector('input[type="hidden"]');
        const displayText = wrapper.querySelector('.modern-select-text');
        const arrow = wrapper.querySelector('.modern-select-arrow');
        const tagsContainer = wrapper.querySelector('.modern-select-tags');
        const isMultiple = wrapper.dataset.multiple === 'true';
        const defaultPlaceholder = displayText.dataset.placeholder || 'Select an option';

        let selectedValues = [];
        let selectedTexts = [];

        // Initialize selected values
        console.log('🔍 Modern Select Debug - Initializing select:', {
            wrapper: wrapper,
            optionsCount: options.length,
            isMultiple: isMultiple,
            hiddenInput: hiddenInput,
            hiddenContainer: hiddenContainer,
            options: Array.from(options).map(opt => ({
                value: opt.dataset.value,
                selected: opt.classList.contains('selected'),
                text: opt.querySelector('.modern-select-option-text')?.textContent.trim()
            }))
        });

        // Check for pre-selected values from hidden inputs or HTML
        if (isMultiple) {
            if (hiddenContainer) {
                const existingInputs = hiddenContainer.querySelectorAll('input[type="hidden"]');
                existingInputs.forEach(input => {
                    selectedValues.push(input.value);
                    // Find the corresponding option text
                    const option = wrapper.querySelector(`[data-value="${input.value}"]`);
                    if (option) {
                        const text = option.querySelector('.modern-select-option-text').textContent.trim();
                        selectedTexts.push(text);
                        option.classList.add('selected');
                        const checkIcon = option.querySelector('.modern-select-check-icon');
                        if (checkIcon) checkIcon.style.display = 'block';
                    }
                });
            }
        } else {
            if (hiddenInput && hiddenInput.value) {
                selectedValues.push(hiddenInput.value);
                const option = wrapper.querySelector(`[data-value="${hiddenInput.value}"]`);
                if (option) {
                    const text = option.querySelector('.modern-select-option-text').textContent.trim();
                    selectedTexts.push(text);
                    option.classList.add('selected');
                    const checkIcon = option.querySelector('.modern-select-check-icon');
                    if (checkIcon) checkIcon.style.display = 'block';
                }
            } else {
                // Check HTML selected options
                options.forEach(option => {
                    if (option.classList.contains('selected')) {
                        const value = option.dataset.value;
                        const text = option.querySelector('.modern-select-option-text').textContent.trim();
                        selectedValues.push(value);
                        selectedTexts.push(text);
                        console.log('✅ Found selected option:', { value, text });
                    }
                });
            }
        }

        console.log('📊 Final selected values:', { selectedValues, selectedTexts });

        updateDisplay();

        // Toggle dropdown
        display.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = dropdown.style.display === 'block';
            closeAllDropdowns();
            if (!isOpen) {
                dropdown.style.display = 'block';
                container.classList.add('open');
                arrow.classList.add('rotated');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                // Show all options
                options.forEach(option => option.style.display = 'flex');
            } else {
                dropdown.style.display = 'none';
                container.classList.remove('open');
                arrow.classList.remove('rotated');
            }
        });

        // Search functionality
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                options.forEach(option => {
                    const text = option.querySelector('.modern-select-option-text').textContent.toLowerCase();
                    option.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                });
            });
        }

        // Option selection
        options.forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const value = this.dataset.value;
                const text = this.querySelector('.modern-select-option-text').textContent.trim();
                if (value === '' || value === undefined) return; // Don't select placeholder option

                console.log('🔘 Option clicked:', { value, text, isMultiple });

                if (isMultiple) {
                    const index = selectedValues.indexOf(value);
                    if (index > -1) {
                        // Remove
                        selectedValues.splice(index, 1);
                        selectedTexts.splice(index, 1);
                        this.classList.remove('selected');
                        const checkIcon = this.querySelector('.modern-select-check-icon');
                        if (checkIcon) checkIcon.style.display = 'none';
                    } else {
                        // Add
                        selectedValues.push(value);
                        selectedTexts.push(text);
                        this.classList.add('selected');
                        const checkIcon = this.querySelector('.modern-select-check-icon');
                        if (checkIcon) checkIcon.style.display = 'block';
                    }
                } else {
                    // Single select
                    selectedValues = [value];
                    selectedTexts = [text];

                    // Remove selected class from all options
                    options.forEach(opt => {
                        opt.classList.remove('selected');
                        const checkIcon = opt.querySelector('.modern-select-check-icon');
                        if (checkIcon) checkIcon.style.display = 'none';
                    });

                    // Add selected class to clicked option
                    this.classList.add('selected');
                    const checkIcon = this.querySelector('.modern-select-check-icon');
                    if (checkIcon) checkIcon.style.display = 'block';

                    // Close dropdown after selection
                    setTimeout(() => {
                        closeAllDropdowns();
                    }, 100);
                }

                updateDisplay();
                console.log('✅ Selection updated:', { selectedValues, selectedTexts });
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                closeAllDropdowns();
            }
        });

        // Handle tag removal for multiple select
        if (isMultiple && tagsContainer) {
            tagsContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('modern-select-tag-remove')) {
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

        function updateDisplay() {
            console.log('🔄 updateDisplay called:', {
                selectedValues,
                selectedTexts,
                isMultiple,
                displayText: displayText,
                defaultPlaceholder,
                hiddenInput: hiddenInput,
                hiddenContainer: hiddenContainer
            });

            if (isMultiple) {
                if (selectedTexts.length > 0) {
                    displayText.textContent = `${selectedTexts.length} selected`;
                    displayText.classList.remove('empty');
                    displayText.classList.add('has-value');
                    if (tagsContainer) {
                        tagsContainer.innerHTML = selectedTexts.map(text => `<span class="modern-select-tag">${text} <span class="modern-select-tag-remove" data-value="${selectedValues[selectedTexts.indexOf(text)]}">×</span></span>`).join('');
                    }
                } else {
                    displayText.textContent = defaultPlaceholder;
                    displayText.classList.add('empty');
                    displayText.classList.remove('has-value');
                    if (tagsContainer) {
                        tagsContainer.innerHTML = '';
                    }
                }
            } else {
                displayText.textContent = selectedTexts.length > 0 ? selectedTexts[0] : defaultPlaceholder;
                displayText.classList.toggle('empty', selectedTexts.length === 0);
                displayText.classList.toggle('has-value', selectedTexts.length > 0);
                arrow.classList.toggle('rotated', selectedTexts.length > 0);
            }

            // Update hidden inputs
            if (isMultiple) {
                if (hiddenContainer) {
                    hiddenContainer.innerHTML = '';
                    selectedValues.forEach(value => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = hiddenContainer.dataset.name || `${wrapper.querySelector('input[type="hidden"]')?.name || name}[]`;
                        input.value = value;
                        hiddenContainer.appendChild(input);
                        console.log('➕ Added hidden input:', { name: input.name, value: input.value });
                    });
                }
            } else {
                if (hiddenInput) {
                    hiddenInput.value = selectedValues[0] || '';
                    console.log('📝 Updated hidden input:', { name: hiddenInput.name, value: hiddenInput.value });
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
        document.querySelectorAll('.modern-select-dropdown').forEach(d => {
            d.style.display = 'none';
            d.closest('.modern-select-wrapper').querySelector('.modern-select-container').classList.remove('open');
            d.closest('.modern-select-wrapper').querySelector('.modern-select-arrow').classList.remove('rotated');
        });
    }
});
