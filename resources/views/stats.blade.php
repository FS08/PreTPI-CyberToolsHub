{{-- resources/views/stats.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Stats</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">

      {{-- Headline KPIs --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total analyses</div>
          <div class="mt-1 text-2xl font-bold">{{ number_format($total) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Likely phishing</div>
          <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ number_format($phishing) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Suspicious</div>
          <div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($suspicious) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Likely legitimate</div>
          <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ number_format($legit) }}</div>
        </div>
      </div>

      {{-- Phishing rate --}}
      <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Phishing rate</div>
            <div class="mt-1 text-3xl font-bold">{{ number_format($phishRate, 1) }}%</div>
          </div>
          @php $rate = max(0, min(100, (float) $phishRate)); @endphp
          <div class="w-40">
            <div class="h-2 w-full bg-gray-200 rounded-full dark:bg-gray-700">
              <div class="h-2 rounded-full transition-all"
                   style="width: {{ $rate }}%; background-color: {{ $rate >= 50 ? '#dc2626' : ($rate >= 20 ? '#d97706' : '#16a34a') }};">
              </div>
            </div>
            <div class="mt-1 text-xs text-right text-gray-500 dark:text-gray-400">{{ $rate }}%</div>
          </div>
        </div>
      </div>

      {{-- 7‑day trend --}}
      @php
        $counts = collect($trend)->pluck('count')->all();
        $maxVal = max(1, max($counts));
        $w = 280; $h = 60;
        $step = $w / max(1, (count($counts)-1));
        $points = [];
        foreach ($counts as $i => $v) {
          $x = $i * $step;
          $y = $h - ($v / $maxVal) * ($h - 6) - 3; // top/bottom padding
          $points[] = $x . ',' . $y;
        }
        $pointsAttr = implode(' ', $points);
      @endphp

      <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold">Scans – last 7 days</h3>
          <div class="text-xs text-gray-500 dark:text-gray-400">by created date</div>
        </div>

        {{-- Row: chart left (centered & lowered), table hard-right --}}
        <div class="flex w-full items-center">
          {{-- Chart container centered on the left --}}
          <div class="flex-1 flex justify-center">
            <svg
              viewBox="0 0 {{ $w }} {{ $h }}"
              width="{{ $w }}"
              height="{{ $h }}"
              class="mt-6"
            >
              <line x1="0" y1="{{ $h-1 }}" x2="{{ $w }}" y2="{{ $h-1 }}" stroke="#D1D5DB" class="dark:stroke-gray-700"/>
              @if($pointsAttr !== '')
                <polyline fill="none" stroke="#2563EB" stroke-width="2" points="{{ $pointsAttr }}" class="dark:stroke-blue-400"/>
                @foreach($counts as $i => $v)
                  @php
                    $x = $i * $step;
                    $y = $h - ($v / $maxVal) * ($h - 6) - 3;
                  @endphp
                  <circle cx="{{ $x }}" cy="{{ $y }}" r="2.5" fill="#2563EB" class="dark:fill-blue-400"/>
                @endforeach
              @endif
            </svg>
          </div>

          {{-- Table wrapper pushed to the edge (consumes right padding with -mr-2) --}}
          <div class="ml-auto -mr-2">
            <table class="min-w-[220px] text-sm">
              <thead class="text-gray-600 dark:text-gray-300">
                <tr>
                  <th class="py-1 pr-4 text-left">Date</th>
                  <th class="py-1 text-center">Scans</th>
                </tr>
              </thead>
              <tbody class="text-gray-800 dark:text-gray-100">
                @foreach ($trend as $row)
                  <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="py-1 pr-4">{{ \Carbon\Carbon::parse($row['date'])->format('Y-m-d') }}</td>
                    <td class="py-1 text-center font-medium">{{ $row['count'] }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <p class="text-sm text-gray-600 dark:text-gray-300">
        These metrics are computed from your saved scans. Only metadata is stored; email bodies are never saved.
      </p>
    </div>
  </div>
</x-app-layout>
