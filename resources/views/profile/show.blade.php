<x-template-layout title="Profile">
    <div class="profile-content">
        @if (Laravel\Fortify\Features::canUpdateProfileInformation())
            <div class="card">
                <div class="card-header">
                    <h3>Profile Information</h3>
                    <p>Update your account's profile information and email address.</p>
                </div>
                @livewire('profile.update-profile-information-form')
            </div>
        @endif

        @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
            <div class="card">
                <div class="card-header">
                    <h3>Update Password</h3>
                    <p>Ensure your account is using a long, random password to stay secure.</p>
                </div>
                @livewire('profile.update-password-form')
            </div>
        @endif

        @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
            <div class="card">
                <div class="card-header">
                    <h3>Two Factor Authentication</h3>
                    <p>Add additional security to your account using two factor authentication.</p>
                </div>
                @livewire('profile.two-factor-authentication-form')
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3>Browser Sessions</h3>
                <p>Manage and log out your active sessions on other browsers and devices.</p>
            </div>
            @livewire('profile.logout-other-browser-sessions-form')
        </div>

        @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
            <div class="card">
                <div class="card-header">
                    <h3>Delete Account</h3>
                    <p>Permanently delete your account.</p>
                </div>
                @livewire('profile.delete-user-form')
            </div>
        @endif
    </div>

    <style>
        .profile-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 24px;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .card-header p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .card > div:not(.card-header) {
            padding: 24px;
        }
    </style>
</x-template-layout>
