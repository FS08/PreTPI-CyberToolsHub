{{-- Public "About" page (guest layout) --}}
<x-guest-layout>
  <div class="max-w-4xl mx-auto py-16 space-y-6">
    <div class="text-center space-y-3">
      <h2 class="text-3xl font-semibold">About Cyber Tools Hub</h2>
      <p class="text-gray-700">
        CTH is a learning tool to analyze .eml emails: MIME parsing, SPF/DMARC checks,
        local heuristics, and urlscan.io integration. Designed for clarity and safety:
        no full email content is stored, only analysis results.
      </p>
    </div>

    {{-- Same CTAs as homepage --}}
    <div class="flex items-center justify-center gap-3">
      @guest
        <x-primary-button onclick="window.location='{{ route('register') }}'">Create account</x-primary-button>
        <x-secondary-button onclick="window.location='{{ route('login') }}'">Sign in</x-secondary-button>
      @endguest

      @auth
        <x-primary-button onclick="window.location='{{ route('scan.create') }}'">New scan</x-primary-button>
        <x-secondary-button onclick="window.location='{{ route('scan.history') }}'">History</x-secondary-button>
        <x-secondary-button onclick="window.location='{{ route('stats') }}'">Stats</x-secondary-button>

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
