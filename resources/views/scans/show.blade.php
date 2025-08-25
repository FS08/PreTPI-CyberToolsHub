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

      @php
        // Pull persisted + flash results
        $spf        = data_get($results, 'extra.spf')   ?? ($scan->spf_json   ?? null);
        $dmarc      = data_get($results, 'extra.dmarc') ?? ($scan->dmarc_json ?? null);
        $heuristics = data_get($results, 'extra.heuristics') ?? ($scan->heuristics_json ?? null);

        // ---- Heuristic banner data (with sane fallbacks) ----
        $score = (int) data_get($heuristics, 'score', 0);
        $risk  = data_get($heuristics, 'risk', $score >= 50 ? 'high' : ($score >= 20 ? 'medium' : 'low'));
        $verdict = data_get($heuristics, 'verdict'); // may be null if you haven’t added it yet in 6.3
        $findings = (array) data_get($heuristics, 'findings', []);

        // Build a short justification if backend didn’t set one
        $autoJust = collect($findings)->take(4)->map(function($f){
            return trim((string) data_get($f, 'message', ''));
        })->filter()->implode('; ');
        $justification = trim((string) (data_get($heuristics, 'justification', '') ?: $autoJust));

        // Colors & icons
        $scoreClass = $score >= 50 ? 'bg-red-100 text-red-800'
                    : ($score >= 20 ? 'bg-amber-100 text-amber-800'
                    : 'bg-green-100 text-green-800');
        $riskPill   = $risk === 'high' ? 'bg-red-600 text-white'
                    : ($risk === 'medium' ? 'bg-amber-600 text-white'
                    : 'bg-green-600 text-white');
        $icon = $risk === 'high' ? '🛑' : ($risk === 'medium' ? '⚠️' : '✅');
        $verdictText = $verdict ? str_replace('_',' ', ucfirst($verdict)) : ($risk === 'high' ? 'Likely phishing' : ($risk === 'medium' ? 'Suspicious' : 'Low risk'));
      @endphp

      {{-- Verdict Banner --}}
      <div class="rounded-xl p-4 border
                  {{ $risk === 'high' ? 'bg-red-50 border-red-200 dark:border-red-700 dark:bg-red-900/20' :
                     ($risk === 'medium' ? 'bg-amber-50 border-amber-200 dark:border-amber-700 dark:bg-amber-900/20' :
                                            'bg-green-50 border-green-200 dark:border-green-700 dark:bg-green-900/20') }}">
        <div class="flex flex-wrap items-center gap-3">
          <div class="text-2xl">{{ $icon }}</div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $riskPill }}">
              {{ $verdictText }}
            </span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $scoreClass }}">
              Score: {{ $score }}
            </span>
          </div>
        </div>
        @if($justification)
          <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
            {{ $justification }}
          </p>
        @endif
      </div>

      {{-- Parsed summary --}}
      <details open class="rounded-lg border border-gray-200 dark:border-gray-700">
        <summary class="cursor-pointer px-4 py-3 font-semibold">Parsed summary</summary>
        <div class="px-4 pb-4">
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
            <div><dt class="text-gray-500 dark:text-gray-400">From</dt><dd>{{ $scan->from ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">From domain</dt><dd>{{ $scan->from_domain ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">To</dt><dd>{{ $scan->to ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Subject</dt><dd>{{ $scan->subject ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Date</dt><dd>{{ $scan->date_iso ?? $scan->date_raw ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Attachments</dt><dd>{{ $scan->attachments_count ?? 0 }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Raw size</dt><dd>{{ number_format($scan->raw_size) }} bytes</dd></div>
          </dl>
        </div>
      </details>

      {{-- Sender authentication (SPF + DMARC) --}}
      @php
        // SPF badge
        $spfBadge = ['class'=>'bg-gray-200 text-gray-800','text'=>'SPF: not checked','detail'=>''];
        if (is_array($spf)) {
          if (!empty($spf['error'])) {
            $spfBadge = ['class'=>'bg-yellow-100 text-yellow-800','text'=>'SPF: lookup error','detail'=>$spf['error']];
          } elseif (empty($spf['found'])) {
            $spfBadge = ['class'=>'bg-red-100 text-red-800','text'=>'SPF: not found','detail'=>'No TXT record with v=spf1'];
          } else {
            $qual = null; $ptr = false;
            foreach ((array) data_get($spf,'parsed',[]) as $p) {
              $qual = $qual ?? (data_get($p,'all') ?: null);
              foreach ((array) data_get($p,'mechanisms',[]) as $m) {
                if (strtolower(data_get($m,'type',''))==='ptr') $ptr = true;
              }
            }
            if ($qual === '-all') {
              $spfBadge = ['class'=>'bg-green-100 text-green-800','text'=>'SPF: strict (-all)','detail'=>'Strong policy'];
            } elseif (in_array($qual,['~all','?all'], true)) {
              $spfBadge = ['class'=>'bg-amber-100 text-amber-800','text'=>"SPF: soft ($qual)",'detail'=>'May allow spoofing'];
            } elseif ($qual === '+all') {
              $spfBadge = ['class'=>'bg-red-100 text-red-800','text'=>'SPF: +all (insecure)','detail'=>'Accepts any sender'];
            } else {
              $spfBadge = ['class'=>'bg-blue-100 text-blue-800','text'=>'SPF: found','detail'=>'No explicit all-qualifier'];
            }
            if ($ptr) {
              $spfBadge['detail'] .= ($spfBadge['detail'] ? ' · ' : '') . 'Contains ptr (discouraged)';
            }
          }
        }

        // DMARC badge
        $dmarcBadge = ['class'=>'bg-gray-200 text-gray-800','text'=>'DMARC: not checked','detail'=>''];
        if (is_array($dmarc)) {
          if (!empty($dmarc['error'])) {
            $dmarcBadge = ['class'=>'bg-yellow-100 text-yellow-800','text'=>'DMARC: lookup error','detail'=>$dmarc['error']];
          } elseif (empty($dmarc['found'])) {
            $dmarcBadge = ['class'=>'bg-red-100 text-red-800','text'=>'DMARC: not found','detail'=>'No _dmarc TXT'];
          } else {
            $p = strtolower((string) data_get($dmarc,'parsed.0.policy',''));
            if     ($p === 'reject')     $dmarcBadge = ['class'=>'bg-green-100 text-green-800','text'=>'DMARC: p=reject','detail'=>'Strong enforcement'];
            elseif ($p === 'quarantine') $dmarcBadge = ['class'=>'bg-amber-100 text-amber-800','text'=>'DMARC: p=quarantine','detail'=>'Partial enforcement'];
            elseif ($p === 'none')       $dmarcBadge = ['class'=>'bg-blue-100 text-blue-800','text'=>'DMARC: p=none','detail'=>'Monitor only'];
            else                          $dmarcBadge = ['class'=>'bg-blue-100 text-blue-800','text'=>'DMARC: found','detail'=>'Policy: '.$p];
          }
        }
      @endphp

      <details open class="rounded-lg border border-gray-200 dark:border-gray-700">
        <summary class="cursor-pointer px-4 py-3 font-semibold">Sender authentication</summary>
        <div class="px-4 pb-4 flex flex-col gap-3">
          <div class="flex items-start gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $spfBadge['class'] }}">
              {{ $spfBadge['text'] }}
            </span>
            @if($spfBadge['detail']) <span class="text-xs text-gray-600 dark:text-gray-300">{{ $spfBadge['detail'] }}</span> @endif
          </div>
          <div class="flex items-start gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $dmarcBadge['class'] }}">
              {{ $dmarcBadge['text'] }}
            </span>
            @if($dmarcBadge['detail']) <span class="text-xs text-gray-600 dark:text-gray-300">{{ $dmarcBadge['detail'] }}</span> @endif
          </div>
        </div>
      </details>

      {{-- Heuristics --}}
      @if(is_array($heuristics))
      <details open class="rounded-lg border border-gray-200 dark:border-gray-700">
        <summary class="cursor-pointer px-4 py-3 font-semibold">Heuristic analysis</summary>
        <div class="px-4 pb-4">
          <ul class="space-y-2 text-sm">
            @forelse($findings as $f)
              <li class="border-b pb-2 border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2">
                  @php
                    $sev = strtolower($f['severity'] ?? 'info');
                    $sevClass = match($sev) {
                      'high'   => 'bg-red-100 text-red-800',
                      'medium' => 'bg-amber-100 text-amber-800',
                      'low'    => 'bg-blue-100 text-blue-800',
                      default  => 'bg-gray-200 text-gray-800'
                    };
                  @endphp
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sevClass }}">
                    {{ ucfirst($sev) }}
                  </span>
                  <span>{{ $f['message'] ?? '' }}</span>
                </div>
                @if(!empty($f['evidence']))
                  <details class="ml-6 mt-1 text-xs text-gray-600 dark:text-gray-300">
                    <summary class="cursor-pointer">Evidence</summary>
                    <pre class="whitespace-pre-wrap break-all">{{ json_encode($f['evidence'], JSON_PRETTY_PRINT) }}</pre>
                  </details>
                @endif
              </li>
            @empty
              <li class="text-sm text-gray-500">No heuristic findings.</li>
            @endforelse
          </ul>
        </div>
      </details>
      @endif

      {{-- Extracted URLs --}}
      @if ($scan->urls->count() > 0)
        <details open class="rounded-lg border border-gray-200 dark:border-gray-700">
          <summary class="cursor-pointer px-4 py-3 font-semibold">
            Extracted URLs ({{ $scan->urls->count() }})
          </summary>
          <div class="px-4 pb-4">
            <ul class="space-y-2 text-sm">
              @foreach ($scan->urls as $u)
                <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                  <div class="break-all">
                    <a href="{{ $u->url }}" target="_blank" rel="noopener noreferrer nofollow"
                      class="text-blue-600 dark:text-blue-400 hover:underline">{{ $u->url }}</a>
                  </div>
                  <div class="text-xs text-gray-700 dark:text-gray-300">
                    @php $label = ucfirst($u->status ?? 'queued'); @endphp
                    @if ($u->result_url)
                      ✅ {{ $label }} → <a href="{{ $u->result_url }}" target="_blank" class="underline">View</a>
                    @elseif ($u->status === 'error')
                      ❌ Error: {{ $u->error_message }}
                    @elseif ($u->status === 'blocked')
                      🚫 Blocked
                    @elseif ($u->status === 'rate_limited')
                      ⏳ Rate limited
                    @else
                      ⏳ {{ $label }}
                    @endif
                  </div>
                </li>
              @endforeach
            </ul>
          </div>
        </details>
      @else
        <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600 dark:text-gray-300">
          No URLs detected.
        </div>
      @endif

      <div class="pt-2 text-sm text-gray-600 dark:text-gray-300">
        This page shows saved scan metadata, SPF/DMARC info, heuristic checks, and URLscan submissions.
        The original email body is never stored for privacy reasons.
      </div>

    </div>
  </div>
</x-app-layout>
