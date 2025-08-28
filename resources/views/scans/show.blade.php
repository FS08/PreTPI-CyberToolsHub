{{-- resources/views/scans/show.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <div class="flex justify-between items-center">
      <h2 class="font-semibold text-xl text-white"><span class="text-indigo-600 dark:text-indigo-400">Scan Details</span></h2>
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
        $spf        = data_get($results, 'extra.spf')   ?? ($scan->spf_json   ?? null);
        $dmarc      = data_get($results, 'extra.dmarc') ?? ($scan->dmarc_json ?? null);
        $heuristics = data_get($results, 'extra.heuristics') ?? ($scan->heuristics_json ?? null);

        $score = (int) data_get($heuristics, 'score', 0);
        $risk  = data_get($heuristics, 'risk', $score >= 50 ? 'high' : ($score >= 20 ? 'medium' : 'low'));
        $verdict = data_get($heuristics, 'verdict');
        $findings = (array) data_get($heuristics, 'findings', []);

        $autoJust = collect($findings)->take(4)->map(fn($f) => trim((string) data_get($f, 'message', '')))->filter()->implode('; ');
        $justification = trim((string) (data_get($heuristics, 'justification', '') ?: $autoJust));

        $scoreClass = $score >= 50 ? 'bg-red-100 text-red-800'
                    : ($score >= 20 ? 'bg-amber-100 text-amber-800'
                    : 'bg-green-100 text-green-800');
        $riskPill   = $risk === 'high' ? 'bg-red-600 text-white'
                    : ($risk === 'medium' ? 'bg-amber-600 text-white'
                    : 'bg-green-600 text-white');
        $icon = $risk === 'high' ? '🛑' : ($risk === 'medium' ? '⚠️' : '✅');
        $verdictText = $verdict ? str_replace('_',' ', ucfirst($verdict)) : ($risk === 'high' ? 'Likely phishing' : ($risk === 'medium' ? 'Suspicious' : 'Low risk'));
      @endphp

      {{-- Verdict Banner + risk badges --}}
        <div class="rounded-xl p-6 border space-y-3
                    {{ $risk === 'high' ? 'bg-red-50 border-red-200 dark:border-red-700 dark:bg-red-900/20' :
                    ($risk === 'medium' ? 'bg-amber-50 border-amber-200 dark:border-amber-700 dark:bg-amber-900/20' :
                                            'bg-green-50 border-green-200 dark:border-green-700 dark:bg-green-900/20') }}">
        <div class="flex flex-wrap items-center gap-4">
            <div class="text-2xl">{{ $icon }}</div>

            <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-sm font-medium {{ $riskPill }}">
                {{ $verdictText }}
            </span>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-sm font-medium {{ $scoreClass }}">
                Score: {{ $score }}
            </span>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-sm font-semibold border
                        {{ $risk==='high' ? 'border-red-500 text-red-700' : ($risk==='medium' ? 'border-amber-500 text-amber-700' : 'border-green-500 text-green-700') }}">
                {{ ucfirst($risk) }} Risk
            </span>
            </div>
        </div>

        @if($justification)
            <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">
            {{ $justification }}
            </p>
        @endif
        </div>

      {{-- Parsed summary --}}
      <details open class="rounded-lg border border-gray-200 dark:border-gray-700 px-5 py-4">
        <summary class="cursor-pointer font-semibold text-lg">Parsed summary</summary>
        <div class="mt-4 space-y-3">
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
              <dt class="text-gray-500 dark:text-gray-400">From</dt>
              <dd class="mt-1 flex items-center">
                <span id="fromVal" class="break-all">{{ $scan->from ?? '—' }}</span>
                @if(!empty($scan->from))
                  <button data-copy="#fromVal" class="copy-btn ml-2" title="Copy">📋</button>
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">From domain</dt>
              <dd class="mt-1 flex items-center">
                <span id="fromDomainVal" class="break-all">{{ $scan->from_domain ?? '—' }}</span>
                @if(!empty($scan->from_domain))
                  <button data-copy="#fromDomainVal" class="copy-btn ml-2" title="Copy">📋</button>
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">To</dt>
              <dd class="mt-1 flex items-center">
                <span id="toVal" class="break-all">{{ $scan->to ?? '—' }}</span>
                @if(!empty($scan->to))
                  <button data-copy="#toVal" class="copy-btn ml-2" title="Copy">📋</button>
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Subject</dt>
              <dd class="mt-1 flex items-center">
                <span id="subjectVal" class="break-all">{{ $scan->subject ?? '—' }}</span>
                @if(!empty($scan->subject))
                  <button data-copy="#subjectVal" class="copy-btn ml-2" title="Copy">📋</button>
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Message-ID</dt>
              <dd class="mt-1 flex items-center">
                @php $mid = data_get($results, 'extra.messageId'); @endphp
                <span id="msgIdVal" class="break-all">{{ $mid ?? '—' }}</span>
                @if(!empty($mid))
                  <button data-copy="#msgIdVal" class="copy-btn ml-2" title="Copy">📋</button>
                @endif
              </dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Date</dt>
              <dd class="mt-1">{{ $scan->date_iso ?? $scan->date_raw ?? '—' }}</dd>
            </div>
            <div><dt class="text-gray-500 dark:text-gray-400">Attachments</dt><dd class="mt-1">{{ $scan->attachments_count ?? 0 }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Raw size</dt><dd class="mt-1">{{ number_format($scan->raw_size) }} bytes</dd></div>
          </dl>
        </div>
      </details>

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
        } elseif (in_array($qual,['~all','?all'])) {
            $spfBadge = ['class'=>'bg-amber-100 text-amber-800','text'=>"SPF: soft ($qual)",'detail'=>'May allow spoofing'];
        } elseif ($qual === '+all') {
            $spfBadge = ['class'=>'bg-red-100 text-red-800','text'=>'SPF: +all (insecure)','detail'=>'Accepts any sender'];
        } else {
            $spfBadge = ['class'=>'bg-blue-100 text-blue-800','text'=>'SPF: found','detail'=>'No explicit all-qualifier'];
        }
        if ($ptr) $spfBadge['detail'] .= ($spfBadge['detail'] ? ' · ' : '') . 'Contains ptr (discouraged)';
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
        else                         $dmarcBadge = ['class'=>'bg-blue-100 text-blue-800','text'=>'DMARC: found','detail'=>'Policy: '.$p];
        }
    }
    @endphp

      {{-- Sender authentication --}}
      <details open class="rounded-lg border border-gray-200 dark:border-gray-700 px-5 py-4">
        <summary class="cursor-pointer font-semibold text-lg">Sender authentication</summary>
        <div class="mt-4 flex flex-col gap-3 text-sm">
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

      {{-- Heuristic findings --}}
      <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-5 py-4">
        <div class="font-semibold text-lg">Heuristic analysis</div>
        <div class="mt-4 space-y-4">
          @forelse($findings as $f)
            @php
              $sev = strtolower($f['severity'] ?? 'info');
              $border = match($sev) {
                'high'   => 'border-red-400 bg-red-50 dark:bg-red-900/20 dark:border-red-700',
                'medium' => 'border-amber-400 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700',
                'low'    => 'border-blue-400 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700',
                default  => 'border-gray-400 bg-gray-50 dark:bg-gray-800/40 dark:border-gray-700'
              };
              $pill = match($sev) {
                'high'   => 'bg-red-600 text-white',
                'medium' => 'bg-amber-600 text-white',
                'low'    => 'bg-blue-600 text-white',
                default  => 'bg-gray-500 text-white'
              };
              $pretty = !empty($f['evidence']) ? json_encode($f['evidence'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : null;
            @endphp

            <details class="rounded-lg border-l-4 {{ $border }} px-4 py-3">
              <summary class="cursor-pointer flex items-center gap-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $pill }}">
                  {{ ucfirst($sev) }}
                </span>
                <span class="text-sm">{{ $f['message'] ?? '' }}</span>
                @if(isset($f['score']))
                  <span class="ml-auto text-xs text-gray-600 dark:text-gray-300">+{{ (int)$f['score'] }} pts</span>
                @endif
              </summary>

              @if($pretty)
                <div class="mt-3">
                  <div class="text-xs text-gray-600 dark:text-gray-300 mb-2">Evidence</div>
                  <pre class="rounded-md overflow-x-auto"><code class="language-json">{{ $pretty }}</code></pre>
                </div>
              @endif
            </details>
          @empty
            <div class="text-sm text-gray-500">No heuristic findings.</div>
          @endforelse
        </div>
      </div>

      {{-- Extracted URLs --}}
      @if ($scan->urls->count() > 0)
        <details open class="rounded-lg border border-gray-200 dark:border-gray-700 px-5 py-4">
          <summary class="cursor-pointer font-semibold text-lg">
            Extracted URLs ({{ $scan->urls->count() }})
          </summary>
          <div class="mt-3">
            <ul class="space-y-3 text-sm">
              @foreach ($scan->urls as $i => $u)
                @php $rowId = 'url-'.$i; @endphp
                <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-200 dark:border-gray-700 pb-2">
                  <div class="break-all">
                    <span id="{{ $rowId }}">
                      <a href="{{ $u->url }}" target="_blank" rel="noopener noreferrer nofollow"
                        class="text-blue-600 dark:text-blue-400 hover:underline">{{ $u->url }}</a>
                    </span>
                  </div>
                  <div class="flex items-center gap-3 text-xs text-gray-700 dark:text-gray-300">
                    <button data-copy="#{{ $rowId }}" class="copy-btn ml-2" title="Copy URL">📋 Copy</button>
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

      <div class="text-sm text-gray-600 dark:text-gray-300">
        This page shows saved scan metadata, SPF/DMARC info, heuristic checks, and URLscan submissions.
        The original email body is never stored for privacy reasons.
      </div>
    </div>
  </div>

  {{-- Copy button style --}}
  <style>
    .copy-btn{
      padding: 0.25rem 0.6rem;
      border: 1px solid rgba(107,114,128,0.6);
      border-radius: 0.375rem;
      font-size: 0.75rem;
      background: transparent;
      color: inherit;
      cursor: pointer;
    }
    .copy-btn:hover{ background: rgba(107,114,128,0.1); }
    .copy-btn:focus{ outline: 2px solid rgba(107,114,128,0.5); outline-offset: 2px; }
    pre code{ display:block; padding:0.75rem; font-size:12px; border-radius:0.5rem; }
  </style>

  {{-- highlight.js --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/styles/github-dark.min.css">
  <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/common.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('pre code').forEach(hljs.highlightElement);
    });
  </script>

  {{-- Copy-to-clipboard --}}
  <script>
    function getTextFromTarget(sel) {
      const el = document.querySelector(sel);
      if (!el) return '';
      return (el.innerText || el.textContent || '').trim();
    }
    function attachCopy() {
      document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const txt = getTextFromTarget(btn.dataset.copy);
          if (!txt) return;
          try {
            await navigator.clipboard.writeText(txt);
            const old = btn.textContent;
            btn.textContent = '✅ Copied';
            setTimeout(() => btn.textContent = old, 900);
          } catch {
            const old = btn.textContent;
            btn.textContent = '❌';
            setTimeout(() => btn.textContent = old, 900);
          }
        });
      });
    }
    attachCopy();
  </script>
</x-app-layout>
