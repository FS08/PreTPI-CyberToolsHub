{{-- resources/views/history/_table.blade.php --}}
<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
  <table class="w-full table-auto text-sm">
    <thead class="bg-gray-50 dark:bg-gray-700/60 text-gray-700 dark:text-gray-200">
      @php
        $activeSort = request('sort', 'date_desc');

        // Helper to mark active column + next sort direction
        function sortMeta($col, $active) {
            $pairs = [
              'date'    => ['date_asc', 'date_desc'],
              'from'    => ['from_asc', 'from_desc'],
              'subject' => ['subject_asc', 'subject_desc'],
              'risk'    => ['risk_asc', 'risk_desc'],
            ];
            [$asc, $desc] = $pairs[$col];
            $isAsc  = $active === $asc;
            $isDesc = $active === $desc;
            $next   = $isAsc ? $desc : $asc;
            $activeState = $isAsc ? 'asc' : ($isDesc ? 'desc' : null);
            return compact('next','activeState');
        }
        $mDate    = sortMeta('date', $activeSort);
        $mFrom    = sortMeta('from', $activeSort);
        $mSubject = sortMeta('subject', $activeSort);
        $mRisk    = sortMeta('risk', $activeSort);

        $thBase = 'px-6 py-4 text-base font-bold cursor-pointer select-none transition-colors';
        $thIdle = 'hover:text-blue-500 hover:underline underline-offset-4';
        $thActiveAsc  = 'text-blue-500 underline underline-offset-4';
        $thActiveDesc = 'text-blue-500 underline underline-offset-4';
      @endphp

      <tr class="text-left">
        <th
          class="sort {{ $thBase }} {{ $mDate['activeState']==='asc'?$thActiveAsc:($mDate['activeState']==='desc'?$thActiveDesc:$thIdle) }}"
          data-key="date"
          data-sort="{{ $mDate['next'] }}"
          aria-sort="{{ $mDate['activeState'] ?? 'none' }}"
        >Date</th>

        <th
          class="sort {{ $thBase }} {{ $mFrom['activeState']==='asc'?$thActiveAsc:($mFrom['activeState']==='desc'?$thActiveDesc:$thIdle) }}"
          data-key="from"
          data-sort="{{ $mFrom['next'] }}"
          aria-sort="{{ $mFrom['activeState'] ?? 'none' }}"
        >From</th>

        <th
          class="sort {{ $thBase }} {{ $mSubject['activeState']==='asc'?$thActiveAsc:($mSubject['activeState']==='desc'?$thActiveDesc:$thIdle) }}"
          data-key="subject"
          data-sort="{{ $mSubject['next'] }}"
          aria-sort="{{ $mSubject['activeState'] ?? 'none' }}"
        >Subject</th>

        <th
          class="sort {{ $thBase }} {{ $mRisk['activeState']==='asc'?$thActiveAsc:($mRisk['activeState']==='desc'?$thActiveDesc:$thIdle) }} text-center"
          data-key="risk"
          data-sort="{{ $mRisk['next'] }}"
          aria-sort="{{ $mRisk['activeState'] ?? 'none' }}"
        >Risk</th>

        <th class="px-6 py-4 text-base font-bold text-center">URLs</th>
        <th class="px-6 py-4 text-base font-bold text-center">Attachments</th>
        <th class="px-6 py-4 text-base font-bold text-right">Size</th>
        <th class="px-6 py-4 text-base font-bold text-center">Action</th>
      </tr>
    </thead>

    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
      @foreach ($scans as $scan)
        @php
          $tz = config('app.timezone') ?: 'UTC';
          $dt = optional($scan->created_at)?->copy()->timezone($tz);

          $score = isset($scan->heuristics_score)
              ? (int) $scan->heuristics_score
              : (int) data_get($scan->heuristics_json, 'score', 0);

          $risk  = $score >= 50 ? 'High' : ($score >= 20 ? 'Medium' : 'Low');
          $riskClass = $score >= 50 ? 'bg-red-600'
                      : ($score >= 20 ? 'bg-amber-600' : 'bg-green-600');
        @endphp
        <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-800 dark:even:bg-gray-800/70">
          <td class="px-6 py-4 whitespace-nowrap">
            @if($dt)
              <time datetime="{{ $dt->toIso8601String() }}" title="{{ $dt->toDayDateTimeString() }} {{ $tz }}">
                {{ $dt->format('Y-m-d') }}
              </time>
            @else — @endif
          </td>

          <td class="px-6 py-4 break-all">{{ $scan->from ?? '—' }}</td>

          <td class="px-6 py-4 break-all">{{ $scan->subject ?? '—' }}</td>

          <td class="px-6 py-4 text-center">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white {{ $riskClass }}">
              {{ $risk }}
            </span>
          </td>

          <td class="px-6 py-4 text-center">🔗 {{ (int) $scan->urls_count }}</td>
          <td class="px-6 py-4 text-center">📎 {{ (int) $scan->attachments_count }}</td>

          <td class="px-6 py-4 text-right">
            {{ number_format((int) $scan->raw_size) }} bytes
          </td>

          <td class="px-6 py-4 text-center">
            <a href="{{ route('scan.show', $scan->id) }}"
               class="inline-flex items-center justify-center px-3 py-1.5 rounded-md bg-gray-700 text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-600 transition">
              View details →
            </a>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="mt-4 flex justify-center">
  {{ $scans->links() }}
</div>
