<nav class="bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-gray-700">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-14 items-center">

      {{-- Left: Logo + Nav Links --}}
      <div class="flex items-center space-x-6">
        <a href="{{ route('home') }}" class="flex items-center">
          <img src="{{ asset('cth_shield_only2.png') }}" alt="CTH Logo" class="h-5 w-5 rounded-md">
        </a>

        <x-nav-link :href="route('home')" :active="request()->routeIs('home')">Home</x-nav-link>
        <x-nav-link :href="route('about')" :active="request()->routeIs('about')">About</x-nav-link>

        @auth
          <x-nav-link :href="route('scan.create')" :active="request()->routeIs('scan.create')">Scan</x-nav-link>
          <x-nav-link :href="route('scan.history')" :active="request()->routeIs('scan.history')">History</x-nav-link>
          <x-nav-link :href="route('stats')" :active="request()->routeIs('stats')">Stats</x-nav-link>
        @endauth
      </div>

      <div class="flex-1"></div>

      {{-- Right: Profile dropdown --}}
      <div>
        @auth
          <div x-data="{ open:false }" @keydown.escape.window="open=false" class="relative">
            <button
              @click="open=!open"
              class="flex items-center space-x-2 px-4 py-1.5 rounded-md bg-gray-100 dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
            >
              <span>{{ Auth::user()->name ?? 'Profile' }}</span>
              <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            {{-- Dropdown --}}
            <div
              x-cloak
              x-show="open"
              x-transition.opacity.scale.80
              @click.away="open=false"
              class="absolute right-0 mt-2 w-44 rounded-md shadow-lg ring-1 ring-black/10 z-50
                     bg-white dark:bg-gray-800"
            >
              <div class="py-1">
                <a href="{{ route('profile.edit') }}"
                   class="block px-4 py-2 text-sm transition-colors
                          text-gray-800 hover:bg-gray-100 hover:text-gray-900
                          dark:text-gray-100 dark:hover:bg-indigo-600 dark:hover:text-white">
                  Profile
                </a>

                <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <button type="submit"
                          class="w-full text-left px-4 py-2 text-sm transition-colors
                                 text-gray-800 hover:bg-gray-100 hover:text-gray-900
                                 dark:text-gray-100 dark:hover:bg-indigo-600 dark:hover:text-white">
                    Logout
                  </button>
                </form>
              </div>
            </div>
          </div>
        @endauth
      </div>
    </div>
  </div>
</nav>
