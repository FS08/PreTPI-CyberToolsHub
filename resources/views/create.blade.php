{{-- Protected "Scan" page (app layout) --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Scan</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">
      
      {{-- Upload form --}}
      <form method="POST" action="{{ route('scan.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Upload .eml file
          </label>
          <input type="file" name="eml" accept=".eml" required 
                 class="block w-full border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-300">
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Max 15MB, MIME: message/rfc822</p>
          @error('eml')
            <p class="text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
          @enderror
        </div>
        <x-primary-button>Upload</x-primary-button>
      </form>

      {{-- Success flash --}}
      @if (session('ok'))
        <div class="text-green-700 dark:text-green-400">✅ {{ session('ok') }}</div>
      @endif

      {{-- Parsed results --}}
      @if (session('results'))
        @php $r = session('results'); @endphp

        {{-- Summary --}}
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <h3 class="font-semibold mb-2">Parsed summary</h3>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
            <div><dt class="text-gray-500 dark:text-gray-400">From</dt><dd>{{ $r['from'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">From domain</dt><dd>{{ $r['fromDomain'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">To</dt><dd>{{ $r['to'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Subject</dt><dd>{{ $r['subject'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Date (raw)</dt><dd>{{ $r['dateRaw'] ?? '—' }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Text length</dt><dd>{{ data_get($r, 'bodies.textLength', 0) }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">HTML length</dt><dd>{{ data_get($r, 'bodies.htmlLength', 0) }}</dd></div>
            <div><dt class="text-gray-500 dark:text-gray-400">Attachments</dt><dd>{{ data_get($r, 'attachments.count', 0) }}</dd></div>
          </dl>
        </div>

        {{-- URLs --}}
        @if (!empty($r['urls']))
          <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="font-semibold mb-2">Extracted URLs ({{ count($r['urls']) }})</h3>
            <ul class="list-disc ms-5 space-y-1 text-sm">
              @foreach ($r['urls'] as $u)
                <li>
                  <a href="{{ $u }}" target="_blank" rel="noopener noreferrer nofollow"
                     class="text-blue-600 dark:text-blue-400 hover:underline">
                    {{ $u }}
                  </a>
                </li>
              @endforeach
            </ul>
          </div>
        @else
          <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600 dark:text-gray-300 dark:border-gray-700">
            No URLs detected.
          </div>
        @endif

        {{-- Extra metadata --}}
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="font-semibold mb-2">Extra metadata</h3>
            <ul class="list-disc ms-5 text-sm space-y-1">
                <li><span class="text-gray-500 dark:text-gray-400">Message-ID:</span> {{ data_get($r, 'extra.messageId', '—') }}</li>
                <li><span class="text-gray-500 dark:text-gray-400">Content-Type:</span> {{ data_get($r, 'extra.contentType', '—') }}</li>
                <li><span class="text-gray-500 dark:text-gray-400">Date (ISO):</span> {{ data_get($r, 'extra.dateIso', '—') }}</li>
                <li>
                <span class="text-gray-500 dark:text-gray-400">Received hops:</span>
                @php $hops = data_get($r, 'extra.received', []); @endphp
                @if (!empty($hops))
                    <ul class="list-disc ms-5">
                    @foreach ($hops as $hop)
                        <li class="break-all">{{ $hop }}</li>
                    @endforeach
                    </ul>
                @else
                    —
                @endif
                </li>
            </ul>
        </div>
      @endif

      <p class="text-sm text-gray-600 dark:text-gray-300">
        This step parses the .eml in memory (no storage), extracts URLs and lightweight metadata,
        and prepares for persistence in the next step.
      </p>
    </div>
  </div>
</x-app-layout>