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

      {{-- SPF info --}}
      @php
        $spf = is_array($scan->spf_json) ? $scan->spf_json : (json_decode($scan->spf_json ?? '[]', true) ?: []);
      @endphp
      <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <h3 class="font-semibold mb-2">SPF Verification</h3>
        @if (!empty($spf['found']) && !empty($spf['records']))
          <p class="text-sm mb-2 text-green-700 dark:text-green-400">✅ SPF record(s) found for {{ $scan->from_domain }}</p>
          <ul class="list-disc ms-5 text-sm space-y-1">
            @foreach ($spf['records'] as $rec)
              <li class="break-all">{{ $rec }}</li>
            @endforeach
          </ul>
        @elseif (!empty($spf['error']))
          <p class="text-sm text-red-600 dark:text-red-400">⚠️ SPF lookup error: {{ $spf['error'] }}</p>
        @else
          <p class="text-sm text-gray-600 dark:text-gray-300">No SPF record found for {{ $scan->from_domain ?? 'domain' }}.</p>
        @endif
      </div>

      {{-- Extracted URLs + urlscan submission status --}}
      @if ($scan->urls->count() > 0)
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <h3 class="font-semibold mb-2">Extracted URLs ({{ $scan->urls->count() }})</h3>
          <ul class="space-y-2 text-sm">
            @foreach ($scan->urls as $u)
              <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                <div class="break-all">
                  <a href="{{ $u->url }}" target="_blank" rel="noopener noreferrer nofollow"
                     class="text-blue-600 dark:text-blue-400 hover:underline">
                    {{ $u->url }}
                  </a>
                </div>

                <div class="text-xs text-gray-700 dark:text-gray-300">
                  @php
                    $label = ucfirst($u->status ?? 'queued');
                  @endphp

                  @if (!empty($u->result_url))
                    <span class="mr-1">✅ {{ $label }}</span>
                    <a href="{{ $u->result_url }}" target="_blank" class="underline">View</a>
                  @elseif ($u->status === 'error')
                    ❌ Error: {{ $u->error_message }}
                  @elseif ($u->status === 'blocked')
                    🚫 Blocked
                  @elseif ($u->status === 'rate_limited')
                    ⏳ Rate limited — retry later
                  @else
                    ⏳ {{ $label }}
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
        This page shows saved scan metadata, SPF info, and submission status.  
        The original email body is never stored for privacy reasons.
      </div>

    </div>
  </div>
</x-app-layout>