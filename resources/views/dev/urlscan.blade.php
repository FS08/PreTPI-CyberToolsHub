<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl">urlscan.io – Dev sandbox</h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-3xl mx-auto bg-white shadow rounded-xl p-6 space-y-6 dark:bg-gray-800 dark:text-gray-100">

      @isset($error)
        <div class="rounded-lg border border-red-300 bg-red-50 text-red-800 p-3 dark:bg-red-900/30 dark:text-red-200 dark:border-red-600">
          {{ $error }}
        </div>
      @endisset

      {{-- Search form --}}
      <div class="space-y-2">
        <h3 class="font-semibold">Search</h3>
        <form method="GET" action="{{ route('dev.urlscan.search') }}" class="flex gap-2">
          <input
            type="text"
            name="q"
            value="{{ $q ?? '' }}"
            placeholder="e.g. domain:example.com OR url:example.com/path"
            autocomplete="off" autocapitalize="off" spellcheck="false"
            class="flex-1 rounded-md border border-gray-300 px-3 py-2
                   bg-white dark:bg-white
                   placeholder-gray-500"
            style="color:#111 !important; -webkit-text-fill-color:#111; caret-color:#111; background-color:#fff !important;"
          >
          <x-primary-button>Run search</x-primary-button>
        </form>

        @if ($searchResults)
          <div class="mt-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <h4 class="font-semibold mb-2">Results</h4>
            <pre class="text-xs overflow-x-auto">{{ json_encode($searchResults, JSON_PRETTY_PRINT) }}</pre>
          </div>
        @endif
      </div>

      <hr class="border-gray-200 dark:border-gray-700">

      {{-- Submit form --}}
      <div class="space-y-4">
        <h3 class="font-semibold">Submit a URL</h3>
        <form method="POST" action="{{ route('dev.urlscan.submit') }}" class="space-y-3">
          @csrf
          <input
            type="url"
            name="url"
            placeholder="https://example.com"
            required
            autocomplete="off" autocapitalize="off" spellcheck="false"
            class="w-full rounded-md border border-gray-300 px-3 py-2
                   bg-white dark:bg-white
                   placeholder-gray-500"
            style="color:#111 !important; -webkit-text-fill-color:#111; caret-color:#111; background-color:#fff !important;"
          >

          <label class="inline-flex items-center gap-2 text-sm mt-2">
            <input type="checkbox" name="public" value="1" checked class="rounded">
            <span class="text-gray-700 dark:text-gray-300">Public result</span>
          </label>

          <div>
            <x-primary-button>Submit</x-primary-button>
          </div>
        </form>

        @if ($submitResponse)
          <div class="mt-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <h4 class="font-semibold mb-2">Submission response</h4>
            <pre class="text-xs overflow-x-auto">{{ json_encode($submitResponse, JSON_PRETTY_PRINT) }}</pre>
          </div>
        @endif
      </div>

      <p class="text-sm text-gray-600 dark:text-gray-300">
        This page is temporary and only for development. It won’t store anything in your DB.
      </p>
    </div>
  </div>
</x-app-layout>