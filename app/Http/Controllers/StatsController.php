<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $hasScoreCol = Schema::hasColumn('scans', 'heuristics_score');

        // ----- Totaux -----
        $total = Scan::where('user_id', $userId)->count();

        // Verdict counts (phishing / suspicious / legit)
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

        // ----- Série 7 jours (par date de création) -----
        $start = Carbon::today()->subDays(6); // inclut aujourd'hui → 7 points
        $series = Scan::selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->where('user_id', $userId)
            ->whereDate('created_at', '>=', $start->toDateString())
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd')
            ->all();

        // Normalise la série pour avoir chaque jour présent (0 si aucune analyse)
        $trend = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $trend[] = ['date' => $day, 'count' => (int)($series[$day] ?? 0)];
        }

        return view('stats', compact('total', 'phishing', 'suspicious', 'legit', 'phishRate', 'trend'));
    }
}
