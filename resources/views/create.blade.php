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
          <input type="file" name="eml" accept=".eml" required class="block w-full">
          <p class="text-xs text-gray-500 mt-1">Max 15MB, MIME: message/rfc822</p>

          {{-- Server-side validation error for the file input --}}
          @error('eml')
            <p class="text-red-600 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <x-primary-button>Upload</x-primary-button>
      </form>

      {{-- Success flash message (from POST handler) --}}
      @if (session('ok'))
        <div class="text-green-700 dark:text-green-400">✅ {{ session('ok') }}</div>
      @endif>

      {{-- Simple guidance (no parsing yet) --}}
      <p class="text-sm text-gray-600 dark:text-gray-300">
        This step only validates and accepts the .eml upload. Parsing comes next.
      </p>
    </div>
  </div>
</x-app-layout>
