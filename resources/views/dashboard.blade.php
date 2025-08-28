<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
            <span class="text-indigo-600 dark:text-indigo-400">{{ __('Dashboard') }}</span>
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        {{-- KPIs --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-2">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Scans this month') }}</p>
                <p class="text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $scansThisMonth }}</p>
                @if($monthlyQuota)
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $scansThisMonth }} / {{ $monthlyQuota }} ({{ $quotaUsedPct }}%)
                    </p>
                @endif
            </div>

            <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-2">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Quota used') }}</p>
                <p class="text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $quotaUsedPct }}%</p>
                <div class="h-2 w-full rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    <div class="h-2 bg-indigo-600" style="width: {{ min($quotaUsedPct,100) }}%"></div>
                </div>
            </div>

            <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-2">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Last scan') }}</p>
                <p class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $lastScanAt ? $lastScanAt->format('Y-m-d H:i') : __('No scans yet') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Success rate this month:') }} {{ $successRate }}%
                </p>
            </div>
        </div>

        {{-- Recent Scans --}}
        <div class="rounded-xl bg-white border p-6 dark:bg-gray-800 dark:border-gray-700 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Recent Scans') }}</h3>
                @if (Route::has('scan.index'))
                    <a href="{{ route('scan.index') }}" class="text-sm underline text-indigo-600 dark:text-indigo-400">
                        {{ __('View all') }}
                    </a>
                @endif
            </div>

            @if($recentScans->isEmpty())
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('No scans yet. Start your first scan!') }}
                </div>
                @if (Route::has('scan.create'))
                    <div class="pt-2">
                        <a href="{{ route('scan.create') }}"
                           class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700">
                            {{ __('Start Scan') }}
                        </a>
                    </div>
                @endif
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-2 text-left font-medium">{{ __('Target') }}</th>
                                <th class="py-2 text-left font-medium">{{ __('Type') }}</th>
                                <th class="py-2 text-left font-medium">{{ __('Status') }}</th>
                                <th class="py-2 text-left font-medium">{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                            @foreach($recentScans as $scan)
                                <tr>
                                    <td class="py-3">{{ $scan->target ?? '—' }}</td>
                                    <td>{{ $scan->type ? ucfirst($scan->type) : '—' }}</td>
                                    <td>
                                        <span class="px-2 py-1 text-xs rounded-full {{ $scan->status_badge }}">
                                            {{ $scan->status ? ucfirst($scan->status) : '—' }}
                                        </span>
                                    </td>
                                    <td>{{ optional($scan->created_at)->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex gap-3 pt-2">
                @if (Route::has('scan.create'))
                    <a href="{{ route('scan.create') }}"
                       class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700">
                        {{ __('Start Scan') }}
                    </a>
                @endif
                @if (Route::has('reports.index'))
                    <a href="{{ route('reports.index') }}"
                       class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium border dark:border-gray-700">
                        {{ __('View Reports') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
