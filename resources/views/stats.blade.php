{{-- resources/views/stats.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Stats</h2>
  </x-slot>

  <style>
    /* Extra spacing between KPI cards (in addition to Tailwind's gap) */
    .kpi-card { margin-bottom: .75rem; } /* 12px */
  </style>

  <div class="p-6">
    <div class="max-w-4xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">

      {{-- KPIs --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700 kpi-card">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total analyses</div>
          <div class="mt-1 text-2xl font-bold" id="kpi-total">{{ number_format($total) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700 kpi-card">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Likely phishing</div>
          <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400" id="kpi-phishing">{{ number_format($phishing) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700 kpi-card">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Suspicious</div>
          <div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400" id="kpi-suspicious">{{ number_format($suspicious) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700 kpi-card">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Likely legitimate</div>
          <div class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400" id="kpi-legit">{{ number_format($legit) }}</div>
        </div>
      </div>

      {{-- Phishing rate --}}
      <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Phishing rate</div>
            <div class="mt-1 text-3xl font-bold"><span id="kpi-rate">{{ number_format($phishRate, 1) }}</span>%</div>
          </div>
          @php $rate = max(0, min(100, (float) $phishRate)); @endphp
          <div class="w-40">
            <div class="h-2 w-full bg-gray-200 rounded-full dark:bg-gray-700">
              <div id="rate-bar" class="h-2 rounded-full transition-all"
                   style="width: {{ $rate }}%; background-color: {{ $rate >= 50 ? '#dc2626' : ($rate >= 20 ? '#d97706' : '#16a34a') }};">
              </div>
            </div>
            <div class="mt-1 text-xs text-right text-gray-500 dark:text-gray-400">
              <span id="rate-pill">{{ $rate }}</span>%
            </div>
          </div>
        </div>
      </div>

      {{-- Chart.js trend --}}
      <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold">Scans – last <span id="range-label">7</span> days</h3>

          {{-- Simple range switcher --}}
          <div class="flex items-center gap-2 text-sm">
            <label for="trendDays" class="text-gray-600 dark:text-gray-300">Range</label>
            <select id="trendDays"
                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 text-sm">
              <option value="7"  selected>7 days</option>
              <option value="14">14 days</option>
              <option value="30">30 days</option>
            </select>
          </div>
        </div>

        <div class="flex w-full items-center gap-10">
          {{-- Chart --}}
          <div class="flex-1">
            <div class="relative h-[140px]">
              <canvas id="trendChart"></canvas>
            </div>
          </div>

          {{-- Table on the right --}}
          <div class="shrink-0">
            <table class="min-w-[220px] text-sm" id="trendTable">
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

  {{-- Chart.js CDN --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    // Initial server-rendered series (Total scans)
    const phpLabels = {!! json_encode(collect($trend)->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M d'))) !!};
    const phpData   = {!! json_encode(collect($trend)->pluck('count')) !!};

    const ctx = document.getElementById('trendChart').getContext('2d');
    const trendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: phpLabels,
        datasets: [
          {
            label: 'Total Scans',
            data: phpData,
            borderColor: '#2563EB',
            backgroundColor: 'rgba(37, 99, 235, 0.18)',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: '#2563EB',
            fill: true,
            tension: 0.3
          },
          {
            label: 'Likely Phishing',
            data: [], // will be filled after fetch
            borderColor: '#DC2626',
            backgroundColor: 'rgba(220, 38, 38, 0.18)',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: '#DC2626',
            fill: true,
            tension: 0.3
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'nearest', axis: 'x', intersect: false },
        plugins: {
          legend: { display: true },
          tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
              // Show "Dataset: value" and a quick ratio when both datasets exist
              label: (ctx) => `${ctx.dataset.label}: ${ctx.formattedValue}`,
              afterBody: (items) => {
                if (items.length < 2) return '';
                try {
                  const total = items.find(i => i.dataset.label === 'Total Scans')?.parsed.y ?? null;
                  const phish = items.find(i => i.dataset.label === 'Likely Phishing')?.parsed.y ?? null;
                  if (total && phish !== null) {
                    const pct = total > 0 ? ((phish / total) * 100).toFixed(1) : '0.0';
                    return `Phishing share: ${pct}%`;
                  }
                } catch (_e) {}
                return '';
              }
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });

    const routeStatsData = "{{ route('stats.data') }}";

    async function loadTrend(days) {
      const res = await fetch(`${routeStatsData}?days=${days}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) return;
      const json = await res.json();
      if (!json.ok) return;

      // Range label
      document.getElementById('range-label').textContent = days;

      // Chart labels + both datasets
      if (Array.isArray(json.labels)) {
        trendChart.data.labels = json.labels;
      }
      if (Array.isArray(json.data)) {
        trendChart.data.datasets[0].data = json.data;
      }
      if (Array.isArray(json.dataPhish)) {
        trendChart.data.datasets[1].data = json.dataPhish;
      }
      trendChart.update();

      // Table (if backend returns day-level array)
      if (Array.isArray(json.trend)) {
        const tbody = document.querySelector('#trendTable tbody');
        tbody.innerHTML = '';
        json.trend.forEach(row => {
          const tr = document.createElement('tr');
          tr.className = 'border-t border-gray-100 dark:border-gray-700';
          tr.innerHTML = `<td class="py-1 pr-4">${row.date}</td>
                          <td class="py-1 text-center font-medium">${row.count}</td>`;
          tbody.appendChild(tr);
        });
      }

      // KPIs (tolerant if fields are missing)
      if (json.totals) {
        if (json.totals.total !== undefined)
          document.getElementById('kpi-total').textContent = new Intl.NumberFormat().format(json.totals.total);
        if (json.totals.phishing !== undefined)
          document.getElementById('kpi-phishing').textContent = new Intl.NumberFormat().format(json.totals.phishing);
        if (json.totals.suspicious !== undefined)
          document.getElementById('kpi-suspicious').textContent = new Intl.NumberFormat().format(json.totals.suspicious);
        if (json.totals.legit !== undefined)
          document.getElementById('kpi-legit').textContent = new Intl.NumberFormat().format(json.totals.legit);

        if (json.totals.phishRate !== undefined) {
          const rate = json.totals.phishRate ?? 0;
          document.getElementById('kpi-rate').textContent = rate.toFixed(1);
          document.getElementById('rate-pill').textContent = Math.max(0, Math.min(100, rate)).toFixed(0);
          const bar = document.getElementById('rate-bar');
          bar.style.width = `${Math.max(0, Math.min(100, rate))}%`;
          bar.style.backgroundColor = rate >= 50 ? '#dc2626' : (rate >= 20 ? '#d97706' : '#16a34a');
        }
      }
    }

    // Initial fetch to populate the Phishing dataset as well
    const sel = document.getElementById('trendDays');
    sel.addEventListener('change', (e) => loadTrend(e.target.value));
    // Kick once for the default (7)
    loadTrend(sel.value);
  </script>
</x-app-layout>
