{{-- Protected "Statistics" page (app layout) --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Statistics</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-4xl mx-auto bg-white shadow rounded-xl p-6">
      <p class="text-gray-700">
        Charts will be displayed here after we persist scan outcomes (e.g., total scans,
        phishing ratio, top indicators). We’ll use Chart.js later.
      </p>
    </div>
  </div>
</x-app-layout>
