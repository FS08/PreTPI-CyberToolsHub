{{-- resources/views/about.blade.php --}}
<x-app-layout>
  <x-slot name="header">
    <h2 class="font-semibold text-xl text-white"><span class="text-indigo-600 dark:text-indigo-400">About</span></h2>
  </x-slot>

  <div class="p-6">
    <div class="max-w-4xl mx-auto bg-white shadow rounded-2xl p-8 space-y-8 dark:bg-gray-800 dark:text-gray-100">

      {{-- Founder card --}}
      <div class="flex flex-col sm:flex-row items-start gap-6">
        <img src="{{ asset('founder.jpg') }}" alt="Founder photo"
             class="w-40 h-40 rounded-xl object-cover ring-2 ring-gray-200 dark:ring-gray-700">
        <div>
          <h1 class="text-2xl font-bold">Hi, I’m <span class="text-indigo-600 dark:text-indigo-400">Fábio Santos</span>.</h1>
          <p class="mt-2 text-gray-600 dark:text-gray-300 leading-relaxed">
            I built <strong>Cyber Tools Hub</strong> to help people quickly understand if an email is safe—without
            needing to be a security expert. After seeing friends and teams struggle with phishing, I wanted a tool that
            is fast, transparent, and privacy-first.
          </p>
          <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            • Based in Lausanne • Security tinkerer • Coffee enthusiast ☕
          </div>
        </div>
      </div>

      {{-- Mission --}}
      <section>
        <h2 class="text-xl font-semibold">Mission</h2>
        <p class="mt-2 text-gray-700 dark:text-gray-200 leading-relaxed">
          Make email security <em>approachable</em>. That means clear signals, honest risk, and evidence you can trust.
          We won’t bury you in jargon or store what we don’t need.
        </p>
      </section>

      {{-- What makes it different --}}
      <section>
        <h2 class="text-xl font-semibold">What makes this different?</h2>
        <div class="mt-3 grid sm:grid-cols-2 gap-4 text-sm">
          <div class="rounded-xl p-5 border bg-white dark:bg-gray-900 dark:border-gray-700">
            <div class="text-lg">🔒 Privacy by default</div>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Only metadata is saved; bodies never leave your machine.</p>
          </div>
          <div class="rounded-xl p-5 border bg-white dark:bg-gray-900 dark:border-gray-700">
            <div class="text-lg">🧠 Helpful heuristics</div>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Human-readable findings—no opaque magic.</p>
          </div>
          <div class="rounded-xl p-5 border bg-white dark:bg-gray-900 dark:border-gray-700">
            <div class="text-lg">⚡ Fast feedback</div>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Upload, verdict, done. Train the eye, reduce risk.</p>
          </div>
          <div class="rounded-xl p-5 border bg-white dark:bg-gray-900 dark:border-gray-700">
            <div class="text-lg">🧪 URL sandbox support</div>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Submit suspicious links for safe inspection.</p>
          </div>
        </div>
      </section>

      {{-- Timeline / story --}}
      <section>
        <h2 class="text-xl font-semibold">The short story</h2>
        <ol class="mt-3 space-y-3 text-gray-700 dark:text-gray-200 text-sm">
          <li>• 2024 — Started as a weekend project after a phishing incident in a small team.</li>
          <li>• 2025 — Evolved into a lightweight tool with heuristics and URL scanning.</li>
          <li>• Today — Focused on clarity, speed, and helping people build intuition.</li>
        </ol>
      </section>

      {{-- Call to action --}}
      <div class="pt-2">
        @auth
          <a href="{{ route('scan.create') }}"
             class="inline-flex items-center px-5 py-3 rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow">
            Run your first scan →
          </a>
        @else
          <a href="{{ route('register') }}"
             class="inline-flex items-center px-5 py-3 rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow">
            Join and try it →
          </a>
        @endauth
      </div>

    </div>
  </div>
</x-app-layout>
