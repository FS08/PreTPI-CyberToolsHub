{{-- resources/views/history.blade.php --}}
<x-app-layout>
  {{-- ---------- Header ---------- --}}
  <x-slot name="header">
    <h2 class="font-semibold text-xl">History</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">

      @if ($scans->count() === 0)
        <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600 text-center dark:text-gray-300 dark:border-gray-700">
          No scans yet. Upload an .eml file on the
          <a href="{{ route('scan.create') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Scan</a> page.
        </div>
      @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/60 text-gray-700 dark:text-gray-200">
              <tr class="text-center">
                <th class="px-6 py-4 text-base font-bold">When</th>
                <th class="px-6 py-4 text-base font-bold">From</th>
                <th class="px-6 py-4 text-base font-bold">Subject</th>
                <th class="px-6 py-4 text-base font-bold">URLs</th>
                <th class="px-6 py-4 text-base font-bold">Attachments</th>
                <th class="px-6 py-4 text-base font-bold">Size</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              @foreach ($scans as $scan)
                <tr class="text-center odd:bg-white even:bg-gray-50 dark:odd:bg-gray-800 dark:even:bg-gray-800/70">
                  <td class="px-6 py-4 whitespace-nowrap">
                    {{ optional($scan->created_at)->format('Y-m-d H:i') }}
                  </td>
                  <td class="px-6 py-4 break-all">{{ $scan->from ?? '—' }}</td>
                  <td class="px-6 py-4 break-all">{{ $scan->subject ?? '—' }}</td>
                  <td class="px-6 py-4">{{ $scan->urls_count }}</td>
                  <td class="px-6 py-4">{{ $scan->attachments_count }}</td>
                  <td class="px-6 py-4">{{ number_format($scan->raw_size) }} bytes</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Centered pagination --}}
        <div class="mt-4 flex justify-center">
          {{ $scans->links() }}
        </div>
      @endif

      <p class="text-sm text-gray-600 dark:text-gray-300">
        This page lists saved scans (metadata only). No email body is stored.
      </p>
    </div>
  </div>
</x-app-layout>