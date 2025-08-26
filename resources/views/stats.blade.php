{{-- resources/views/stats.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Stats</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-4xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">

      {{-- KPIs --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total analyses</div>
          <div class="mt-1 text-2xl font-bold" id="kpi-total">{{ number_format($total) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Likely phishing</div>
          <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400" id="kpi-phishing">{{ number_format($phishing) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Suspicious</div>
          <div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400" id="kpi-suspicious">{{ number_format($suspicious) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
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
            <div class="mt-1 text-xs text-right text-gray-500 dark:text-gray-400"><span id="rate-pill">{{ $rate }}</span>%</div>
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
            <canvas id="trendChart" height="120"></canvas>
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
    // Initial data from PHP (server-rendered 7-day)
    const phpLabels = {!! json_encode(collect($trend)->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M d'))) !!};
    const phpData   = {!! json_encode(collect($trend)->pluck('count')) !!};

    const ctx = document.getElementById('trendChart').getContext('2d');
    const trendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: phpLabels,
        datasets: [{
          label: 'Scans',
          data: phpData,
          borderColor: '#2563EB',
          backgroundColor: 'rgba(37, 99, 235, 0.2)',
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: '#2563EB',
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });

    const routeStatsData = "{{ route('stats.data') }}";

    async function loadTrend(days) {
      const res = await fetch(`${routeStatsData}?days=${days}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return;
      const json = await res.json();
      if (!json.ok) return;

      // Update title label
      document.getElementById('range-label').textContent = days;

      // Update chart
      trendChart.data.labels = json.labels;
      trendChart.data.datasets[0].data = json.data;
      trendChart.update();

      // Update table (right)
      const tbody = document.querySelector('#trendTable tbody');
      tbody.innerHTML = '';
      json.trend.forEach(row => {
        const tr = document.createElement('tr');
        tr.className = 'border-t border-gray-100 dark:border-gray-700';
        const tdDate = document.createElement('td');
        tdDate.className = 'py-1 pr-4';
        tdDate.textContent = row.date;
        const tdCount = document.createElement('td');
        tdCount.className = 'py-1 text-center font-medium';
        tdCount.textContent = row.count;
        tr.appendChild(tdDate);
        tr.appendChild(tdCount);
        tbody.appendChild(tr);
      });

      // Update KPIs (overall, from server totals)
      document.getElementById('kpi-total').textContent      = new Intl.NumberFormat().format(json.totals.total);
      document.getElementById('kpi-phishing').textContent   = new Intl.NumberFormat().format(json.totals.phishing);
      document.getElementById('kpi-suspicious').textContent = new Intl.NumberFormat().format(json.totals.suspicious);
      document.getElementById('kpi-legit').textContent      = new Intl.NumberFormat().format(json.totals.legit);

      // Rate number + pill + bar color/width
      const rate = json.totals.phishRate ?? 0;
      document.getElementById('kpi-rate').textContent = rate.toFixed(1);
      document.getElementById('rate-pill').textContent = Math.max(0, Math.min(100, rate)).toFixed(0);
      const bar = document.getElementById('rate-bar');
      bar.style.width = `${Math.max(0, Math.min(100, rate))}%`;
      bar.style.backgroundColor = rate >= 50 ? '#dc2626' : (rate >= 20 ? '#d97706' : '#16a34a');
    }

    // Hook the selector
    const sel = document.getElementById('trendDays');
    sel.addEventListener('change', (e) => loadTrend(e.target.value));
  </script>
</x-app-layout>
