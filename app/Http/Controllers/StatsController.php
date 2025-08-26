<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class StatsController extends Controller
{
    /**
     * Stats page (HTML).
     * Renders resources/views/stats.blade.php with initial server-side KPIs + 7-day trend.
     */
    public function index(Request $request)
    {
        $userId      = Auth::id();
        $hasScoreCol = Schema::hasColumn('scans', 'heuristics_score');

        // ---- Totals (cached) ----
        $totalsKey = "stats:totals:$userId";
        $totals = Cache::remember($totalsKey, now()->addMinutes(10), function () use ($userId, $hasScoreCol) {
            $total = Scan::where('user_id', $userId)->count();

            if ($hasScoreCol) {
                $phishing   = Scan::where('user_id', $userId)->where('heuristics_score', '>=', 50)->count();
                $suspicious = Scan::where('user_id', $userId)->whereBetween('heuristics_score', [20, 49])->count();
                $legit      = Scan::where('user_id', $userId)->where('heuristics_score', '<', 20)->count();
            } else {
                $jq = "CAST(JSON_EXTRACT(heuristics_json, '$.score') AS UNSIGNED)";
                $phishing   = Scan::where('user_id', $userId)->whereRaw("$jq >= 50")->count();
                $suspicious = Scan::where('user_id', $userId)->whereRaw("$jq BETWEEN 20 AND 49")->count();
                $legit      = Scan::where('user_id', $userId)->whereRaw("$jq < 20")->count();
            }

            $phishRate = $total > 0 ? round(($phishing / $total) * 100, 1) : 0.0;

            return compact('total', 'phishing', 'suspicious', 'legit', 'phishRate');
        });

        // ---- 7-day trend (cached) ----
        $days     = 7;
        $trendKey = "stats:trend:$userId:days:$days";
        $trend    = Cache::remember($trendKey, now()->addMinutes(10), function () use ($userId, $days) {
            $start  = Carbon::today()->subDays($days - 1);
            $series = Scan::selectRaw('DATE(created_at) as d, COUNT(*) as c')
                ->where('user_id', $userId)
                ->whereDate('created_at', '>=', $start->toDateString())
                ->groupBy('d')
                ->orderBy('d')
                ->pluck('c', 'd')
                ->all();

            $out = [];
            for ($i = 0; $i < $days; $i++) {
                $day = $start->copy()->addDays($i)->toDateString();
                $out[] = ['date' => $day, 'count' => (int)($series[$day] ?? 0)];
            }
            return $out;
        });

        // Pass to view
        return view('stats', [
            'total'      => $totals['total'],
            'phishing'   => $totals['phishing'],
            'suspicious' => $totals['suspicious'],
            'legit'      => $totals['legit'],
            'phishRate'  => $totals['phishRate'],
            'trend'      => $trend,
        ]);
    }

    /**
     * JSON data endpoint for charts.
     * GET /stats/data?days=7  OR  /stats/data?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function data(Request $request)
    {
        $userId      = Auth::id();
        $hasScoreCol = Schema::hasColumn('scans', 'heuristics_score');

        // ---- Parse range ----
        $fromParam = $request->query('from');
        $toParam   = $request->query('to');

        if ($fromParam && $toParam) {
            try {
                $from = Carbon::parse($fromParam)->startOfDay();
                $to   = Carbon::parse($toParam)->endOfDay();
            } catch (\Throwable) {
                return response()->json(['ok' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.'], 422);
            }
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            $days = $from->diffInDays($to) + 1;
            // No caching for arbitrary ranges
            $trend = $this->buildTrend($userId, $from, $to);
        } else {
            // days-based range (cached for 7/14/30)
            $days = (int) $request->integer('days', 7);
            if (!in_array($days, [7, 14, 30], true)) $days = 7;

            $to   = Carbon::today()->endOfDay();
            $from = Carbon::today()->subDays($days - 1)->startOfDay();

            $trendKey = "stats:trend:$userId:days:$days";
            $trend = Cache::remember($trendKey, now()->addMinutes(10), function () use ($userId, $from, $to) {
                return $this->buildTrend($userId, $from, $to);
            });
        }

        // ---- Totals (overall; cached) ----
        $totalsKey = "stats:totals:$userId";
        $totals = Cache::remember($totalsKey, now()->addMinutes(10), function () use ($userId, $hasScoreCol) {
            $total = Scan::where('user_id', $userId)->count();

            if ($hasScoreCol) {
                $phishing   = Scan::where('user_id', $userId)->where('heuristics_score', '>=', 50)->count();
                $suspicious = Scan::where('user_id', $userId)->whereBetween('heuristics_score', [20, 49])->count();
                $legit      = Scan::where('user_id', $userId)->where('heuristics_score', '<', 20)->count();
            } else {
                $jq = "CAST(JSON_EXTRACT(heuristics_json, '$.score') AS UNSIGNED)";
                $phishing   = Scan::where('user_id', $userId)->whereRaw("$jq >= 50")->count();
                $suspicious = Scan::where('user_id', $userId)->whereRaw("$jq BETWEEN 20 AND 49")->count();
                $legit      = Scan::where('user_id', $userId)->whereRaw("$jq < 20")->count();
            }

            $phishRate = $total > 0 ? round(($phishing / $total) * 100, 1) : 0.0;

            return compact('total', 'phishing', 'suspicious', 'legit', 'phishRate');
        });

        // Labels for Chart.js (pretty)
        $labels = collect($trend)->pluck('date')->map(fn($d) => Carbon::parse($d)->format('M d'))->values();
        $counts = collect($trend)->pluck('count')->values();

        return response()->json([
            'ok'     => true,
            'days'   => $days,
            'range'  => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'labels' => $labels,
            'data'   => $counts,
            'trend'  => $trend,   // raw [{date, count}]
            'totals' => $totals,  // overall KPIs
        ]);
    }

    /**
     * Build normalized day-by-day trend between $from and $to (inclusive).
     */
    private function buildTrend(int $userId, Carbon $from, Carbon $to): array
    {
        $series = Scan::selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd')
            ->all();

        $out = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $day = $cursor->toDateString();
            $out[] = ['date' => $day, 'count' => (int)($series[$day] ?? 0)];
            $cursor->addDay();
        }
        return $out;
    }
}
