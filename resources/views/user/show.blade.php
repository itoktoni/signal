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
                    <a href="{{ route('profile.show') }}" class="dropdown-item">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="dropdown-item"
                            style="background: none; border: none; padding: 0; cursor: pointer; text-align: left; width: 100%;">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    @endsection

    <div class="card">
        <div class="page-header">
            <h2>User Details</h2>
        </div>
        <div class="card-table">
            <div class="form-group">
                <strong>ID:</strong> {{ $user->id }}
            </div>
            <div class="form-group">
                <strong>Name:</strong> {{ $user->name }}
            </div>
            <div class="form-group">
                <strong>Email:</strong> {{ $user->email }}
            </div>
            <div class="form-group">
                <strong>Email Verified:</strong> {{ $user->email_verified_at ? 'Yes' : 'No' }}
            </div>
            <div class="form-group">
                <strong>Created At:</strong> {{ $user->created_at->format('Y-m-d H:i:s') }}
            </div>
            <div class="form-group">
                <strong>Updated At:</strong> {{ $user->updated_at->format('Y-m-d H:i:s') }}
            </div>

            <footer class="content-footer safe-area-bottom">
            <div class="form-actions">
                <a href="{{ route('users.edit', $user) }}" class="button primary">Edit User</a>
                <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline"
                    onsubmit="return confirm('Are you sure you want to delete this user?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button danger">Delete User</button>
                </form>
            </div>
            </footer>
        </div>
    </div>
</x-template-layout>
