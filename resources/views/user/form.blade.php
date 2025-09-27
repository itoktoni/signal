<x-layout>
    <x-card :model="$model">
        <x-form :model="$model">

            <x-input name="username" :value="$model ?? null" col="6" hint="Username cannot be changed" />
            <x-input name="name" :value="$model"/>
            <x-input type="email" name="email" :value="$model ?? null" col="12" />
            <x-input type="password" name="password" col="6" />
            <x-input type="password" name="password_confirmation" col="6" />
            <x-select name="role" :value="$model ?? null" col="6" :options="['' => 'Select Role', 'admin' => 'Admin', 'user' => 'User', 'manager' => 'Manager']" :required="!isset($model)" />

            <x-footer :model="$model" />

        </x-form>
    </x-card>
</x-layout>