 <!-- Sidebar Container for both columns -->
        <div id="sidebar-container" class="sidebar-container">
            <!-- Column 1: Main Sidebar -->
            <aside class="main-sidebar safe-area-drawer">
                <ul class="main-nav">
                    @php
                        $currentRoute = Route::currentRouteName();
                        $activeGroup = null;
                        foreach(config('sidebar.groups') as $groupKey => $group) {
                            foreach($group['menus'] as $menuKey => $menu) {
                                if($menu['route'] === $currentRoute) {
                                    $activeGroup = $groupKey;
                                    $activeMenu = $menuKey;
                                    break 2;
                                }
                            }
                        }
                        if(!$activeGroup) {
                            $activeGroup = 'system'; // default
                        }
                    @endphp
                    @foreach(config('sidebar.groups') as $groupKey => $group)
                        <li>
                            <a href="#" class="nav-item {{ $groupKey === $activeGroup ? 'active' : '' }}" data-menu="{{ $groupKey }}">
                                <i class="bi {{ $group['icon'] }}"></i>
                                <span>{{ $group['name'] }}</span>
                            </a>
                        </li>
                    @endforeach
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

                @foreach(config('sidebar.groups') as $groupKey => $group)
                    <div class="sub-menu" id="{{ $groupKey }}-submenu" style="display: {{ $groupKey === $activeGroup ? 'block' : 'none' }}">
                        <div class="sub-menu-list">
                            @foreach($group['menus'] as $menuKey => $menu)
                                <a href="{{ route($menu['route']) }}" class="{{ $menuKey === ($activeMenu ?? 'users') ? 'active' : '' }}">
                                    <span>{{ $menu['name'] }}</span>
                                    <i class="bi {{ $menu['icon'] }}"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>