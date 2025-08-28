{{-- resources/views/history.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-white"><span class="text-indigo-600 dark:text-indigo-400">History</span></h2>
  </x-slot>

  <style>
    /* Hover effect + cursor on sortable headers */
    th.sort { cursor: pointer; transition: background 0.2s ease; }
    th.sort:hover { background-color: rgba(59,130,246,0.15); }
    th.sort.active { background-color: rgba(59,130,246,0.25); }
  </style>

  <div class="p-6">
    <div class="max-w-5xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">

      {{-- Quick search (AJAX, instant) --}}
      <div class="flex items-center gap-3">
        <input
          id="q"
          type="text"
          value="{{ $filters['q'] ?? '' }}"
          placeholder="Quick search… (from, subject, domain)"
          autocomplete="off" autocapitalize="off" spellcheck="false"
          class="w-full rounded-md border border-gray-300 px-3 py-2 bg-white dark:bg-white placeholder-gray-500"
          style="color:#111 !important; -webkit-text-fill-color:#111; caret-color:#111; background-color:#fff !important;"
        />
        <a href="{{ route('scan.history') }}"
           class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Reset</a>
      </div>

      {{-- Results container (will be replaced by AJAX) --}}
      <div id="history-results">
        @include('history._table', ['scans' => $scans, 'sort' => $filters['sort'] ?? 'date_desc'])
      </div>

      <p class="text-sm text-gray-600 dark:text-gray-300">
        This page lists saved scans (metadata only). No email body is stored.
      </p>
    </div>
  </div>

  <script>
    const endpoint = "{{ route('scan.history.partial') }}";
    let sort = "{{ $filters['sort'] ?? 'date_desc' }}";

    // Debounce helper
    function debounce(fn, ms=300) {
      let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    async function fetchTable(params) {
      const url = new URL(endpoint, window.location.origin);
      Object.entries(params).forEach(([k, v]) => { if (v !== '' && v !== null) url.searchParams.set(k, v); });

      const wrap = document.getElementById('history-results');
      wrap.style.opacity = .6;
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      wrap.style.opacity = 1;

      if (!res.ok) return;
      const json = await res.json();
      if (!json.ok) return;

      wrap.innerHTML = json.html;
      attachSortHandlers();
      attachPaginationHandlers();
      highlightActiveSort();
    }

    // Live search
    const onType = debounce(() => {
      const q = document.getElementById('q').value;
      fetchTable({ q, sort });
      const newUrl = new URL(window.location);
      if (q) newUrl.searchParams.set('q', q); else newUrl.searchParams.delete('q');
      newUrl.searchParams.set('sort', sort);
      history.replaceState({}, '', newUrl);
    }, 300);
    document.getElementById('q').addEventListener('input', onType);

    // Sorting
    function attachSortHandlers() {
      document.querySelectorAll('#history-results th.sort').forEach(th => {
        th.addEventListener('click', () => {
          const key = th.dataset.key;
          const map = {
            date:    ['date_desc','date_asc'],
            subject: ['subject_desc','subject_asc'],
            from:    ['from_desc','from_asc'],
            risk:    ['risk_desc','risk_asc'],
          };
          const pair = map[key] || map.date;
          sort = (sort === pair[0]) ? pair[1] : pair[0];
          const q = document.getElementById('q').value;
          fetchTable({ q, sort });

          const newUrl = new URL(window.location);
          if (q) newUrl.searchParams.set('q', q); else newUrl.searchParams.delete('q');
          newUrl.searchParams.set('sort', sort);
          history.replaceState({}, '', newUrl);
        });
      });
    }

    // Highlight active sorted column
    function highlightActiveSort() {
      document.querySelectorAll('#history-results th.sort').forEach(th => {
        th.classList.remove('active');
        const key = th.dataset.key;
        if ((key === 'date' && sort.startsWith('date'))
         || (key === 'subject' && sort.startsWith('subject'))
         || (key === 'from' && sort.startsWith('from'))
         || (key === 'risk' && sort.startsWith('risk'))) {
          th.classList.add('active');
        }
      });
    }

    // Pagination
    function attachPaginationHandlers() {
      document.querySelectorAll('#history-results .pagination a, #history-results nav[role="navigation"] a').forEach(a => {
        a.addEventListener('click', (e) => {
          e.preventDefault();
          const target = new URL(a.href);
          const qVal = document.getElementById('q').value;
          target.searchParams.set('sort', sort);
          if (qVal) target.searchParams.set('q', qVal);
          fetch(target.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
              if (json?.ok) {
                const wrap = document.getElementById('history-results');
                wrap.innerHTML = json.html;
                attachSortHandlers();
                attachPaginationHandlers();
                highlightActiveSort();
                history.replaceState({}, '', target);
              }
            });
        });
      });
    }

    // Init
    attachSortHandlers();
    attachPaginationHandlers();
    highlightActiveSort();
  </script>
</x-app-layout>
