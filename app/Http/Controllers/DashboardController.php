<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Scan;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();

        // Recent scans with URLs eager-loaded (so Scan::target accessor is fast)
        $recentScans = Scan::query()
            ->forUser($user->id)
            ->with(['urls:id,scan_id,url,status,submitted_at'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        // KPIs
        $scansThisMonth = Scan::query()
            ->forUser($user->id)
            ->thisMonth()
            ->count();

        // Quota (user column OR config/env fallback)
        $monthlyQuota = $user->monthly_quota
            ?? (int) config('services.scan.monthly_quota', env('SCAN_MONTHLY_QUOTA', 100));

        $quotaUsedPct = $monthlyQuota > 0
            ? (int) round(($scansThisMonth / $monthlyQuota) * 100)
            : 0;

        $lastScanAt = optional($recentScans->first())->created_at;

        $successCount = Scan::query()
            ->forUser($user->id)
            ->thisMonth()
            ->successful()
            ->count();

        $successRate = $scansThisMonth > 0
            ? round(($successCount / $scansThisMonth) * 100)
            : 0;

        return view('dashboard', compact(
            'recentScans',
            'scansThisMonth',
            'monthlyQuota',
            'quotaUsedPct',
            'lastScanAt',
            'successRate'
        ));
    }
}
