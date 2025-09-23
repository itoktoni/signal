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