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
        $userId = Auth::id();
        $hasScoreCol = Schema::hasColumn('scans', 'heuristics_score');

        $days = (int) $request->query('days', 7);
        $days = max(1, min(90, $days));

        $to   = \Carbon\Carbon::today()->endOfDay();
        $from = \Carbon\Carbon::today()->subDays($days - 1)->startOfDay();

        // Overall totals
        $total = Scan::where('user_id', $userId)->count();

        if ($hasScoreCol) {
            $phishing = Scan::where('user_id', $userId)->where('heuristics_score', '>=', 50)->count();
        } else {
            $jq = "CAST(JSON_EXTRACT(heuristics_json, '$.score') AS UNSIGNED)";
            $phishing = Scan::where('user_id', $userId)->whereRaw("$jq >= 50")->count();
        }

        $phishRate = $total > 0 ? round(($phishing / $total) * 100, 1) : 0.0;

        // Per-day stats
        if ($hasScoreCol) {
            $rows = Scan::selectRaw("
                    DATE(created_at) as d,
                    COUNT(*) as total_c,
                    SUM(CASE WHEN heuristics_score >= 50 THEN 1 ELSE 0 END) as phish_c
                ")
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('d')
                ->orderBy('d')
                ->get()
                ->keyBy('d');
        } else {
            $jq = "CAST(JSON_EXTRACT(heuristics_json, '$.score') AS UNSIGNED)";
            $rows = Scan::selectRaw("
                    DATE(created_at) as d,
                    COUNT(*) as total_c,
                    SUM(CASE WHEN $jq >= 50 THEN 1 ELSE 0 END) as phish_c
                ")
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('d')
                ->orderBy('d')
                ->get()
                ->keyBy('d');
        }

        $labels    = [];
        $dataTotal = [];
        $dataPhish = [];

        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $day = $cursor->toDateString();
            $labels[]    = $cursor->format('M d');
            $dataTotal[] = (int) ($rows[$day]->total_c ?? 0);
            $dataPhish[] = (int) ($rows[$day]->phish_c ?? 0);
            $cursor->addDay();
        }

        return response()->json([
            'ok'     => true,
            'labels' => $labels,
            'data'   => $dataTotal,
            'dataPhish' => $dataPhish,
            'totals' => [
                'total'     => $total,
                'phishing'  => $phishing,
                'phishRate' => $phishRate,
            ],
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
