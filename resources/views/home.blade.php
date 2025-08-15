{{-- Public homepage (guest layout) --}}
<x-guest-layout>
  {{-- Hero section --}}
  <div class="max-w-4xl mx-auto py-16 text-center space-y-6">
    <h1 class="text-4xl font-bold tracking-tight">Cyber Tools Hub</h1>
    <p class="text-gray-600">
      Analyze .eml emails to detect suspicious links, SPF/DMARC issues, and phishing signals.
    </p>

    {{-- Call-to-actions: change based on auth state --}}
    <div class="flex items-center justify-center gap-3">
      @guest
        <x-primary-button onclick="window.location='{{ route('register') }}'">Create account</x-primary-button>
        <x-secondary-button onclick="window.location='{{ route('login') }}'">Sign in</x-secondary-button>
      @endguest

      @auth
        <x-primary-button onclick="window.location='{{ route('scan.create') }}'">New scan</x-primary-button>
        <x-secondary-button onclick="window.location='{{ route('scan.history') }}'">History</x-secondary-button>
        <x-secondary-button onclick="window.location='{{ route('stats') }}'">Stats</x-secondary-button>

        {{-- Inline logout (POST with CSRF) --}}
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="ms-2 text-sm text-gray-600 hover:text-gray-900 underline">
            Logout
          </button>
        </form>
      @endauth
    </div>
  </div>
</x-guest-layout>
