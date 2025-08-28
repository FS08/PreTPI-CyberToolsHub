<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanUrl extends Model
{
    protected $fillable = [
        'scan_id', 'url', 'host', 'visibility', 'status',
        'urlscan_uuid', 'result_url', 'error_code', 'error_message',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /** Convenience badge class for rows if you render ScanUrl lists */
    public function getStatusBadgeAttribute(): string
    {
        return match (strtolower((string) $this->status)) {
            'finished', 'completed', 'success', 'done' =>
                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            'running', 'processing', 'in_progress', 'queued', 'pending' =>
                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
            'failed', 'error', 'timeout' =>
                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
            default =>
                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    }
}
