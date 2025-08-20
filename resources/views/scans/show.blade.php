{{-- resources/views/scans/show.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <div class="flex justify-between items-center">
      <h2 class="font-semibold text-xl">Scan Details</h2>
      <a href="{{ route('scan.history') }}" 
         class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium 
                rounded-lg shadow hover:bg-gray-900 focus:outline-none focus:ring-2 
                focus:ring-offset-2 focus:ring-gray-700 transition">
        ← Back to History
      </a>
    </div>
  </x-slot>

  <div class="p-6">
    <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">

      {{-- Summary --}}
      <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <h3 class="font-semibold mb-2">Parsed summary</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
          <div>
            <dt class="text-gray-500 dark:text-gray-400">From</dt>
            <dd>{{ $scan->from ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500 dark:text-gray-400">From domain</dt>
            <dd>{{ $scan->from_domain ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500 dark:text-gray-400">To</dt>
            <dd>{{ $scan->to ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500 dark:text-gray-400">Subject</dt>
            <dd>{{ $scan->subject ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500 dark:text-gray-400">Date</dt>
            <dd>{{ $scan->date_iso ?? $scan->date_raw ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500 dark:text-gray-400">Attachments</dt>
            <dd>{{ $scan->attachments_count ?? 0 }}</dd>
          </div>
          <div>
            <dt class="text-gray-500 dark:text-gray-400">Raw size</dt>
            <dd>{{ number_format($scan->raw_size) }} bytes</dd>
          </div>
        </dl>
      </div>

      {{-- Extracted URLs + urlscan submission status --}}
      @if ($scan->urls->count() > 0)
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <h3 class="font-semibold mb-2">Extracted URLs ({{ $scan->urls->count() }})</h3>
          <ul class="space-y-2 text-sm">
            @foreach ($scan->urls as $url)
              <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                <div class="break-all">
                  <a href="{{ $url->url }}" target="_blank" rel="noopener noreferrer nofollow"
                     class="text-blue-600 dark:text-blue-400 hover:underline">
                    {{ $url->url }}
                  </a>
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-300">
                  @if ($url->status === 'submitted' && $url->result_url)
                    ✅ Submitted → <a href="{{ $url->result_url }}" target="_blank" class="underline">View</a>
                  @elseif ($url->status === 'error')
                    ❌ Error: {{ $url->error_message }}
                  @else
                    ⏳ {{ ucfirst($url->status) }}
                  @endif
                </div>
              </li>
            @endforeach
          </ul>
        </div>
      @else
        <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600 dark:text-gray-300 dark:border-gray-700">
          No URLs detected.
        </div>
      @endif

      <div class="pt-2 text-sm text-gray-600 dark:text-gray-300">
        This page shows saved scan metadata and submission status.  
        The original email body is never stored for privacy reasons.
      </div>

    </div>
  </div>
</x-app-layout>