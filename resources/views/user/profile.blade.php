<x-layout>
    <div class="card">
        <div class="page-header">
            <h2>User Security</h2>
        </div>
        <div class="form-container">

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="form-container">
                    @livewire('profile.two-factor-authentication-form')
                </div>
            @endif

            <div class="form-container">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <div class="form-container">
                    @livewire('profile.delete-user-form')
                </div>
            @endif
        </div>
    </div>
</x-layout>
