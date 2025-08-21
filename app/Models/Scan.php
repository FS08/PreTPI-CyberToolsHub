<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'from', 'from_domain', 'to', 'subject',
        'date_raw', 'date_iso',
        'text_length', 'html_length', 'raw_size',
        'attachments_count', 'urls_count',
        'urls_json',
        'spf_json',          // from step 5.2 (SPF)
        'dmarc_json',        // NEW (DMARC)
        'heuristics_json',   // NEW (heuristics)
        'risk_score',        // NEW (risk score)
    ];

    protected $casts = [
        'date_iso'   => 'datetime',
        'urls_json'  => 'array',
        'spf_json'   => 'array',
        'dmarc_json' => 'array',
        'heuristics_json' => 'array', // NEW: heuristics JSON
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Related URL submissions (each extracted URL sent to urlscan.io). */
    public function urls(): HasMany
    {
        return $this->hasMany(ScanUrl::class);
    }
}