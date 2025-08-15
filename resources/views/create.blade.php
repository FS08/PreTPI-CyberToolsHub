{{-- Protected "Scan" page (app layout) --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Scan</h2>
  </x-slot>

  <div class="p-6">
    {{-- Uniform card look --}}
    <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6 space-y-4">
      <p class="text-gray-700">
        This page will let you upload a <code>.eml</code> file and parse it in memory
        (no full email content will be stored). For now it is a placeholder.
      </p>

      {{-- Quick actions --}}
      <div class="flex items-center gap-3">
        <x-secondary-button onclick="window.location='{{ route('scan.history') }}'">Go to history</x-secondary-button>
        <x-secondary-button onclick="window.location='{{ route('stats') }}'">View stats</x-secondary-button>
      </div>
    </div>
  </div>
</x-app-layout>
