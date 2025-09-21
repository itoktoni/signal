<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/safe-area.css') }}" />
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
                            <a href="{{ route('profile.show') }}" class="dropdown-item">Profile</a>
                            <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="dropdown-item" style="background: none; border: none; padding: 0; cursor: pointer; text-align: left; width: 100%;">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
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
                        A new user has been registered to the system.
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
                        Your monthly report has been generated.
                    </p>
                    <div class="notification-time">1 hour ago</div>
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

    @livewireScripts
    <script>
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
    </script>
</body>

</html>