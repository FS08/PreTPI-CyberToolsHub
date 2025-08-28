{{-- resources/views/layouts/navigation.blade.php --}}
<nav id="siteNav"
     class="fixed top-0 inset-x-0 z-50 border-b border-gray-800
                        bg-gray-900
            text-gray-200 transition-shadow">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between h-14 items-center">

      {{-- Left: Logo + Nav --}}
      <div class="flex items-center space-x-6">
        {{-- Logo --}}
        <a href="{{ route('home') }}" class="flex items-center">
          <img src="{{ asset('favicon-96x96.png') }}"
               alt="CTH Logo"
               class="h-12 w-12">
        </a>

        {{-- Links (high contrast + clearer active/hover) --}}
        @php
          $linkBase = 'px-3 py-1.5 rounded-md text-sm font-medium transition-colors text-gray-200';
          $hover    = 'hover:bg-gray-800 hover:text-white';
          $active   = 'bg-indigo-600 text-white shadow';
        @endphp

        <a href="{{ route('home') }}"
           class="{{ $linkBase }} {{ request()->routeIs('home') ? $active : $hover }}">
          Home
        </a>
        <a href="{{ route('about') }}"
           class="{{ $linkBase }} {{ request()->routeIs('about') ? $active : $hover }}">
          About
        </a>
        @auth
          <a href="{{ route('dashboard') }}"
             class="{{ $linkBase }} {{ request()->routeIs('dashboard') ? $active : $hover }}">
            Dashboard
          </a>
          <a href="{{ route('scan.create') }}"
             class="{{ $linkBase }} {{ request()->routeIs('scan.create') ? $active : $hover }}">
            Scan
          </a>
          <a href="{{ route('scan.history') }}"
             class="{{ $linkBase }} {{ request()->routeIs('scan.history') ? $active : $hover }}">
            History
          </a>
          <a href="{{ route('stats') }}"
             class="{{ $linkBase }} {{ request()->routeIs('stats') ? $active : $hover }}">
            Stats
          </a>
        @endauth
      </div>

      {{-- Right: Profile dropdown (stronger hover contrast) --}}
      <div>
        @auth
          <div class="relative" x-data="{ open: false }">
            <button @click="open = !open"
                    class="flex items-center space-x-2 px-4 py-1.5 rounded-md
                           bg-gray-800 text-sm text-gray-200 hover:bg-gray-700 focus:outline-none">
              <span class="text-indigo-600 dark:text-indigo-400">{{ Auth::user()->name ?? 'Profile' }}</span>
              <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <div x-show="open" @click.away="open = false"
                 x-transition
                 class="absolute right-0 mt-2 w-40 rounded-md shadow-lg
                        bg-gray-900 ring-1 ring-black/10 z-50 overflow-hidden">
              <a href="{{ route('profile.edit') }}"
                 class="block px-4 py-2 text-sm text-gray-100 hover:bg-indigo-600 hover:text-white">
                Profile
              </a>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full text-left px-4 py-2 text-sm text-gray-200 hover:bg-indigo-600 hover:text-white">
                  Logout
                </button>
              </form>
            </div>
          </div>
        @endauth
      </div>
    </div>
  </div>
</nav>

{{-- Spacer so content isn’t hidden behind the fixed nav (skip on home) --}}
@unless(request()->routeIs('home'))
  <div class="h-14"></div>
@endunless

{{-- Add a tiny effect when scrolling --}}
<script>
  (function () {
    const nav = document.getElementById('siteNav');
    const onScroll = () => {
      if (window.scrollY > 8) {
        nav.classList.add('shadow-lg');
      } else {
        nav.classList.remove('shadow-lg');
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  })();
</script>
