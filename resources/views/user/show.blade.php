<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('User Details') }}
            </h2>
            <a href="{{ route('user.index]') }}" class="text-gray-500">Back to Users</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-4">
                        <strong>ID:</strong> {{ $user->id }}
                    </div>
                    <div class="mb-4">
                        <strong>Name:</strong> {{ $user->name }}
                    </div>
                    <div class="mb-4">
                        <strong>Email:</strong> {{ $user->email }}
                    </div>
                    <div class="mb-4">
                        <strong>Email Verified:</strong> {{ $user->email_verified_at ? 'Yes' : 'No' }}
                    </div>
                    <div class="mb-4">
                        <strong>Created At:</strong> {{ $user->created_at->format('Y-m-d H:i:s') }}
                    </div>
                    <div class="mb-4">
                        <strong>Updated At:</strong> {{ $user->updated_at->format('Y-m-d H:i:s') }}
                    </div>

                    <div class="mt-6">
                        <a href="{{ route('users.edit', $user) }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded mr-2">
                            Edit User
                        </a>
                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Delete User
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>