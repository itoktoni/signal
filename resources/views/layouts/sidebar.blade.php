<div id="sidebar-container" class="sidebar-container">
    <!-- Column 1: Main Sidebar -->
    @php
        $seg1 = request()->segment(1);
        $seg2 = request()->segment(2);
        $home = in_array($seg1, ['dashboard', 'profile']);
    @endphp
    <aside class="main-sidebar safe-area-drawer">
        <ul class="main-nav">
            <li>
                <a href="{{ route('dashboard') }}" class="nav-item {{ $home ? 'active' : '' }}" data-menu="dashboard">
                    <i class="bi bi-house-down"></i>
                    <span>Home</span>
                </a>
            </li>
            @foreach ($context['group'] as $group)
                <li>
                    <a href="#" class="nav-item {{ $group->field_key === $seg1 ? 'active' : '' }}"
                        data-menu="{{ $group->field_key }}">
                        <i class="bi {{ $group->group_icon }}"></i>
                        <span>{{ $group->field_name }}</span>
                    </a>
                </li>
            @endforeach
            <li>
                <a href="{{ route('logout') }}" class="nav-item">
                    <i class="bi bi-person-x"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Column 2: Sub Menu -->
    <div id="sub-menu-container" class="sub-menu-container">
        <!-- Logo -->
        <div class="logo-container">
            <a href="/" class="logo">
                <h1>
                    <img src="{{ asset('images/logo.png') }}" alt="logo" />
                </h1>
            </a>
        </div>
        @foreach ($context['group'] as $group)
            <div class="sub-menu" id="{{ $group->field_key }}-submenu"
                style="display: {{ $group->field_key === $seg1 ? 'block' : 'none' }}">
                <div class="sub-menu-list">
                    @foreach ($context['menu']->where('menu_group', $group->field_key) as $menu)
                        <a href="{{ route($menu->field_key . '.index') }}"
                            class="{{ $menu->field_key === $seg2 ? 'active' : '' }}">
                            <span>{{ $menu->field_name }}</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($home)
        <div class="sub-menu" id="dashboard-submenu" style="display: block">
            <div class="sub-menu-list">
                <a href="{{ route('dashboard') }}"
                    class="{{ $seg1 === 'dashboard' ? 'active' : '' }}">
                    <span>Dashboard</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
                <a href="{{ route('profile') }}" class="{{ $seg1 === 'profile' ? 'active' : '' }}">
                    <span>Profile</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        @endif

    </div>
</div>
