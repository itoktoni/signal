import './bootstrap';
 import './bootstrap';

document.addEventListener('DOMContentLoaded', function() {
    // Checkbox functionality
    const checkAlls = document.querySelectorAll('.checkall');
    const checkboxes = document.querySelectorAll('.row-checkbox');

    checkAlls.forEach(checkAll => {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
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
        });
    });

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
});
