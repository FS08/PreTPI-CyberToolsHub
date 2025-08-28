<x-app-layout>
  <x-slot name="header"></x-slot>

  {{-- Hero --}}
  <section class="bg-gradient-to-b from-indigo-50 to-white dark:from-gray-900 dark:to-gray-800">
    <div class="max-w-7xl mx-auto px-6 py-20 sm:py-28 space-y-10">
      <div class="grid md:grid-cols-2 gap-14 items-center">
        <div class="space-y-6">
          <div class="inline-flex items-center gap-2 text-xs font-medium px-3 py-1 rounded-full
                      bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
            <span>New</span> <span>Built for privacy</span>
          </div>
          <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-gray-900 dark:text-white leading-snug">
            Spot phishing emails <span class="text-indigo-600 dark:text-indigo-400">before</span> they spot you.
          </h1>
          <p class="text-lg text-gray-600 dark:text-gray-300 leading-relaxed">
            Upload a <code class="px-1 py-0.5 bg-gray-100 rounded dark:bg-gray-700">.eml</code> file.
            We’ll extract URLs, check SPF/DMARC, run heuristics, and surface risk in seconds—without storing bodies.
          </p>

          <div class="flex flex-wrap items-center gap-4 mt-8">
            @auth
              <a href="{{ route('scan.create') }}"
                 class="inline-flex items-center px-6 py-3 rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow">
                Start a scan →
              </a>
              <a href="{{ route('scan.history') }}"
                 class="inline-flex items-center px-6 py-3 rounded-xl bg-white text-gray-900 border border-gray-200 hover:bg-gray-50
                        dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:hover:bg-gray-700">
                View history
              </a>
            @else
              <a href="{{ route('register') }}"
                 class="inline-flex items-center px-6 py-3 rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow">
                Create free account →
              </a>
              <a href="{{ route('login') }}"
                 class="inline-flex items-center px-6 py-3 rounded-xl bg-white text-gray-900 border border-gray-200 hover:bg-gray-50
                        dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:hover:bg-gray-700">
                Sign in
              </a>
            @endauth
          </div>

          {{-- Trust blips --}}
          <div class="mt-10 flex flex-wrap gap-x-8 gap-y-3 text-sm text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-2">🔒 No email body stored</div>
            <div class="flex items-center gap-2">⚡ Fast heuristics</div>
            <div class="flex items-center gap-2">🧪 URL sandboxing support</div>
          </div>
        </div>

        {{-- Hero Illustration --}}
        <div class="relative space-y-4">
          <div class="absolute inset-0 -z-10 bg-indigo-200/30 blur-3xl rounded-full translate-x-10 translate-y-10
                      dark:bg-indigo-400/10"></div>
          <div class="rounded-2xl border border-gray-200 shadow-2xl overflow-hidden bg-white
                      dark:bg-gray-800 dark:border-gray-700">
            <img src="{{ asset('homepage-show.png') }}" alt="App screenshot"
                 class="w-full h-90 object-cover object-top dark:opacity-90">
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400">A peek at the Stats page.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- Value Props --}}
  <section class="py-20 bg-white dark:bg-gray-800">
    <div class="max-w-7xl mx-auto px-6 space-y-10">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Why choose us</h2>
      <div class="grid md:grid-cols-3 gap-10">
        <div class="rounded-2xl p-8 border bg-white shadow-sm dark:bg-gray-900 dark:border-gray-700 space-y-4">
          <div class="text-3xl">🔎</div>
          <h3 class="font-semibold text-gray-900 dark:text-white text-lg">Deep parsing</h3>
          <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
            Extract headers, detect links, and read metadata reliably—no copy/paste chaos.
          </p>
        </div>
        <div class="rounded-2xl p-8 border bg-white shadow-sm dark:bg-gray-900 dark:border-gray-700 space-y-4">
          <div class="text-3xl">🧠</div>
          <h3 class="font-semibold text-gray-900 dark:text-white text-lg">Heuristics that help</h3>
          <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
            Smart rules highlight urgency tactics, domain look-alikes, weak auth, and more.
          </p>
        </div>
        <div class="rounded-2xl p-8 border bg-white shadow-sm dark:bg-gray-900 dark:border-gray-700 space-y-4">
          <div class="text-3xl">📈</div>
          <h3 class="font-semibold text-gray-900 dark:text-white text-lg">Actionable stats</h3>
          <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
            Track trends over time and see phishing rates drop as your team learns.
          </p>
        </div>
      </div>
    </div>
  </section>

  {{-- How it works --}}
  <section class="py-20 bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-6 space-y-10">
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">How it works</h2>
      <div class="grid md:grid-cols-4 gap-8 text-sm">
        <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
          <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">1.</div>
          <p class="text-gray-700 dark:text-gray-200">Upload a <strong>.eml</strong> file securely.</p>
        </div>
        <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
          <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">2.</div>
          <p class="text-gray-700 dark:text-gray-200">We parse headers, URLs, and metadata.</p>
        </div>
        <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
          <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">3.</div>
          <p class="text-gray-700 dark:text-gray-200">Heuristics + SPF/DMARC checks run instantly.</p>
        </div>
        <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
          <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">4.</div>
          <p class="text-gray-700 dark:text-gray-200">You get a verdict and clear next steps.</p>
        </div>
      </div>

      <div class="pt-6">
        @auth
          <a href="{{ route('scan.create') }}" class="inline-flex items-center px-6 py-3 rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow">
            Try a scan now →
          </a>
        @else
          <a href="{{ route('register') }}" class="inline-flex items-center px-6 py-3 rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow">
            Get started free →
          </a>
        @endauth
      </div>
    </div>
  </section>

  {{-- FAQ --}}
  <section class="py-20 bg-white dark:bg-gray-800">
    <div class="max-w-7xl mx-auto px-6 space-y-14">
      <div class="grid md:grid-cols-2 gap-14">
        <div class="space-y-6">
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Why another phishing tool?</h2>
          <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
            Most tools overwhelm users. We focus on clarity: a single upload, a single verdict, with transparent evidence.
          </p>
          <ul class="space-y-3 text-gray-700 dark:text-gray-200 text-sm">
            <li>• No email body storage—privacy first.</li>
            <li>• Friendly UI for non-security folks.</li>
            <li>• Clear findings you can share with your team.</li>
          </ul>
        </div>

        <div class="rounded-2xl p-8 border bg-white shadow-sm dark:bg-gray-900 dark:border-gray-700 space-y-6">
          <h3 class="font-semibold text-gray-900 dark:text-white text-lg">FAQ</h3>
          <dl class="space-y-5 text-sm">
            <div>
              <dt class="font-medium"><span class="text-indigo-600 dark:text-indigo-400">Do you store my emails?</span></dt>
              <dd class="mt-2 text-gray-600 dark:text-gray-300">No. We save only lightweight metadata.</dd>
            </div>
            <div>
              <dt class="font-medium"><span class="text-indigo-600 dark:text-indigo-400">Can I export results?</span></dt>
              <dd class="mt-2 text-gray-600 dark:text-gray-300">History and details are always available; exports are on the roadmap.</dd>
            </div>
            <div>
              <dt class="font-medium"><span class="text-indigo-600 dark:text-indigo-400">Is there an API?</span></dt>
              <dd class="mt-2 text-gray-600 dark:text-gray-300">Coming soon—reach out if you want early access.</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
  </section>
</x-app-layout>
