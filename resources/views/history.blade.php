{{-- resources/views/history.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">History</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-5xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">

      {{-- Filters / Search (dev-style inputs) --}}
      <form method="GET" action="{{ route('scan.history') }}" class="space-y-3">
        @php
          $inputBase  = 'w-full rounded-md border border-gray-300 px-3 py-2 bg-white dark:bg-white placeholder-gray-500';
          $forceStyle = 'color:#111 !important; -webkit-text-fill-color:#111; caret-color:#111; background-color:#fff !important;';
        @endphp

        <div class="grid grid-cols-1 gap-3">
          <div>
            <label class="block text-sm font-semibold mb-1">Search</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="from, subject, domain…" autocomplete="off" autocapitalize="off" spellcheck="false"
                   class="{{ $inputBase }}" style="{{ $forceStyle }}">
          </div>

          <div>
            <label class="block text-sm font-semibold mb-1">From domain</label>
            <input type="text" name="from_domain" value="{{ $filters['from_domain'] ?? '' }}"
                   placeholder="example.com" autocomplete="off" autocapitalize="off" spellcheck="false"
                   class="{{ $inputBase }}" style="{{ $forceStyle }}">
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-semibold mb-1">Date from</label>
              <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                     class="{{ $inputBase }}" style="{{ $forceStyle }}">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1">Date to</label>
              <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                     class="{{ $inputBase }}" style="{{ $forceStyle }}">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-sm font-semibold mb-1">Risk</label>
              @php $riskSel = $filters['risk'] ?? ''; @endphp
              <select name="risk" class="{{ $inputBase }}" style="{{ $forceStyle }}">
                <option value="">Any</option>
                <option value="low"    {{ $riskSel==='low' ? 'selected' : '' }}>Low</option>
                <option value="medium" {{ $riskSel==='medium' ? 'selected' : '' }}>Medium</option>
                <option value="high"   {{ $riskSel==='high' ? 'selected' : '' }}>High</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1">Score min</label>
              <input type="number" name="score_min" min="0" max="100" value="{{ $filters['score_min'] ?? '' }}"
                     class="{{ $inputBase }}" style="{{ $forceStyle }}">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1">Score max</label>
              <input type="number" name="score_max" min="0" max="100" value="{{ $filters['score_max'] ?? '' }}"
                     class="{{ $inputBase }}" style="{{ $forceStyle }}">
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold mb-1">Sort</label>
            @php $sortSel = $filters['sort'] ?? 'date_desc'; @endphp
            <select name="sort" class="{{ $inputBase }}" style="{{ $forceStyle }}">
              <option value="date_desc"  {{ $sortSel==='date_desc' ? 'selected' : '' }}>Newest first</option>
              <option value="date_asc"   {{ $sortSel==='date_asc' ? 'selected' : '' }}>Oldest first</option>
              <option value="score_desc" {{ $sortSel==='score_desc' ? 'selected' : '' }}>Score high → low</option>
              <option value="score_asc"  {{ $sortSel==='score_asc' ? 'selected' : '' }}>Score low → high</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
          <button type="submit"
                  class="inline-flex items-center px-4 py-2 rounded-md bg-gray-900 text-white text-sm shadow hover:bg-black">
            APPLY FILTERS
          </button>
          <a href="{{ route('scan.history') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Reset</a>
        </div>
      </form>

      {{-- Results --}}
      @if ($scans->count() === 0)
        <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600 text-center dark:text-gray-300 dark:border-gray-700">
          No results with the current filters.
        </div>
      @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
          <table class="w-full table-auto text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/60 text-gray-700 dark:text-gray-200">
              <tr class="text-left">
                <th class="px-6 py-4 text-base font-bold">Date</th>
                <th class="px-6 py-4 text-base font-bold">From</th>
                <th class="px-6 py-4 text-base font-bold">Subject</th>
                <th class="px-6 py-4 text-base font-bold text-center">URLs</th>
                <th class="px-6 py-4 text-base font-bold text-center">Attachments</th>
                <th class="px-6 py-4 text-base font-bold text-right">Size</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              @foreach ($scans as $scan)
                @php
                  // Always use upload time and show only the date; keep full timestamp in tooltip
                  $tz = config('app.timezone') ?: 'UTC';
                  $dt = optional($scan->created_at)?->copy()->timezone($tz);
                @endphp
                <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-800 dark:even:bg-gray-800/70">
                  <td class="px-6 py-4 whitespace-nowrap">
                    @if($dt)
                      <time datetime="{{ $dt->toIso8601String() }}" title="{{ $dt->toDayDateTimeString() }} {{ $tz }}">
                        {{ $dt->format('Y-m-d') }}
                      </time>
                    @else
                      —
                    @endif
                  </td>
                  <td class="px-6 py-4 break-all">{{ $scan->from ?? '—' }}</td>
                  <td class="px-6 py-4 break-all">
                    <a href="{{ route('scan.show', $scan->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                      {{ $scan->subject ?? '—' }}
                    </a>
                  </td>
                  <td class="px-6 py-4 text-center">{{ (int) $scan->urls_count }}</td>
                  <td class="px-6 py-4 text-center">{{ (int) $scan->attachments_count }}</td>
                  <td class="px-6 py-4 text-right">{{ number_format((int) $scan->raw_size) }} bytes</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

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
