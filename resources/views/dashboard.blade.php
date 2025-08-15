{{-- Protected "Dashboard" (Breeze redirect target) --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Dashboard</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-4xl mx-auto bg-white shadow rounded-xl p-6 space-y-3">
      <p class="text-gray-700">
        Welcome! From here you can start a new scan, view your history, or check stats.
      </p>
      <div class="flex items-center gap-3">
        <x-primary-button onclick="window.location='{{ route('scan.create') }}'">New scan</x-primary-button>
        <x-secondary-button onclick="window.location='{{ route('scan.history') }}'">History</x-secondary-button>
        <x-secondary-button onclick="window.location='{{ route('stats') }}'">Stats</x-secondary-button>
      </div>
    </div>
  </div>
</x-app-layout>
