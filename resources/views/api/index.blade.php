<x-template-layout>
    @section('header')
        <div class="header-title-wrapper is-vertical-align">
            <button id="mobile-menu-button" class="mobile-menu-button safe-area-left">
                <i class="bi bi-sliders"></i>
            </button>
            <h1 class="header-title">ASSET MANAGEMENT</h1>
        </div>
        <div class="user-profile is-vertical-align safe-area-right">
            <div class="notification-icon" id="notification-icon">
                <i class="bi bi-bell"></i><span class="notification-badge">2</span>
            </div>
            <div class="profile-icon" id="profile-icon">
                <i class="bi bi-person-badge"></i>
                <div class="profile-dropdown" id="profile-dropdown">
                    <a href="{{ route('profile') }}" class="dropdown-item">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="dropdown-item"
                            style="background: none; border: none; padding: 0; cursor: pointer; text-align: left; width: 100%;">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    @endsection

    <div class="content-body">
        <div class="card">
            <div class="page-header">
                <h2>API Tokens</h2>
            </div>
            <div class="card-table">
                @livewire('api.api-token-manager')
            </div>
        </div>
    </div>
</x-template-layout>
