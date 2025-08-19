{{-- Protected "Scan" page (app layout) --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">Scan</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">
      
      {{-- Upload form: POST + multipart/form-data to send file --}}
      <form method="POST" action="{{ route('scan.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        {{-- File input: backend will read request()->file('eml') --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
            Upload .eml file
          </label>
          <input type="file" name="eml" accept=".eml" required 
                 class="block w-full border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-300">
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Max 15MB, MIME: message/rfc822</p>

          {{-- Server-side validation error for the file input --}}
          @error('eml')
            <p class="text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <x-primary-button>Upload</x-primary-button>
      </form>

      {{-- Success flash message (from POST handler) --}}
      @if (session('ok'))
        <div class="text-green-700 dark:text-green-400">✅ {{ session('ok') }}</div>
      @endif

      {{-- Parsed summary (shown after a successful upload + parsing) --}}
      @if (session('results'))
        @php $r = session('results'); @endphp
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <h3 class="font-semibold mb-2">Parsed summary</h3>
          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
            <div>
              <dt class="text-gray-500 dark:text-gray-400">From</dt>
              <dd>{{ $r['from'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">From domain</dt>
              <dd>{{ $r['fromDomain'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">To</dt>
              <dd>{{ $r['to'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Subject</dt>
              <dd>{{ $r['subject'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Date</dt>
              <dd>{{ $r['date'] ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Text length</dt>
              <dd>{{ $r['bodies']['textLength'] ?? 0 }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">HTML length</dt>
              <dd>{{ $r['bodies']['htmlLength'] ?? 0 }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 dark:text-gray-400">Attachments</dt>
              <dd>{{ $r['attachments']['count'] ?? 0 }}</dd>
            </div>
          </dl>
        </div>

        {{-- Extracted URLs --}}
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
      @endif

      {{-- Simple guidance --}}
      <p class="text-sm text-gray-600 dark:text-gray-300">
        This step validates, accepts, and parses the .eml file in memory. 
        No email content is stored. We also extract URLs (basic regex, normalized & deduped).
      </p>
    </div>
  </div>
</x-app-layout>