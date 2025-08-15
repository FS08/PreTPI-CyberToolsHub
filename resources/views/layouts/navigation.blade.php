<!-- Logo -->
<div class="shrink-0 flex items-center">
    {{-- Minimal brand link to home --}}
    <a href="{{ route('home') }}" class="font-bold text-lg">CTH</a>
</div>

<!-- Nav Links -->
<div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
    {{-- Public links --}}
    <x-nav-link :href="route('home')" :active="request()->routeIs('home')">
        Home
    </x-nav-link>

    <x-nav-link :href="route('about')" :active="request()->routeIs('about')">
        About
    </x-nav-link>

    {{-- Private links (visible if authenticated) --}}
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

        {{-- Visible logout button --}}
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline">
                Logout
            </button>
        </form>
    @endauth
</div>
