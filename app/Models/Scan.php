<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'from', 'from_domain', 'to', 'subject',
        'date_raw', 'date_iso',
        'text_length', 'html_length', 'raw_size',
        'attachments_count', 'urls_count',
        'urls_json',
        'spf_json',
        'dmarc_json',
        'heuristics_json',
        'risk_score',
    ];

    protected $casts = [
        'date_iso'        => 'datetime',
        'urls_json'       => 'array',
        'spf_json'        => 'array',
        'dmarc_json'      => 'array',
        'heuristics_json' => 'array',
    ];

    /* ===========================
     |          Relations
     * =========================== */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Each extracted URL sent to urlscan.io */
    public function urls(): HasMany
    {
        return $this->hasMany(ScanUrl::class);
    }

    /* ===========================
     |           Scopes
     * =========================== */
    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeThisMonth(Builder $q): Builder
    {
        return $q->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
    }

    /**
     * Consider a scan “successful” if at least one URL reached a finished/ok-ish state.
     * Adjust the list to your own statuses if needed.
     */
    public function scopeSuccessful(Builder $q): Builder
    {
        return $q->whereHas('urls', function (Builder $uq) {
            $uq->whereIn('status', ['finished', 'completed', 'success', 'done']);
        });
    }

    /* ===========================
     |        Accessors (UI)
     * =========================== */

    /**
     * A human “target” for the dashboard.
     * Priority: first related ScanUrl.url (if eager loaded) → first in DB → first in urls_json → from_domain → from.
     */
    public function getTargetAttribute(): ?string
    {
        if ($this->relationLoaded('urls')) {
            $first = $this->urls->first();
            if ($first?->url) return $first->url;
        }

        $firstUrl = $this->urls()->select('url')->orderBy('id')->value('url');
        if ($firstUrl) return $firstUrl;

        // Try urls_json: can be array of strings or array of objects with url/host
        if (is_array($this->urls_json) && !empty($this->urls_json)) {
            $candidate = $this->urls_json[0];
            if (is_string($candidate)) return $candidate;
            if (is_array($candidate)) {
                return $candidate['url'] ?? $candidate['host'] ?? null;
            }
        }

        return $this->from_domain ?? $this->from ?? null;
    }

    /**
     * A normalized status for the whole scan.
     * We take the “latest submitted” ScanUrl status if present, otherwise infer from heuristics/risk.
     */
    public function getStatusAttribute(): ?string
    {
        // Prefer the most recently submitted URL’s status
        if ($this->relationLoaded('urls')) {
            $latest = $this->urls->sortByDesc(fn ($u) => $u->submitted_at ?? $u->created_at)->first();
            if ($latest?->status) return $latest->status;
        } else {
            $latest = $this->urls()->orderByDesc('submitted_at')->orderByDesc('id')->first();
            if ($latest?->status) return $latest->status;
        }

        // Fallbacks based on data you track on the scan itself
        if (is_numeric($this->risk_score)) {
            return $this->risk_score >= 80 ? 'high-risk' : ($this->risk_score >= 40 ? 'medium' : 'low');
        }

        return null;
    }

    /**
     * A Tailwind badge class to pair with status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return match (strtolower((string) $this->status)) {
            'finished', 'completed', 'success', 'done', 'low' =>
                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            'running', 'processing', 'in_progress', 'queued', 'pending', 'medium' =>
                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
            'failed', 'error', 'timeout', 'high-risk' =>
                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
            default =>
                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    }

    /**
     * Last meaningful activity timestamp for sorting/UX.
     * Chooses the latest of scan.created_at, scan.date_iso, latest url.submitted_at.
     */
    public function getLastActivityAtAttribute(): ?Carbon
    {
        $candidates = [
            $this->created_at,
            $this->date_iso,
        ];

        if ($this->relationLoaded('urls')) {
            $candidates[] = optional(
                $this->urls->max(fn ($u) => $u->submitted_at ?? $u->created_at)
            ) instanceof Carbon
                ? $this->urls->max(fn ($u) => $u->submitted_at ?? $u->created_at)
                : ($this->urls->max('submitted_at') ?? $this->urls->max('created_at'));
        } else {
            $candidates[] = $this->urls()->max('submitted_at') ?: $this->urls()->max('created_at');
        }

        // Filter nulls and return the max Carbon
        $candidates = array_filter($candidates);
        if (empty($candidates)) return null;

        return collect($candidates)->max();
    }
}
