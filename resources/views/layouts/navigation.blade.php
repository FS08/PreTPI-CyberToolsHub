<nav class="bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14 items-center">
            
            <!-- Left: Logo + Nav Links -->
            <div class="flex items-center space-x-6">
                {{-- Logo (shield only) --}}
                <a href="{{ route('home') }}" class="flex items-center">
                    <img src="{{ asset('cth_shield_only2.png') }}" alt="CTH Logo"
                         class="h-5 w-5ß rounded-md">
                </a>

                {{-- Nav Links --}}
                <x-nav-link :href="route('home')" :active="request()->routeIs('home')">
                    Home
                </x-nav-link>
                <x-nav-link :href="route('about')" :active="request()->routeIs('about')">
                    About
                </x-nav-link>
                @auth
                    <x-nav-link :href="route('scan.create')" :active="request()->routeIs('scan.create')">
                        Scan
                    </x-nav-link>
                    <x-nav-link :href="route('scan.history')" :active="request()->routeIs('scan.history')">
                        History
                    </x-nav-link>
                    <x-nav-link :href="route('stats')" :active="request()->routeIs('stats')">
                        Stats
                    </x-nav-link>
                @endauth
            </div>

            <!-- Middle spacer -->
            <div class="flex-1"></div>

            <!-- Right: Logout -->
            <div>
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white underline">
                            Logout
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </div>
</nav>