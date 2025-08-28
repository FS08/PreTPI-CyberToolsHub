<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white">
            <span class="text-indigo-600 dark:text-indigo-400">{{ __('Profile') }}</span>
        </h2>
    </x-slot>

    <div class="py-12 bg-gradient-to-b from-indigo-50 to-white dark:from-gray-900 dark:to-gray-800">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
