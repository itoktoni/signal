<x-layout>
    <div class="card">
        <div class="page-header">
            <h2>Profile</h2>
        </div>
        <div class="form-container">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                <div class="card">
                    <div class="form-container">
                        @livewire('profile.update-profile-information-form')
                    </div>
                </div>
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="card">
                    <div class="form-container">
                        @livewire('profile.update-password-form')
                    </div>
                </div>
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="card">
                    <div class="form-container">
                        @livewire('profile.two-factor-authentication-form')
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="form-container">
                    @livewire('profile.logout-other-browser-sessions-form')
                </div>
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <div class="card">
                    <div class="form-container">
                        @livewire('profile.delete-user-form')
                    </div>
                </div>
            @endif
        </div>
</x-layout>
